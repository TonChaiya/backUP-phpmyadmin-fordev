<?php

declare(strict_types=1);

namespace BackupApp\Backup;

use BackupApp\Database\AuthorizedSourcePolicy;
use RuntimeException;
use mysqli;

final class MysqldumpCommand
{
    public const BINARY = 'C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysqldump.exe';
    public const SHA256 = '25d8eab0985f55864d5007574ec275b79fdf761cf53c3a0a766ec4f2bdd10acc';
    public const VERSION = '8.4.3';
    public const SERVER_UUID = '198f474e-899b-11f1-903d-f0761cf4041a';
    public const PROFILE = 'innodb-tables-v1';
    public const OPTIONS = [
        '--no-login-paths', '--protocol=TCP', '--host=127.0.0.1', '--port=3306',
        '--user=backup_reader', '--single-transaction', '--quick', '--skip-lock-tables',
        '--no-tablespaces', '--set-gtid-purged=OFF', '--default-character-set=utf8mb4',
        '--skip-triggers', '--skip-events', '--skip-routines', '--hex-blob', '--tz-utc',
        '--column-statistics=0', '--skip-add-drop-table', '--skip-add-locks',
        '--skip-disable-keys', '--comments', '--dump-date',
    ];

    /** @return list<string> */
    public function arguments(string $database, string $credentialFile): array
    {
        (new AuthorizedSourcePolicy())->assertAuthorized($database);
        if (preg_match('/[\x00-\x1f]/', $credentialFile) === 1 || $credentialFile === '') {
            throw new RuntimeException('mysqldump_credential_path_invalid');
        }
        return [self::BINARY, '--defaults-file=' . $credentialFile, ...self::OPTIONS, $database];
    }

    public function verifyBinary(): void
    {
        if (!is_file(self::BINARY) || is_link(self::BINARY)
            || !hash_equals(self::SHA256, (string) hash_file('sha256', self::BINARY))) {
            throw new RuntimeException('mysqldump_binary_identity_mismatch');
        }
        $out = tmpfile(); $err = tmpfile();
        if ($out === false || $err === false) {
            throw new RuntimeException('mysqldump_probe_storage_failed');
        }
        try {
            $exit = (new MysqldumpProcess())->run([self::BINARY, '--no-defaults', '--no-login-paths', '--version'], $out, $err, 10, 4096, 4096);
            rewind($out);
            $version = stream_get_contents($out, 4096);
            if ($exit !== 0 || !is_string($version)
                || !str_contains($version, 'Ver 8.4.3 for Win64 on x86_64 (MySQL Community Server - GPL)')) {
                throw new RuntimeException('mysqldump_version_mismatch');
            }
        } finally { fclose($out); fclose($err); }
    }

    /** @param array<string,mixed> $connection */
    public static function assertConnection(array $connection): void
    {
        if (($connection['host'] ?? null) !== '127.0.0.1'
            || ($connection['port'] ?? null) !== 3306
            || ($connection['username'] ?? null) !== 'backup_reader') {
            throw new RuntimeException('Backup requires backup_reader at 127.0.0.1:3306');
        }
    }

    /** @param array<string,mixed> $identity */
    public static function assertServer(array $identity): void
    {
        if (($identity['version'] ?? '') !== self::VERSION
            || ($identity['server_uuid'] ?? '') !== self::SERVER_UUID
            || (int) ($identity['port'] ?? 0) !== 3306
            || (string) ($identity['partial_revokes'] ?? '') !== '0'
            || ($identity['account'] ?? '') !== 'backup_reader@localhost'
            || ($identity['roles'] ?? '') !== 'NONE') {
            throw new RuntimeException('mysqldump_server_or_account_identity_mismatch');
        }
    }

    /** @param list<string> $grants */
    public static function assertGrants(array $grants): void
    {
        $expected = ['GRANT USAGE ON *.* TO `backup_reader`@`localhost`'];
        foreach (['autoprintdb', 'drugmap\\_db', 'drug\\_stock\\_check', 'prepack\\_labels'] as $database) {
            $expected[] = 'GRANT SELECT ON `' . $database . '`.* TO `backup_reader`@`localhost`';
        }
        sort($expected, SORT_STRING); sort($grants, SORT_STRING);
        if ($expected !== $grants) { throw new RuntimeException('mysqldump_reader_privilege_scope_mismatch'); }
    }

    /** @return array<string,mixed> */
    public static function verifyConnection(mysqli $connection): array
    {
        $identity = $connection->query(
            'SELECT CURRENT_USER() AS account, CURRENT_ROLE() AS roles, @@version AS version, @@port AS port, @@server_uuid AS server_uuid, @@global.partial_revokes AS partial_revokes'
        )->fetch_assoc();
        self::assertServer(is_array($identity) ? $identity : []);
        $result = $connection->query('SHOW GRANTS FOR CURRENT_USER()');
        $grants = [];
        while ($row = $result->fetch_assoc()) { $grants[] = (string) reset($row); }
        self::assertGrants($grants);
        return $identity;
    }
}
