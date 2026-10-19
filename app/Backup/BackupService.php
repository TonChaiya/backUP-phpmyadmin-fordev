<?php

declare(strict_types=1);

namespace BackupApp\Backup;

use BackupApp\Database\AuthorizedSourcePolicy;
use mysqli;
use RuntimeException;
use Throwable;

/** Official utility export plus the existing artifact inventory/download interface. */
final class BackupService
{
    private BackupArtifactPublisher $publisher;
    private DumpEngine $engine;

    public function __construct(private string $backupPath, ?BackupArtifactPublisher $publisher = null, ?DumpEngine $engine = null)
    {
        $this->publisher = $publisher ?? new BackupArtifactPublisher();
        $this->engine = $engine ?? new MysqldumpEngine();
    }

    /** @param array<string,mixed> $connectionData @return array<string,mixed> */
    public function backupDatabase(mysqli $connection, string $database, array $connectionData = []): array
    {
        (new AuthorizedSourcePolicy())->assertSelected($database,$connectionData['selected_databases']??[]);
        MysqldumpCommand::assertConnection($connectionData);
        $identity = MysqldumpCommand::verifyConnection($connection, $connectionData);
        (new \BackupApp\Database\DatabaseRepository())->assertVisible($connection,$database);
        $before = $this->tableInventory($connection, $database);
        $folder = $this->backupPath . DIRECTORY_SEPARATOR . $database;
        if (is_link($folder) || (file_exists($folder) && !is_dir($folder))) {
            throw new RuntimeException('mysqldump_artifact_directory_unsafe');
        }
        if (!is_dir($folder) && !@mkdir($folder, 0750, true)) {
            throw new RuntimeException('mysqldump_artifact_directory_failed');
        }
        $root = realpath($this->backupPath);
        $actual = realpath($folder);
        if ($root === false || $actual === false
            || strcasecmp($actual, $root . DIRECTORY_SEPARATOR . $database) !== 0) {
            throw new RuntimeException('mysqldump_artifact_directory_unsafe');
        }
        $filename = date('Y-m-d_H-i-s') . '_' . bin2hex(random_bytes(8)) . '_' . $database . '.sql';
        $path = $folder . DIRECTORY_SEPARATOR . $filename;
        $temporary = $path . '.tmp';
        // Reserve ownership before handing the file to the process adapter.
        $reservation = @fopen($temporary, 'xb');
        if ($reservation === false) { throw new RuntimeException('mysqldump_artifact_collision'); }
        fclose($reservation);
        try {
            $engine = $this->engine->dump($database, $connectionData, $temporary);
            if ($engine['exit_code'] !== 0) { throw new RuntimeException('mysqldump_nonzero_exit'); }
            $after = $this->tableInventory($connection, $database);
            if ($before !== $after) { throw new RuntimeException('mysqldump_visible_schema_changed'); }
            clearstatcache(true, $temporary);
            $hash = hash_file('sha256', $temporary);
            $bytes = filesize($temporary);
            if (!is_string($hash) || $bytes === false || $bytes === 0) {
                throw new RuntimeException('mysqldump_artifact_empty');
            }
            $relative = $database . '/' . $filename;
            $manifest = [
                'database' => $database, 'filename' => $filename, 'relative_path' => $relative,
                'created_at' => date('c'), 'bytes' => $bytes, 'sha256' => $hash, 'complete' => true,
                'scope' => 'innodb_tables_structure_and_data', 'full_database_objects' => false,
                'excluded_objects' => ['views', 'triggers', 'routines', 'events'],
                'excluded_object_presence' => 'not_verified_by_select_only_account',
                'engine' => $engine, 'server' => $identity,
                'snapshot' => [
                    'data_consistency' => 'mysqldump_single_transaction_innodb',
                    'concurrent_ddl' => 'must_be_avoided_not_prevented_by_application',
                    'visible_inventory_check' => 'pre_post_drift_detector_not_ddl_exclusion_proof',
                    'table_inventory_sha256' => hash('sha256', json_encode($before, JSON_THROW_ON_ERROR)),
                ],
                'stats' => ['tables' => count($before), 'rows' => null],
            ];
            $published = $this->publisher->publish($temporary, $path, $manifest, $hash);
            return [
                'database' => $database, 'filename' => $filename, 'relative_path' => $relative,
                'bytes' => $published['bytes'], 'sha256' => $published['sha256'],
                'manifest_path' => $relative . '.json',
            ];
        } catch (Throwable $e) {
            // Only the random temporary artifact owned by this attempt.
            if (is_file($temporary)) { @unlink($temporary); }
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> */
    private function tableInventory(mysqli $connection, string $database): array
    {
        $escaped = $connection->real_escape_string($database);
        $result = $connection->query(
            "SELECT TABLE_NAME, TABLE_TYPE, ENGINE FROM information_schema.TABLES"
            . " WHERE TABLE_SCHEMA = '{$escaped}' ORDER BY TABLE_NAME"
        );
        $tables = [];
        while ($row = $result->fetch_assoc()) {
            if (($row['TABLE_TYPE'] ?? '') !== 'BASE TABLE' || ($row['ENGINE'] ?? '') !== 'InnoDB') {
                throw new RuntimeException('mysqldump_unsupported_view_or_table_engine');
            }
            $tables[] = $row;
        }
        $cross = $connection->query(
            "SELECT COUNT(*) AS cross_schema FROM information_schema.KEY_COLUMN_USAGE"
            . " WHERE CONSTRAINT_SCHEMA = '{$escaped}' AND REFERENCED_TABLE_SCHEMA IS NOT NULL"
            . " AND REFERENCED_TABLE_SCHEMA <> CONSTRAINT_SCHEMA"
        )->fetch_assoc();
        if (!is_array($cross) || (int) ($cross['cross_schema'] ?? -1) !== 0) {
            throw new RuntimeException('mysqldump_cross_schema_dependency_unsupported');
        }
        return $tables;
    }

    /** Lightweight history only: never grants Restore eligibility or hashes the SQL.
     * @param array<string,mixed>|null $origin @return array<string,string>|null */
    public function mysqldumpHistoryMetadata(string $relativePath, ?array $origin): ?array
    {
        try { $sql = $this->resolveBackupPath($relativePath); } catch (RuntimeException) { return null; }
        $manifestPath = realpath($sql . '.json');
        $root = realpath($this->backupPath);
        if ($manifestPath === false || $root === false || !is_file($manifestPath)
            || !str_starts_with($manifestPath, $root . DIRECTORY_SEPARATOR)) { return null; }
        $size = filesize($manifestPath);
        if ($size === false || $size < 2 || $size > 1048576) { return null; }
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($manifest) || ($manifest['complete'] ?? null) !== true
            || ($manifest['full_database_objects'] ?? null) !== false
            || ($manifest['scope'] ?? '') !== 'innodb_tables_structure_and_data'
            || ($manifest['filename'] ?? '') !== basename($sql)
            || ($manifest['relative_path'] ?? '') !== $relativePath
            || ($manifest['bytes'] ?? null) !== filesize($sql)
            || !is_string($manifest['sha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $manifest['sha256']) !== 1
            || !is_string($manifest['database'] ?? null)
            || !(new AuthorizedSourcePolicy())->isAuthorized($manifest['database'])
            || ($manifest['engine']['name'] ?? '') !== 'mysqldump'
            || ($manifest['engine']['version'] ?? '') !== MysqldumpCommand::VERSION
            || ($manifest['engine']['profile'] ?? '') !== MysqldumpCommand::PROFILE
            || ($manifest['engine']['exit_code'] ?? null) !== 0) { return null; }
        $verified = $origin !== null && ($origin['database'] ?? '') === $manifest['database']
            && ($origin['bytes'] ?? null) === $manifest['bytes']
            && hash_equals((string) ($origin['sha256'] ?? ''), $manifest['sha256']);
        return [
            'database' => $manifest['database'], 'restore_status' => 'unsupported_mysqldump_format',
            'restore_eligibility' => 'ineligible', 'integrity_status' => 'not_checked',
            'provenance_status' => $verified ? 'verified' : 'unverified_origin',
        ];
    }

    /**
     * @return array<int, array{name: string, folder: string, relative_path: string, size: int, modified: int}>
     */
    public function listBackupFiles(): array
    {
        $files = $this->findSqlFiles($this->backupPath);
        $items = [];

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $relativePath = $this->relativePath($file);

            $items[] = [
                'name' => basename($file),
                'folder' => dirname($relativePath) === '.' ? '' : dirname($relativePath),
                'relative_path' => str_replace('\\', '/', $relativePath),
                'size' => filesize($file) ?: 0,
                'modified' => filemtime($file) ?: 0,
            ];
        }

        usort($items, static fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        return $items;
    }

    public function resolveBackupPath(string $filename): string
    {
        $requested = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filename);
        $requested = ltrim($requested, DIRECTORY_SEPARATOR);
        $path = realpath($this->backupPath . DIRECTORY_SEPARATOR . $requested);
        $backupRoot = realpath($this->backupPath);

        $backupRootWithSeparator = $backupRoot === false
            ? false
            : rtrim($backupRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (
            $path === false
            || $backupRoot === false
            || $backupRootWithSeparator === false
            || ($path !== $backupRoot && !str_starts_with($path, $backupRootWithSeparator))
            || !is_file($path)
        ) {
            throw new RuntimeException('Backup file not found');
        }

        return $path;
    }

    /**
     * @return array<int, string>
     */
    private function findSqlFiles(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'sql') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function relativePath(string $path): string
    {
        $root = realpath($this->backupPath);
        $realPath = realpath($path);

        if ($root === false || $realPath === false) {
            return basename($path);
        }

        return ltrim(substr($realPath, strlen($root)), DIRECTORY_SEPARATOR);
    }

}
