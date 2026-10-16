<?php

declare(strict_types=1);

namespace BackupApp\Backup;

use BackupApp\Support\Security;
use mysqli;
use RuntimeException;

final class BackupService
{
    private const COMPLETION_MARKER = '-- Backup completed successfully';
    private const INSERT_BATCH_LIMIT = 100;

    public function __construct(private string $backupPath)
    {
    }

    /**
     * @return array{database: string, filename: string, relative_path: string, bytes: int, sha256: string, manifest_path: string}
     */
    public function backupDatabase(mysqli $connection, string $database): array
    {
        if (!preg_match('/^[A-Za-z0-9_$-]+$/', $database)) {
            throw new RuntimeException('Invalid database name');
        }

        $connection->select_db($database);
        $databaseFolder = Security::safeFilename($database);
        $folderPath = $this->backupPath . DIRECTORY_SEPARATOR . $databaseFolder;

        if (!is_dir($folderPath) && !mkdir($folderPath, 0750, true)) {
            throw new RuntimeException('Cannot create database backup folder');
        }

        $timestamp = date('Y-m-d_H-i-s');
        $filename = Security::safeFilename($timestamp . '_' . $database) . '.sql';
        $relativePath = $databaseFolder . '/' . $filename;
        $path = $folderPath . DIRECTORY_SEPARATOR . $filename;
        $temporaryPath = $path . '.tmp';
        $manifestPath = $path . '.json';
        $handle = fopen($temporaryPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Cannot create backup file');
        }

        $hashContext = hash_init('sha256');
        $stats = [
            'tables' => 0,
            'views' => 0,
            'triggers' => 0,
            'procedures' => 0,
            'functions' => 0,
            'events' => 0,
            'foreign_keys' => 0,
            'auto_increment_tables' => 0,
            'myisam_lock_sections' => 0,
            'rows' => 0,
        ];

        try {
            $this->writeHeader($handle, $hashContext, $database);
            $objects = $this->listObjects($connection);
            $tables = array_values(array_filter(
                $objects,
                static fn (array $object): bool => $object['type'] !== 'VIEW'
            ));
            $views = array_values(array_filter(
                $objects,
                static fn (array $object): bool => $object['type'] === 'VIEW'
            ));
            $foreignKeys = $this->listForeignKeys($connection);
            $tableMetadata = $this->listTableMetadata($connection);
            $tables = $this->sortTablesByForeignKeyDependency($tables, $foreignKeys);

            $this->writeViewDrops($handle, $hashContext, $views);

            foreach ($tables as $object) {
                $metadata = $tableMetadata[$object['name']] ?? ['engine' => '', 'auto_increment' => null];
                $stats['rows'] += $this->writeTable($connection, $handle, $hashContext, $object['name'], $metadata);
                $stats['tables']++;

                if (strtoupper((string) $metadata['engine']) === 'MYISAM') {
                    $stats['myisam_lock_sections']++;
                }
            }

            $stats['auto_increment_tables'] = $this->writeAutoIncrementValues($handle, $hashContext, $tableMetadata);
            $stats['foreign_keys'] = $this->writeForeignKeys($handle, $hashContext, $foreignKeys);
            $stats['triggers'] = $this->writeTriggers($connection, $handle, $hashContext);
            $this->writeViewPlaceholders($connection, $handle, $hashContext, $views);

            foreach ($views as $object) {
                $this->writeView($connection, $handle, $hashContext, $object['name']);
                $stats['views']++;
            }

            $routineStats = $this->writeRoutines($connection, $handle, $hashContext, $database);
            $stats['procedures'] = $routineStats['procedures'];
            $stats['functions'] = $routineStats['functions'];
            $stats['events'] = $this->writeEvents($connection, $handle, $hashContext, $database);

            $this->writeFooter($handle, $hashContext);
        } finally {
            fclose($handle);
        }

        if (!$this->fileHasCompletionMarker($temporaryPath)) {
            @unlink($temporaryPath);
            throw new RuntimeException('Backup file was not completed');
        }

        if (!rename($temporaryPath, $path)) {
            @unlink($temporaryPath);
            throw new RuntimeException('Cannot finalize backup file');
        }

        @chmod($path, 0640);

        $sha256 = hash_final($hashContext);
        $bytes = filesize($path) ?: 0;
        $manifest = [
            'database' => $database,
            'filename' => $filename,
            'relative_path' => $relativePath,
            'created_at' => date('c'),
            'bytes' => $bytes,
            'sha256' => $sha256,
            'complete' => true,
            'stats' => $stats,
            'restore_order' => [
                'session_setup',
                'drop_views',
                'tables_without_foreign_keys',
                'data_in_foreign_key_dependency_order',
                'auto_increment_values_after_data',
                'foreign_keys_after_all_tables_exist',
                'triggers',
                'view_placeholders',
                'views',
                'routines',
                'events',
                'session_restore',
            ],
        ];

        $encodedManifest = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($encodedManifest === false || file_put_contents($manifestPath, $encodedManifest . "\n", LOCK_EX) === false) {
            throw new RuntimeException('Cannot create backup manifest');
        }

        @chmod($manifestPath, 0640);

        return [
            'database' => $database,
            'filename' => $filename,
            'relative_path' => $relativePath,
            'bytes' => $bytes,
            'sha256' => $sha256,
            'manifest_path' => $databaseFolder . '/' . basename($manifestPath),
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

    /**
     * @return array<int, array{name: string, type: string}>
     */
    private function listObjects(mysqli $connection): array
    {
        $result = $connection->query('SHOW FULL TABLES');
        $objects = [];

        while ($row = $result->fetch_row()) {
            $objects[] = [
                'name' => (string) $row[0],
                'type' => strtoupper((string) ($row[1] ?? 'BASE TABLE')),
            ];
        }

        usort($objects, static fn (array $a, array $b): int => [$a['type'], $a['name']] <=> [$b['type'], $b['name']]);

        return $objects;
    }

    /**
     * @param resource $handle
     * @param resource $hashContext
     */
    private function writeHeader($handle, $hashContext, string $database): void
    {
        $escapedDatabase = $this->escapeIdentifier($database);

        $this->write($handle, $hashContext, "-- Backup database: `{$database}`\n");
        $this->write($handle, $hashContext, '-- Created at: ' . date('c') . "\n");
        $this->write($handle, $hashContext, "-- Dump order: tables/data, triggers, views, routines, events\n\n");
        $this->write($handle, $hashContext, "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n");
        $this->write($handle, $hashContext, "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n");
        $this->write($handle, $hashContext, "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n");
        $this->write($handle, $hashContext, "/*!40101 SET NAMES utf8mb4 */;\n");
        $this->write($handle, $hashContext, "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;\n");
        $this->write($handle, $hashContext, "/*!40103 SET TIME_ZONE='+00:00' */;\n");
        $this->write($handle, $hashContext, "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n");
        $this->write($handle, $hashContext, "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n");
        $this->write($handle, $hashContext, "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n");
        $this->write($handle, $hashContext, "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n");
        $this->write($handle, $hashContext, "CREATE DATABASE IF NOT EXISTS `{$escapedDatabase}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n");
        $this->write($handle, $hashContext, "USE `{$escapedDatabase}`;\n\n");
    }

    /**
     * @param resource $handle
     * @param resource $hashContext
     * @param array{engine: string, auto_increment: int|null} $metadata
     */
    private function writeTable(mysqli $connection, $handle, $hashContext, string $table, array $metadata): int
    {
        $escapedTable = $this->escapeIdentifier($table);
        $isMyisam = strtoupper($metadata['engine']) === 'MYISAM';
        $createResult = $connection->query('SHOW CREATE TABLE `' . $escapedTable . '`');
        $createRow = $createResult->fetch_assoc();
        $createSql = (string) ($createRow['Create Table'] ?? '');
        $createSql = $this->removeForeignKeysFromCreateSql($createSql);

        $this->write($handle, $hashContext, "\n-- --------------------------------------------------------\n");
        $this->write($handle, $hashContext, "-- Table structure for `{$table}`\n\n");
        $this->write($handle, $hashContext, "DROP TABLE IF EXISTS `{$escapedTable}`;\n");
        $this->write($handle, $hashContext, $createSql . ";\n\n");

        if ($isMyisam) {
            $this->write($handle, $hashContext, "LOCK TABLES `{$escapedTable}` WRITE;\n");
            $this->write($handle, $hashContext, "ALTER TABLE `{$escapedTable}` DISABLE KEYS;\n");
        }

        $rows = $connection->query('SELECT * FROM `' . $escapedTable . '`', MYSQLI_USE_RESULT);
        $fields = $rows->fetch_fields();
        $columnNames = array_map(static fn ($field): string => '`' . str_replace('`', '``', $field->name) . '`', $fields);
        $prefix = 'INSERT INTO `' . $escapedTable . '` (' . implode(', ', $columnNames) . ') VALUES ';
        $batch = [];
        $rowCount = 0;

        while ($row = $rows->fetch_assoc()) {
            $values = [];

            foreach ($fields as $field) {
                $value = $row[$field->name];
                $values[] = $value === null ? 'NULL' : "'" . $connection->real_escape_string((string) $value) . "'";
            }

            $batch[] = '(' . implode(', ', $values) . ')';
            $rowCount++;

            if (count($batch) >= self::INSERT_BATCH_LIMIT) {
                $this->write($handle, $hashContext, $prefix . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->write($handle, $hashContext, $prefix . implode(",\n", $batch) . ";\n");
        }

        $rows->free();

        if ($isMyisam) {
            $this->write($handle, $hashContext, "ALTER TABLE `{$escapedTable}` ENABLE KEYS;\n");
            $this->write($handle, $hashContext, "UNLOCK TABLES;\n");
        }

        return $rowCount;
    }

    /**
     * @return array<string, array{engine: string, auto_increment: int|null}>
     */
    private function listTableMetadata(mysqli $connection): array
    {
        $sql = <<<'SQL'
SELECT TABLE_NAME, ENGINE, AUTO_INCREMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_NAME
SQL;
        $result = $connection->query($sql);
        $metadata = [];

        while ($row = $result->fetch_assoc()) {
            $autoIncrement = $row['AUTO_INCREMENT'] === null ? null : (int) $row['AUTO_INCREMENT'];
            $metadata[(string) $row['TABLE_NAME']] = [
                'engine' => (string) ($row['ENGINE'] ?? ''),
                'auto_increment' => $autoIncrement,
            ];
        }

        return $metadata;
    }

    /**
     * @param resource $handle
     * @param resource $hashContext
     * @param array<string, array{engine: string, auto_increment: int|null}> $tableMetadata
     */
    private function writeAutoIncrementValues($handle, $hashContext, array $tableMetadata): int
    {
        $autoIncrementTables = array_filter(
            $tableMetadata,
            static fn (array $metadata): bool => $metadata['auto_increment'] !== null && $metadata['auto_increment'] > 1
        );

        if ($autoIncrementTables === []) {
            return 0;
        }

        ksort($autoIncrementTables, SORT_NATURAL | SORT_FLAG_CASE);
        $this->write($handle, $hashContext, "\n-- --------------------------------------------------------\n");
        $this->write($handle, $hashContext, "-- AUTO_INCREMENT values restored after data load\n\n");

        foreach ($autoIncrementTables as $table => $metadata) {
            $this->write(
                $handle,
                $hashContext,
                'ALTER TABLE `' . $this->escapeIdentifier((string) $table) . '` AUTO_INCREMENT = ' . (int) $metadata['auto_increment'] . ";\n"
            );
        }

        return count($autoIncrementTables);
    }

    /**
     * @return array<int, array{name: string, table: string, referenced_table: string, columns: array<int, string>, referenced_columns: array<int, string>, update_rule: string, delete_rule: string}>
     */
    private function listForeignKeys(mysqli $connection): array
    {
        $sql = <<<'SQL'
SELECT
    rc.CONSTRAINT_NAME,
    rc.TABLE_NAME,
    rc.REFERENCED_TABLE_NAME,
    rc.UPDATE_RULE,
    rc.DELETE_RULE,
    kcu.COLUMN_NAME,
    kcu.REFERENCED_COLUMN_NAME,
    kcu.ORDINAL_POSITION
FROM information_schema.REFERENTIAL_CONSTRAINTS rc
INNER JOIN information_schema.KEY_COLUMN_USAGE kcu
    ON kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
    AND kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
    AND kcu.TABLE_NAME = rc.TABLE_NAME
WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
ORDER BY rc.TABLE_NAME, rc.CONSTRAINT_NAME, kcu.ORDINAL_POSITION
SQL;
        $result = $connection->query($sql);
        $foreignKeys = [];

        while ($row = $result->fetch_assoc()) {
            $key = (string) $row['TABLE_NAME'] . "\0" . (string) $row['CONSTRAINT_NAME'];

            if (!isset($foreignKeys[$key])) {
                $foreignKeys[$key] = [
                    'name' => (string) $row['CONSTRAINT_NAME'],
                    'table' => (string) $row['TABLE_NAME'],
                    'referenced_table' => (string) $row['REFERENCED_TABLE_NAME'],
                    'columns' => [],
                    'referenced_columns' => [],
                    'update_rule' => (string) $row['UPDATE_RULE'],
                    'delete_rule' => (string) $row['DELETE_RULE'],
                ];
            }

            $foreignKeys[$key]['columns'][] = (string) $row['COLUMN_NAME'];
            $foreignKeys[$key]['referenced_columns'][] = (string) $row['REFERENCED_COLUMN_NAME'];
        }

        return array_values($foreignKeys);
    }

    /**
     * @param array<int, array{name: string, type: string}> $tables
     * @param array<int, array{name: string, table: string, referenced_table: string, columns: array<int, string>, referenced_columns: array<int, string>, update_rule: string, delete_rule: string}> $foreignKeys
     * @return array<int, array{name: string, type: string}>
     */
    private function sortTablesByForeignKeyDependency(array $tables, array $foreignKeys): array
    {
        $tableByName = [];
        $dependencies = [];
        $dependents = [];

        foreach ($tables as $table) {
            $tableByName[$table['name']] = $table;
            $dependencies[$table['name']] = [];
            $dependents[$table['name']] = [];
        }

        foreach ($foreignKeys as $foreignKey) {
            $table = $foreignKey['table'];
            $referencedTable = $foreignKey['referenced_table'];

            if (
                $table === $referencedTable
                || !isset($dependencies[$table])
                || !isset($dependencies[$referencedTable])
            ) {
                continue;
            }

            $dependencies[$table][$referencedTable] = true;
            $dependents[$referencedTable][$table] = true;
        }

        $ready = [];

        foreach ($dependencies as $table => $items) {
            if ($items === []) {
                $ready[] = $table;
            }
        }

        sort($ready, SORT_NATURAL | SORT_FLAG_CASE);
        $sorted = [];

        while ($ready !== []) {
            $table = array_shift($ready);

            if (!isset($tableByName[$table])) {
                continue;
            }

            $sorted[] = $tableByName[$table];
            unset($tableByName[$table]);

            foreach (array_keys($dependents[$table] ?? []) as $dependent) {
                unset($dependencies[$dependent][$table]);

                if (($dependencies[$dependent] ?? []) === []) {
                    $ready[] = $dependent;
                    sort($ready, SORT_NATURAL | SORT_FLAG_CASE);
                }
            }
        }

        if ($tableByName !== []) {
            ksort($tableByName, SORT_NATURAL | SORT_FLAG_CASE);
            $sorted = array_merge($sorted, array_values($tableByName));
        }

        return $sorted;
    }

    /**
     * @param resource $handle
     * @param resource $hashContext
     * @param array<int, array{name: string, table: string, referenced_table: string, columns: array<int, string>, referenced_columns: array<int, string>, update_rule: string, delete_rule: string}> $foreignKeys
     */
    private function writeForeignKeys($handle, $hashContext, array $foreignKeys): int
    {
        if ($foreignKeys === []) {
            return 0;
        }

        $this->write($handle, $hashContext, "\n-- --------------------------------------------------------\n");
        $this->write($handle, $hashContext, "-- Foreign keys added after every table exists\n\n");

        foreach ($foreignKeys as $foreignKey) {
            $columns = implode(', ', array_map(fn (string $column): string => '`' . $this->escapeIdentifier($column) . '`', $foreignKey['columns']));
            $referencedColumns = implode(', ', array_map(fn (string $column): string => '`' . $this->escapeIdentifier($column) . '`', $foreignKey['referenced_columns']));
            $sql = 'ALTER TABLE `' . $this->escapeIdentifier($foreignKey['table']) . '` ADD CONSTRAINT `'
                . $this->escapeIdentifier($foreignKey['name']) . '` FOREIGN KEY (' . $columns . ') REFERENCES `'
                . $this->escapeIdentifier($foreignKey['referenced_table']) . '` (' . $referencedColumns . ')';

            if ($foreignKey['delete_rule'] !== '' && $foreignKey['delete_rule'] !== 'RESTRICT') {
                $sql .= ' ON DELETE ' . $foreignKey['delete_rule'];
            }

            if ($foreignKey['update_rule'] !== '' && $foreignKey['update_rule'] !== 'RESTRICT') {
                $sql .= ' ON UPDATE ' . $foreignKey['update_rule'];
            }

            $this->write($handle, $hashContext, $sql . ";\n");
        }

        return count($foreignKeys);
    }

    private function removeForeignKeysFromCreateSql(string $sql): string
    {
        $lines = preg_split('/\R/', rtrim($sql, ";\r\n"));

        if ($lines === false || count($lines) < 3) {
            return $sql;
        }

        $kept = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*(CONSTRAINT\s+`?[^` ]+`?\s+)?FOREIGN\s+KEY\b/i', $line) === 1) {
                continue;
            }

            $kept[] = $line;
        }

        for ($index = count($kept) - 1; $index >= 0; $index--) {
            if (trim($kept[$index]) === '') {
                continue;
            }

            if (preg_match('/^\s*\)/', $kept[$index]) === 1) {
                for ($previous = $index - 1; $previous >= 0; $previous--) {
                    if (trim($kept[$previous]) === '') {
                        continue;
                    }

                    $kept[$previous] = rtrim($kept[$previous], ',');
                    break 2;
                }
            }

            break;
        }

        return implode("\n", $kept);
    }

    /**
     * @param resource $handle
     * @param resource $hashContext
     */
    private function writeView(mysqli $connection, $handle, $hashContext, string $view): void
    {
        $escapedView = $this->escapeIdentifier($view);
        $createResult = $connection->query('SHOW CREATE VIEW `' . $escapedView . '`');
        $createRow = $createResult->fetch_assoc();
        $createSql = (string) ($createRow['Create View'] ?? '');
        $createSql = $this->stripDefiner($createSql);

        $this->write($handle, $hashContext, "\n-- --------------------------------------------------------\n");
        $this->write($handle, $hashContext, "-- View structure for `{$view}`\n\n");
        $this->write($handle, $hashContext, $this->makeCreateOrReplace($createSql) . ";\n\n");
    }

    /**
     * @param resource $handle
     * @param resource $hashContext
     * @param array<int, array{name: string, type: string}> $views
     */
    private function writeViewDrops($handle, $hashContext, array $views): void
    {
        if ($views === []) {
            return;
        }

        $this->write($handle, $hashContext, "\n-- --------------------------------------------------------\n");
        $this->write($handle, $hashContext, "-- Drop views before tables so table names are reusable\n\n");

        foreach ($views as $view) {
            $this->write($handle, $hashContext, 'DROP VIEW IF EXISTS `' . $this->escapeIdentifier($view['name']) . "`;\n");
        }
    }

    /**
     * @param resource $handle
     * @param resource $hashContext
     * @param array<int, array{name: string, type: string}> $views
     */
    private function writeViewPlaceholders(mysqli $connection, $handle, $hashContext, array $views): void
    {
        if ($views === []) {
            return;
        }

        $this->write($handle, $hashContext, "\n-- --------------------------------------------------------\n");
        $this->write($handle, $hashContext, "-- Temporary view placeholders for dependency-safe restore\n\n");

        foreach ($views as $view) {
            $escapedView = $this->escapeIdentifier($view['name']);
            $columns = $this->listColumns($connection, $view['name']);
            $selects = [];

            foreach ($columns as $column) {
                $selects[] = '1 AS `' . $this->escapeIdentifier($column) . '`';
            }

            if ($selects === []) {
                $selects[] = '1 AS `backup_placeholder`';
            }

            $this->write($handle, $hashContext, "CREATE OR REPLACE VIEW `{$escapedView}` AS SELECT " . implode(', ', $selects) . ";\n");
        }
    }

    /**
     * @return array<int, string>
     */
    private function listColumns(mysqli $connection, string $object): array
    {
        $escapedObject = $this->escapeIdentifier($object);
        $result = $connection->query('SHOW COLUMNS FROM `' . $escapedObject . '`');
        $columns = [];

        while ($row = $result->fetch_assoc()) {
            $columns[] = (string) $row['Field'];
        }

        return $columns;
    }

    /**
     * @param resource $handle
     * @param resource $hashContext
     */
    private function writeTriggers(mysqli $connection, $handle, $hashContext): int
    {
        $result = $connection->query('SHOW TRIGGERS');
        $count = 0;

        while ($row = $result->fetch_assoc()) {
            $trigger = (string) ($row['Trigger'] ?? '');

            if ($trigger === '') {
                continue;
            }

            $escapedTrigger = $this->escapeIdentifier($trigger);
            $createResult = $connection->query('SHOW CREATE TRIGGER `' . $escapedTrigger . '`');
            $createRow = $createResult->fetch_assoc();
            $createSql = (string) ($createRow['SQL Original Statement'] ?? $createRow['Create Trigger'] ?? '');
            $createSql = $this->stripDefiner($createSql);

            if ($createSql === '') {
                continue;
            }

            $this->write($handle, $hashContext, "\n-- Trigger `{$trigger}`\n");
            $this->write($handle, $hashContext, "DROP TRIGGER IF EXISTS `{$escapedTrigger}`;\n");
            $this->writeDelimitedStatement($handle, $hashContext, $createSql);
            $count++;
        }

        return $count;
    }

    /**
     * @param resource $handle
     * @param resource $hashContext
     * @return array{procedures: int, functions: int}
     */
    private function writeRoutines(mysqli $connection, $handle, $hashContext, string $database): array
    {
        $stats = ['procedures' => 0, 'functions' => 0];

        foreach (['PROCEDURE' => 'procedures', 'FUNCTION' => 'functions'] as $type => $statKey) {
            $escapedDatabase = $connection->real_escape_string($database);
            $result = $connection->query("SHOW {$type} STATUS WHERE Db = '{$escapedDatabase}'");

            while ($row = $result->fetch_assoc()) {
                $name = (string) ($row['Name'] ?? '');

                if ($name === '') {
                    continue;
                }

                $escapedName = $this->escapeIdentifier($name);
                $createResult = $connection->query("SHOW CREATE {$type} `{$escapedName}`");
                $createRow = $createResult->fetch_assoc();
                $createSql = (string) ($createRow['Create ' . ucfirst(strtolower($type))] ?? '');
                $createSql = $this->stripDefiner($createSql);

                if ($createSql === '') {
                    continue;
                }

                $this->write($handle, $hashContext, "\n-- {$type} `{$name}`\n");
                $this->write($handle, $hashContext, "DROP {$type} IF EXISTS `{$escapedName}`;\n");
                $this->writeDelimitedStatement($handle, $hashContext, $createSql);
                $stats[$statKey]++;
            }
        }

        return $stats;
    }

    /**
     * @param resource $handle
     * @param resource $hashContext
     */
    private function writeEvents(mysqli $connection, $handle, $hashContext, string $database): int
    {
        $escapedDatabase = $connection->real_escape_string($database);
        $result = $connection->query("SHOW EVENTS WHERE Db = '{$escapedDatabase}'");
        $count = 0;

        while ($row = $result->fetch_assoc()) {
            $name = (string) ($row['Name'] ?? '');

            if ($name === '') {
                continue;
            }

            $escapedName = $this->escapeIdentifier($name);
            $createResult = $connection->query('SHOW CREATE EVENT `' . $escapedName . '`');
            $createRow = $createResult->fetch_assoc();
            $createSql = (string) ($createRow['Create Event'] ?? '');
            $createSql = $this->stripDefiner($createSql);

            if ($createSql === '') {
                continue;
            }

            $this->write($handle, $hashContext, "\n-- Event `{$name}`\n");
            $this->write($handle, $hashContext, "DROP EVENT IF EXISTS `{$escapedName}`;\n");
            $this->writeDelimitedStatement($handle, $hashContext, $createSql);
            $count++;
        }

        return $count;
    }

    /**
     * @param resource $handle
     * @param resource $hashContext
     */
    private function writeFooter($handle, $hashContext): void
    {
        $this->write($handle, $hashContext, "\n/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n");
        $this->write($handle, $hashContext, "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n");
        $this->write($handle, $hashContext, "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n");
        $this->write($handle, $hashContext, "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n");
        $this->write($handle, $hashContext, "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n");
        $this->write($handle, $hashContext, "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n");
        $this->write($handle, $hashContext, "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;\n");
        $this->write($handle, $hashContext, "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;\n");
        $this->write($handle, $hashContext, self::COMPLETION_MARKER . "\n");
    }

    /**
     * @param resource $handle
     * @param resource $hashContext
     */
    private function writeDelimitedStatement($handle, $hashContext, string $sql): void
    {
        $this->write($handle, $hashContext, "DELIMITER $$\n");
        $this->write($handle, $hashContext, rtrim($sql, ";\r\n") . "$$\n");
        $this->write($handle, $hashContext, "DELIMITER ;\n");
    }

    /**
     * @param resource $handle
     * @param resource $hashContext
     */
    private function write($handle, $hashContext, string $contents): void
    {
        if (fwrite($handle, $contents) === false) {
            throw new RuntimeException('Cannot write backup file');
        }

        hash_update($hashContext, $contents);
    }

    private function fileHasCompletionMarker(string $path): bool
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $size = filesize($path) ?: 0;
        $offset = max(0, $size - 4096);
        fseek($handle, $offset);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return $contents !== false && str_contains($contents, self::COMPLETION_MARKER);
    }

    private function makeCreateOrReplace(string $sql): string
    {
        $sql = rtrim($sql, ";\r\n");
        $updated = preg_replace('/^CREATE\s+/i', 'CREATE OR REPLACE ', $sql, 1);

        return $updated ?? $sql;
    }

    private function stripDefiner(string $sql): string
    {
        $updated = preg_replace('/^CREATE\s+DEFINER=`[^`]+`@`[^`]+`\s+/i', 'CREATE ', $sql, 1);

        if ($updated !== null && $updated !== $sql) {
            return $updated;
        }

        $updated = preg_replace('/^CREATE\s+DEFINER=[^\s]+\s+/i', 'CREATE ', $sql, 1);

        return $updated ?? $sql;
    }

    private function escapeIdentifier(string $identifier): string
    {
        return str_replace('`', '``', $identifier);
    }
}
