<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Database;

use BackupApp\Restore\Integration\Ownership\OwnershipMarker;
use BackupApp\Restore\Integration\Policy\TrustedTargetName;
use BackupApp\Restore\Integration\Sql\ValidatedExecutableStatement;
use BackupApp\Restore\Integration\Sql\CreateTableEvidenceParser;
use DomainException;
use mysqli;
use mysqli_result;
use RuntimeException;

/**
 * Production mysqli adapter blueprint. This build deliberately has no issuer
 * for a live connection: the public constructor fails before touching mysqli.
 */
final class MysqliRestoreConnection implements RestoreConnection, MetadataConnection
{
    private mysqli $connection;
    private string $endpoint;
    private string $identityFingerprint;

    public function __construct() { throw new DomainException('live_restore_execution_disabled'); }
    public function serverIdentityFingerprint(): string { return $this->identityFingerprint; }

    public function targetExists(TrustedTargetName $target): bool
    {
        $statement = $this->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        $name = $target->value();
        $statement->bind_param('s', $name); $statement->execute();
        $exists = $statement->get_result()->fetch_row() !== null;
        $statement->close();
        return $exists;
    }

    public function createTarget(TrustedTargetName $target, string $charset, string $collation): void
    {
        if ($charset !== 'utf8mb4' || $collation !== 'utf8mb4_unicode_ci') { throw new DomainException('unsupported_target_encoding'); }
        $this->executeCommand('CREATE DATABASE ' . $this->identifier($target->value()) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function selectTarget(TrustedTargetName $target): void
    {
        if (!$this->connection->select_db($target->value())) { throw new RuntimeException('target_selection_failed'); }
    }

    public function currentDatabase(): ?string
    {
        $result = $this->queryResult('SELECT DATABASE()');
        $row = $result->fetch_row(); $result->free();
        return is_string($row[0] ?? null) ? $row[0] : null;
    }

    public function execute(ValidatedExecutableStatement $statement): void
    {
        $result = $this->connection->query($statement->sql());
        if ($result === false) { throw new RuntimeException('typed_statement_failed'); }
        if ($result instanceof mysqli_result) { $result->free(); }
    }

    public function createOwnershipMarker(OwnershipMarker $marker): void
    {
        $table = $this->identifier($marker->table());
        $this->executeCommand("CREATE TABLE {$table} (`nonce_hash` char(64) NOT NULL, `run_id` char(32) NOT NULL) ENGINE=InnoDB");
        $statement = $this->prepare("INSERT INTO {$table} (`nonce_hash`, `run_id`) VALUES (?, ?)");
        $evidence = $marker->publicEvidence(); $hash = $evidence['nonce_hash']; $runId = $evidence['run_id'];
        $statement->bind_param('ss', $hash, $runId); $statement->execute(); $statement->close();
    }

    public function readOwnershipMarkerHash(OwnershipMarker $marker): ?string
    {
        $result = $this->queryResult('SELECT `nonce_hash` FROM ' . $this->identifier($marker->table()) . ' LIMIT 2');
        $rows = []; while ($row = $result->fetch_row()) { $rows[] = $row; } $result->free();
        return count($rows) === 1 && is_string($rows[0][0] ?? null) ? $rows[0][0] : null;
    }

    public function dropExactTarget(TrustedTargetName $target): void
    {
        $this->executeCommand('DROP DATABASE ' . $this->identifier($target->value()));
    }

    public function schemaSnapshot(): array
    {
        $database = $this->currentDatabase();
        if ($database === null) { throw new DomainException('target_context_unproven'); }
        $statement = $this->prepare('SELECT TABLE_NAME, TABLE_TYPE, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME');
        $statement->bind_param('s', $database); $statement->execute();
        $result = $statement->get_result(); $objects = ['tables' => [], 'views' => [], 'triggers' => [], 'routines' => [], 'events' => []];
        while ($row = $result->fetch_assoc()) {
            $name = (string) $row['TABLE_NAME'];
            if ((string) $row['TABLE_TYPE'] === 'VIEW') { $objects['views'][$name] = []; }
            else { $objects['tables'][$name] = ['engine' => strtoupper((string) ($row['ENGINE'] ?? '')), 'columns' => []]; }
        }
        $statement->close();
        $columns = $this->prepare('SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION');
        $columns->bind_param('s', $database); $columns->execute(); $result = $columns->get_result(); $types = [];
        while ($row = $result->fetch_assoc()) {
            $table = (string) $row['TABLE_NAME']; $column = (string) $row['COLUMN_NAME'];
            $definition = ['name' => $column, 'type' => strtolower((string) $row['COLUMN_TYPE']), 'nullable' => (string) $row['IS_NULLABLE'] === 'YES'];
            if (isset($objects['tables'][$table])) { $objects['tables'][$table]['columns'][] = $definition; }
            $types[$table][$column] = strtolower((string) $row['COLUMN_TYPE']);
        }
        $columns->close();
        $tableParser = new CreateTableEvidenceParser();
        foreach (array_keys($objects['tables']) as $tableName) {
            $createResult = $this->queryResult('SHOW CREATE TABLE ' . $this->identifier($tableName));
            $createRow = $createResult->fetch_assoc(); $createResult->free();
            $createSql = is_array($createRow) ? (string) ($createRow['Create Table'] ?? '') : '';
            if ($createSql === '') { throw new RuntimeException('target_create_table_unavailable'); }
            $parsed = $tableParser->parse($createSql);
            if ($parsed['table'] !== $tableName) { throw new DomainException('target_schema_identity_mismatch'); }
            $objects['tables'][$tableName] = $parsed['definition'];
        }
        foreach ([
            'triggers' => ['SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ? ORDER BY TRIGGER_NAME', 'TRIGGER_NAME'],
            'routines' => ['SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ? ORDER BY ROUTINE_NAME', 'ROUTINE_NAME'],
            'events' => ['SELECT EVENT_NAME FROM information_schema.EVENTS WHERE EVENT_SCHEMA = ? ORDER BY EVENT_NAME', 'EVENT_NAME'],
        ] as $family => [$sql, $field]) {
            $objectStatement = $this->prepare($sql); $objectStatement->bind_param('s', $database); $objectStatement->execute();
            $objectResult = $objectStatement->get_result();
            while ($row = $objectResult->fetch_assoc()) { $objects[$family][(string) $row[$field]] = []; }
            $objectStatement->close();
        }
        return ['objects' => $objects, 'types' => $types];
    }

    public function rowBatches(string $table, array $columns, int $batchSize): iterable
    {
        if ($batchSize < 1 || $batchSize > 5000 || $columns === []) { throw new DomainException('invalid_row_batch_request'); }
        $projection = implode(', ', array_map(fn (string $column): string => $this->identifier($column), $columns));
        $result = $this->connection->query('SELECT ' . $projection . ' FROM ' . $this->identifier($table), MYSQLI_USE_RESULT);
        if (!$result instanceof mysqli_result) { throw new RuntimeException('target_rows_unavailable'); }
        try {
            $batch = [];
            while ($row = $result->fetch_assoc()) {
                $batch[] = $row;
                if (count($batch) >= $batchSize) { yield $batch; $batch = []; }
            }
            if ($batch !== []) { yield $batch; }
        } finally { $result->free(); }
    }

    public function authorizedSchemas(array $exactNames): array
    {
        $found = [];
        foreach ($exactNames as $name) {
            if (!is_string($name) || preg_match('/^[A-Za-z0-9_$-]+$/D', $name) !== 1) { throw new DomainException('invalid_schema_scope'); }
            $statement = $this->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
            $statement->bind_param('s', $name); $statement->execute();
            if ($statement->get_result()->fetch_row() !== null) { $found[] = $name; }
            $statement->close();
        }
        return $found;
    }

    public function serverIdentityFacts(): array
    {
        $result = $this->queryResult('SELECT VERSION(), @@version_comment');
        $row = $result->fetch_row(); $result->free();
        $version = (string) ($row[0] ?? ''); $productText = strtolower((string) ($row[1] ?? '') . ' ' . $version);
        $product = str_contains($productText, 'mariadb') ? 'mariadb' : 'mysql';
        $identityResult = $this->queryResult($product === 'mariadb' ? 'SELECT @@server_uid' : 'SELECT @@server_uuid');
        $identityRow = $identityResult->fetch_row(); $identityResult->free();
        $nativeIdentity = trim((string) ($identityRow[0] ?? ''));
        return ['product' => $product, 'version' => $version,
            'stable_id' => $nativeIdentity === '' ? '' : $product . ':' . $nativeIdentity, 'endpoint' => $this->endpoint];
    }

    public function currentAccount(): string
    {
        $result = $this->queryResult('SELECT CURRENT_USER()'); $row = $result->fetch_row(); $result->free();
        return (string) ($row[0] ?? '');
    }

    public function grantLines(): array
    {
        $result = $this->queryResult('SHOW GRANTS FOR CURRENT_USER()'); $lines = [];
        while ($row = $result->fetch_row()) { $lines[] = (string) ($row[0] ?? ''); }
        $result->free(); return $lines;
    }

    public function privilegeContextFacts(): array
    {
        $identity = $this->serverIdentityFacts();
        $roleResult = $this->queryResult('SELECT CURRENT_ROLE()');
        $roleRow = $roleResult->fetch_row(); $roleResult->free();
        $roleText = trim((string) ($roleRow[0] ?? ''));
        $activeRoles = $roleText === '' || strtoupper($roleText) === 'NONE' ? [] : [$roleText];
        $facts = ['roles_resolved' => true, 'role_grants_resolved' => true, 'active_roles' => $activeRoles];
        if (($identity['product'] ?? '') === 'mysql') {
            $partialResult = $this->queryResult('SELECT @@partial_revokes');
            $partialRow = $partialResult->fetch_row(); $partialResult->free();
            $facts['partial_revokes'] = in_array(strtolower((string) ($partialRow[0] ?? '')), ['1', 'on'], true);
        } else {
            // PUBLIC resolution is deliberately unsupported until a live-version
            // adapter proves its semantics. This forces attestation closed.
            $facts['public_resolved'] = false;
            $facts['public_grants'] = null;
        }
        return $facts;
    }

    public function close(): void { $this->connection->close(); }

    private function identifier(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_$-]+$/D', $value) !== 1) { throw new DomainException('unsafe_exact_identifier'); }
        return '`' . $value . '`';
    }

    private function queryResult(string $sql): mysqli_result
    {
        $result = $this->connection->query($sql);
        if (!$result instanceof mysqli_result) { throw new RuntimeException('metadata_query_failed'); }
        return $result;
    }

    private function executeCommand(string $sql): void
    {
        if ($this->connection->query($sql) !== true) { throw new RuntimeException('exact_command_failed'); }
    }

    private function prepare(string $sql): \mysqli_stmt
    {
        $statement = $this->connection->prepare($sql);
        if ($statement === false) { throw new RuntimeException('metadata_prepare_failed'); }
        return $statement;
    }
}
