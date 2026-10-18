<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Sql;

use BackupApp\Restore\Integration\Sql\Statement\AlterTable;
use BackupApp\Restore\Integration\Sql\Statement\CreatePlaceholderView;
use BackupApp\Restore\Integration\Sql\Statement\CreateTable;
use BackupApp\Restore\Integration\Sql\Statement\DropObject;
use BackupApp\Restore\Integration\Sql\Statement\InsertRows;
use BackupApp\Restore\Integration\Sql\Statement\LockTables;
use BackupApp\Restore\Integration\Sql\Statement\SessionDirective;
use BackupApp\Restore\Integration\Sql\Statement\SourceDatabaseDeclaration;
use BackupApp\Restore\Integration\Sql\Statement\SourceDatabaseSelection;
use BackupApp\Restore\Integration\Sql\Statement\UnlockTables;
use BackupApp\Restore\Sql\SqlStatementLexer;
use BackupApp\Restore\StructuralSqlParser;
use BackupApp\Restore\Integration\Validation\RowCanonicalizer;
use BackupApp\Restore\Integration\Validation\RowEvidenceAccumulator;
use DomainException;
use RuntimeException;

final class RestorePlanCompiler
{
    public const MAX_ARTIFACT_BYTES = 67108864;
    public const MAX_PLAN_ITEMS = 20000;
    public function __construct(
        private readonly SqlStatementLexer $lexer = new SqlStatementLexer(),
        private readonly StructuralSqlParser $validator = new StructuralSqlParser(),
        private readonly InsertRowsParser $insertParser = new InsertRowsParser(),
        private readonly RowCanonicalizer $rowCanonicalizer = new RowCanonicalizer()
    ) {
    }

    public function compileFile(string $path, string $expectedDatabase): RestorePlan
    {
        if (!$this->hasCompletionMarker($path)) { throw new DomainException('artifact_completion_marker_missing'); }
        $validation = $this->validator->validateFile($path, $expectedDatabase);
        if (($validation['supported'] ?? false) !== true) {
            throw new DomainException('unsupported_sql:' . implode(',', $validation['findings'] ?? []));
        }
        if ((int) ($validation['content_bytes'] ?? 0) > self::MAX_ARTIFACT_BYTES) {
            throw new DomainException('artifact_too_large');
        }
        $stream = $this->lexer->streamFile($path);
        $spool = new StatementSpoolWriter();
        try {
            $plan = $this->compileStatements(
                (function () use ($stream): \Generator { foreach ($stream as $lexed) { yield $lexed->sql(); } })(),
                $expectedDatabase,
                (int) $validation['content_bytes'],
                (string) $validation['content_sha256'],
                $spool
            );
            $spool->finish();
        } catch (\Throwable $exception) {
            $spool->abort();
            throw $exception;
        }
        $fingerprint = $stream->getReturn();
        if (($validation['content_bytes'] ?? null) !== $fingerprint['bytes']
            || !is_string($validation['content_sha256'] ?? null)
            || !hash_equals($validation['content_sha256'], $fingerprint['sha256'])) {
            throw new DomainException('artifact_changed_during_compile');
        }
        return $plan;
    }

    public function compile(string $sql, string $expectedDatabase): RestorePlan
    {
        if (strlen($sql) > self::MAX_ARTIFACT_BYTES) { throw new DomainException('artifact_too_large'); }
        $validation = $this->validator->validate($sql, $expectedDatabase);
        if (($validation['supported'] ?? false) !== true) {
            throw new DomainException('unsupported_sql:' . implode(',', $validation['findings'] ?? []));
        }

        $statements = [];
        foreach ($this->lexer->stream($sql) as $lexed) { $statements[] = $lexed->sql(); }
        return $this->compileStatements($statements, $expectedDatabase, strlen($sql), hash('sha256', $sql));
    }

    /** @param iterable<string> $statements */
    private function compileStatements(
        iterable $statements,
        string $expectedDatabase,
        int $bytes,
        string $sha256,
        ?StatementSpoolWriter $spool = null
    ): RestorePlan
    {
        $items = [];
        $inventory = ['tables' => [], 'views' => []];
        $schemaEvidence = ['tables' => [], 'views' => [], 'triggers' => [], 'routines' => [], 'events' => []];
        /** @var array<string, RowEvidenceAccumulator> $accumulators */
        $accumulators = [];
        $evidenceColumns = [];
        $statementCount = 0;
        foreach ($statements as $statementSql) {
            if (++$statementCount > self::MAX_PLAN_ITEMS) { throw new DomainException('plan_item_limit_exceeded'); }
            $statement = trim($statementSql);
            $normalized = $this->withoutLeadingComments($statement);
            $payload = $spool?->append($normalized) ?? $normalized;
            if (preg_match('/^\/\*![0-9]{5}\s+SET\b/i', $normalized) === 1) {
                $items[] = new SessionDirective($payload);
            } elseif (preg_match('/^CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+`([^`]+)`/i', $normalized, $m) === 1) {
                $items[] = new SourceDatabaseDeclaration($m[1]);
            } elseif (preg_match('/^USE\s+`([^`]+)`$/i', $normalized, $m) === 1) {
                $items[] = new SourceDatabaseSelection($m[1]);
            } elseif (preg_match('/^CREATE\s+TABLE\s+`([^`]+)`/i', $normalized, $m) === 1) {
                $items[] = new CreateTable($payload, $m[1]);
                $inventory['tables'][] = $m[1];
                $tableEvidence = (new CreateTableEvidenceParser())->parse($normalized);
                $schemaEvidence['tables'][$tableEvidence['table']] = $tableEvidence['definition'];
            } elseif (preg_match('/^CREATE\s+OR\s+REPLACE\s+VIEW\s+`([^`]+)`/i', $normalized, $m) === 1) {
                $items[] = new CreatePlaceholderView($payload);
                $inventory['views'][] = $m[1];
                $schemaEvidence['views'][$m[1]] = [];
            } elseif (preg_match('/^DROP\s+(?:TABLE|VIEW|TRIGGER|PROCEDURE|FUNCTION|EVENT)\b/i', $normalized) === 1) {
                $items[] = new DropObject($payload);
            } elseif (preg_match('/^INSERT\s+INTO\b/i', $normalized) === 1) {
                $parsed = $this->insertParser->parse($normalized);
                $table = $parsed->table();
                $columns = $parsed->columns();
                if (isset($evidenceColumns[$table]) && $evidenceColumns[$table] !== $columns) {
                    throw new DomainException('inconsistent_insert_column_order');
                }
                $evidenceColumns[$table] = $columns;
                $accumulators[$table] ??= new RowEvidenceAccumulator($table, $columns);
                foreach ($parsed->rows() as $row) {
                    $accumulators[$table]->addCanonicalRow($this->rowCanonicalizer->canonicalizeTyped($row, $columns));
                }
                $items[] = new InsertRows($payload, $table, $columns, count($parsed->rows()));
            } elseif (preg_match('/^ALTER\s+TABLE\b/i', $normalized) === 1) {
                $items[] = new AlterTable($payload);
                if (preg_match('/^ALTER\s+TABLE\s+`([^`]+)`\s+ADD\s+(CONSTRAINT\s+[\s\S]+)$/i', $normalized, $constraint) === 1) {
                    if (!isset($schemaEvidence['tables'][$constraint[1]])) { throw new DomainException('alter_table_evidence_missing'); }
                    $schemaEvidence['tables'][$constraint[1]]['constraints'][] =
                        (new CreateTableEvidenceParser())->canonicalizeFragment($constraint[2]);
                    sort($schemaEvidence['tables'][$constraint[1]]['constraints'], SORT_STRING);
                }
            } elseif (preg_match('/^LOCK\s+TABLES\b/i', $normalized) === 1) {
                $items[] = new LockTables($payload);
            } elseif (preg_match('/^UNLOCK\s+TABLES$/i', $normalized) === 1) {
                $items[] = new UnlockTables($payload);
            } else {
                throw new DomainException('unsupported_typed_statement');
            }
        }

        $dataEvidence = [];
        foreach ($accumulators as $table => $accumulator) {
            $schemaColumns = array_map(
                static fn (array $column): string => (string) $column['name'],
                (array) ($schemaEvidence['tables'][$table]['columns'] ?? [])
            );
            if ($schemaColumns === [] || $schemaColumns !== ($evidenceColumns[$table] ?? [])) {
                throw new DomainException('insert_columns_incomplete_or_reordered');
            }
            $dataEvidence[$table] = $accumulator->finish();
        }
        return new RestorePlan(
            $expectedDatabase,
            $items,
            $bytes,
            $sha256,
            ['tables' => array_values(array_unique($inventory['tables'])), 'views' => array_values(array_unique($inventory['views']))],
            $dataEvidence,
            $schemaEvidence
        );
    }

    private function withoutLeadingComments(string $sql): string
    {
        do {
            $previous = $sql;
            $sql = preg_replace('/\A\s*(?:--[^\r\n]*(?:\r?\n|\z)|#[^\r\n]*(?:\r?\n|\z)|\/\*(?![!]|M!)[\s\S]*?\*\/)\s*/i', '', $sql) ?? $sql;
        } while ($sql !== $previous);
        return trim($sql);
    }

    private function hasCompletionMarker(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) { throw new RuntimeException('artifact_read_failed'); }
        try {
            $size = filesize($path);
            if (!is_int($size)) { return false; }
            if (fseek($handle, max(0, $size - 4096)) !== 0) { return false; }
            $tail = stream_get_contents($handle);
            return is_string($tail)
                && preg_match('/(?:^|\R)-- Backup completed successfully\s*\z/D', $tail) === 1;
        } finally { fclose($handle); }
    }
}
