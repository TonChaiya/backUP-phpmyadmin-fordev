<?php

declare(strict_types=1);

namespace BackupApp\Restore;

use BackupApp\Restore\Integration\Sql\InsertRowsParser;
use BackupApp\Restore\Sql\SqlStatementLexer;
use DomainException;

final class StructuralSqlParser implements SqlStructureValidator
{
    private const MAX_STATEMENT_BYTES = 16777216;

    public function __construct(private readonly SqlStatementLexer $lexer = new SqlStatementLexer())
    {
    }

    /** @return array<string, mixed> */
    public function validate(string $sql, string $expectedDatabase): array
    {
        return $this->validateLexed($sql, $expectedDatabase);
    }

    /** @return array<string, mixed> */
    public function validateFile(string $path, string $expectedDatabase): array
    {
        $state = ['database' => null, 'create_count' => 0, 'use_count' => 0, 'statement_count' => 0, 'findings' => []];
        $fingerprint = ['bytes' => null, 'sha256' => null];
        try {
            $stream = $this->lexer->streamFile($path);
            foreach ($stream as $statement) { $this->evaluateStatement($statement->sql(), $expectedDatabase, $state); }
            $fingerprint = $stream->getReturn();
        } catch (\Throwable $exception) {
            $state['findings'][] = $exception instanceof DomainException ? $exception->getMessage() : 'artifact_read_failed';
        }
        if ($state['create_count'] !== 1 || $state['use_count'] !== 1 || $state['database'] !== $expectedDatabase) {
            $state['findings'][] = 'unproven_database_scope';
        }
        return $this->result($state['database'], $state['statement_count'], $fingerprint['bytes'], array_values(array_unique($state['findings'])), $fingerprint['sha256']);
    }

    /** @return array<string, mixed> */
    private function validateLexed(string $sql, string $expectedDatabase): array
    {
        $state = [
            'database' => null,
            'create_count' => 0,
            'use_count' => 0,
            'statement_count' => 0,
            'findings' => [],
        ];

        try {
            foreach ($this->lexer->stream($sql) as $statement) {
                $this->evaluateStatement($statement->sql(), $expectedDatabase, $state);
            }
        } catch (DomainException $exception) {
            $state['findings'][] = $exception->getMessage();
        }

        if ($state['create_count'] !== 1 || $state['use_count'] !== 1 || $state['database'] !== $expectedDatabase) {
            $state['findings'][] = 'unproven_database_scope';
        }

        return $this->result(
            $state['database'],
            $state['statement_count'],
            strlen($sql),
            array_values(array_unique($state['findings'])),
            hash('sha256', $sql)
        );
    }

    /** @param iterable<string> $lines @return array<string, mixed> */
    private function validateLines(iterable $lines, string $expectedDatabase, ?int $knownBytes, ?string $knownHash): array
    {
        $state = [
            'database' => null,
            'create_count' => 0,
            'use_count' => 0,
            'statement_count' => 0,
            'findings' => [],
        ];
        $delimiter = ';';
        $buffer = '';
        $quote = null;
        $escaped = false;
        $lineComment = false;
        $blockComment = false;
        $discarding = false;

        foreach ($lines as $rawLine) {
            $line = $rawLine;

            if ($quote === null && !$lineComment && !$blockComment && !$discarding
                && preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $match) === 1) {
                if (!$this->onlyWhitespaceOrComments($buffer)) {
                    $state['findings'][] = 'delimiter_changed_mid_statement';
                }
                $buffer = '';
                $delimiter = $match[1];
                continue;
            }

            $length = strlen($line);
            for ($i = 0; $i < $length; $i++) {
                $char = $line[$i];
                $next = $i + 1 < $length ? $line[$i + 1] : '';

                if ($lineComment) {
                    if (!$discarding) {
                        $buffer .= $char;
                    }
                    if ($char === "\n") {
                        $lineComment = false;
                        if (!$discarding && $this->onlyWhitespaceOrComments($buffer)
                            && !str_contains($buffer, '/*!') && stripos($buffer, '/*M!') === false) {
                            $buffer = '';
                        }
                    }
                    continue;
                }

                if ($blockComment) {
                    if (!$discarding) {
                        $buffer .= $char;
                    }
                    if ($char === '*' && $next === '/') {
                        if (!$discarding) {
                            $buffer .= $next;
                        }
                        $i++;
                        $blockComment = false;
                        if (!$discarding && $this->onlyWhitespaceOrComments($buffer)
                            && !str_contains($buffer, '/*!') && stripos($buffer, '/*M!') === false) {
                            $buffer = '';
                        }
                    }
                    continue;
                }

                if ($quote !== null) {
                    if (!$discarding) {
                        $buffer .= $char;
                    }
                    if ($escaped) {
                        $escaped = false;
                        continue;
                    }
                    if ($char === '\\' && $quote !== '`') {
                        $escaped = true;
                        continue;
                    }
                    if ($char === $quote) {
                        if ($next === $quote) {
                            if (!$discarding) {
                                $buffer .= $next;
                            }
                            $i++;
                        } else {
                            $quote = null;
                        }
                    }
                    continue;
                }

                if ($char === '#' || ($char === '-' && $next === '-')) {
                    if (!$discarding) {
                        $buffer .= $char;
                        if ($char === '-') {
                            $buffer .= $next;
                        }
                    }
                    if ($char === '-') {
                        $i++;
                    }
                    $lineComment = true;
                    continue;
                }

                if ($char === '/' && $next === '*') {
                    if (!$discarding) {
                        $buffer .= '/*';
                    }
                    $i++;
                    $blockComment = true;
                    continue;
                }

                if (in_array($char, ["'", '"', '`'], true)) {
                    if (!$discarding) {
                        $buffer .= $char;
                    }
                    $quote = $char;
                    continue;
                }

                if (substr($line, $i, strlen($delimiter)) === $delimiter) {
                    if (!$discarding) {
                        $this->evaluateStatement($buffer, $expectedDatabase, $state);
                    }
                    $buffer = '';
                    $discarding = false;
                    $i += strlen($delimiter) - 1;
                    continue;
                }

                if (!$discarding) {
                    $buffer .= $char;
                    if (strlen($buffer) > self::MAX_STATEMENT_BYTES) {
                        $state['findings'][] = 'statement_too_large';
                        $buffer = '';
                        $discarding = true;
                    }
                }
            }
        }

        if ($quote !== null || $blockComment) {
            $state['findings'][] = 'unterminated_sql_lexeme';
        } elseif (!$this->onlyWhitespaceOrComments($buffer)) {
            $state['findings'][] = $delimiter !== ';' ? 'unterminated_delimiter' : 'unterminated_statement';
        } elseif ($delimiter !== ';') {
            $state['findings'][] = 'unterminated_delimiter';
        }

        if ($state['create_count'] !== 1 || $state['use_count'] !== 1 || $state['database'] !== $expectedDatabase) {
            $state['findings'][] = 'unproven_database_scope';
        }

        return $this->result(
            $state['database'],
            $state['statement_count'],
            $knownBytes,
            array_values(array_unique($state['findings'])),
            $knownHash
        );
    }

    /** @param array<string, mixed> $state */
    private function evaluateStatement(string $statement, string $expectedDatabase, array &$state): void
    {
        $trimmed = trim($statement);
        if ($trimmed === '') {
            return;
        }

        $stringsMasked = $this->maskStrings($trimmed);
        if (str_contains($stringsMasked, '/*!') || stripos($stringsMasked, '/*M!') !== false) {
            $state['statement_count']++;
            if ($this->allowedVersionedSet($trimmed)) {
                return;
            }
            $state['findings'][] = 'unsupported_executable_comment';
            return;
        }

        $masked = $this->maskComments($stringsMasked);
        if (trim($masked) === '') {
            return;
        }
        $state['statement_count']++;
        if ($this->containsDangerous($masked)) {
            $state['findings'][] = 'dangerous_or_dynamic_construct';
            return;
        }

        if (preg_match('/(?:`[^`]+`|\b[A-Za-z_][A-Za-z0-9_$]*)\s*\.\s*(?:`[^`]+`|[A-Za-z_][A-Za-z0-9_$]*\b)/', $masked) === 1) {
            $state['findings'][] = 'schema_qualified_reference';
            return;
        }

        if (preg_match('/^CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+`([^`]+)`(?:\s|$)/i', $masked, $match) === 1) {
            $state['create_count']++;
            $state['database'] = $match[1];
            if ($match[1] !== $expectedDatabase) {
                $state['findings'][] = 'database_scope_mismatch';
            }
            return;
        }

        if (preg_match('/^USE\s+`([^`]+)`$/i', trim($masked), $match) === 1) {
            $state['use_count']++;
            if ($match[1] !== $expectedDatabase) {
                $state['findings'][] = 'database_scope_mismatch';
            }
            return;
        }

        if (!$this->supportedStatement($masked)) {
            $state['findings'][] = 'unsupported_statement';
        }
    }

    /** @param array<int, string> $findings @return array<string, mixed> */
    private function result(?string $database, int $statementCount, ?int $bytes, array $findings, ?string $sha256): array
    {
        $findings = array_values(array_unique($findings));

        return [
            'supported' => $findings === [],
            'database' => $database,
            'findings' => $findings,
            'statement_count' => $statementCount,
            'content_bytes' => $bytes,
            'content_sha256' => $sha256,
        ];
    }

    private function onlyWhitespaceOrComments(string $sql): bool
    {
        return trim($this->maskComments($sql)) === '';
    }

    private function maskStrings(string $sql): string
    {
        $strings = preg_replace("/'(?:''|\\\\.|[^'])*'|\"(?:\"\"|\\\\.|[^\"])*\"/", "''", $sql);

        return is_string($strings) ? $strings : $sql;
    }

    private function maskComments(string $sql): string
    {
        $comments = preg_replace('/\/\*[\s\S]*?\*\/|--[^\r\n]*|#[^\r\n]*/', ' ', $sql);

        return is_string($comments) ? $comments : $sql;
    }

    private function supportedStatement(string $statement): bool
    {
        $sql = trim($statement);
        $identifier = '`(?:``|[^`])+`';
        $identifierList = $identifier . '(?:\s*,\s*' . $identifier . ')*';

        if (preg_match('/^CREATE\s+TABLE\s+' . $identifier . '\s*\([\s\S]+\)([\s\S]*)$/i', $sql, $tableMatch) === 1) {
            $options = $tableMatch[1];
            $engineCount = preg_match_all('/\bENGINE(?:\s*=\s*|\s+)([A-Za-z0-9_]+)/i', $options, $engineMatches);

            return $engineCount === 1
                && in_array(strtoupper($engineMatches[1][0]), ['INNODB', 'MYISAM', 'ARIA', 'MEMORY', 'CSV'], true)
                && preg_match('/\bSELECT\b/i', $sql) !== 1;
        }
        if (preg_match('/^CREATE\s+OR\s+REPLACE\s+VIEW\s+' . $identifier . '\s+AS\s+SELECT\s+1\s+AS\s+' . $identifier
            . '(?:\s*,\s*1\s+AS\s+' . $identifier . ')*$/i', $sql) === 1) {
            return true;
        }
        if (preg_match('/^DROP\s+(?:TABLE|VIEW|TRIGGER|PROCEDURE|FUNCTION|EVENT)\s+IF\s+EXISTS\s+' . $identifier . '$/i', $sql) === 1) {
            return true;
        }
        if (preg_match('/^ALTER\s+TABLE\s+' . $identifier . '\s+(?:DISABLE\s+KEYS|ENABLE\s+KEYS|AUTO_INCREMENT\s*=\s*[1-9][0-9]*)$/i', $sql) === 1) {
            return true;
        }
        if (preg_match('/^ALTER\s+TABLE\s+' . $identifier . '\s+ADD\s+CONSTRAINT\s+' . $identifier
            . '\s+FOREIGN\s+KEY\s*\(\s*' . $identifierList . '\s*\)\s+REFERENCES\s+' . $identifier
            . '\s*\(\s*' . $identifierList . '\s*\)(?:\s+ON\s+DELETE\s+(?:CASCADE|SET\s+NULL|NO\s+ACTION))?'
            . '(?:\s+ON\s+UPDATE\s+(?:CASCADE|SET\s+NULL|NO\s+ACTION))?$/i', $sql) === 1) {
            return true;
        }
        if (preg_match('/^INSERT\s+INTO\b/i', $sql) === 1) {
            try { (new InsertRowsParser())->parse($sql); return true; }
            catch (DomainException) { return false; }
        }

        return preg_match('/^LOCK\s+TABLES\s+' . $identifier . '\s+WRITE$/i', $sql) === 1
            || preg_match('/^UNLOCK\s+TABLES$/i', $sql) === 1;
    }

    private function containsDangerous(string $statement): bool
    {
        return preg_match('/\b(DEFINER|PREPARE|EXECUTE|CALL|DROP\s+DATABASE|LOAD\s+DATA|LOAD_FILE|INFILE|OUTFILE|GRANT|REVOKE|CREATE\s+USER|ALTER\s+USER|DROP\s+USER|INSTALL\s+PLUGIN|UNINSTALL\s+PLUGIN|SONAME|SOURCE|TABLESPACE|FEDERATED|CONNECTION)\b|\bENGINE\s*=\s*CONNECT\b|\b(?:DATA|INDEX)\s+DIRECTORY\b|\bSET\s+(?:GLOBAL|PERSIST|PERSIST_ONLY)\b/i', $statement) === 1;
    }

    private function allowedVersionedSet(string $statement): bool
    {
        $assignment = '(?:'
            . '@OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT'
            . '|@OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS'
            . '|@OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION'
            . '|NAMES\s+utf8mb4'
            . '|@OLD_TIME_ZONE=@@TIME_ZONE'
            . "|TIME_ZONE='\\+00:00'"
            . '|@OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS,\s*UNIQUE_CHECKS=0'
            . '|@OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS,\s*FOREIGN_KEY_CHECKS=0'
            . "|@OLD_SQL_MODE=@@SQL_MODE,\s*SQL_MODE='NO_AUTO_VALUE_ON_ZERO'"
            . '|@OLD_SQL_NOTES=@@SQL_NOTES,\s*SQL_NOTES=0'
            . '|CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT'
            . '|CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS'
            . '|COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION'
            . '|TIME_ZONE=@OLD_TIME_ZONE'
            . '|SQL_NOTES=@OLD_SQL_NOTES'
            . ')';

        return preg_match('/^\/\*!\d{5}\s+SET\s+' . $assignment . '\s*\*\/$/i', trim($statement)) === 1;
    }
}
