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
        '--no-login-paths', '--protocol=TCP', '--single-transaction', '--quick', '--skip-lock-tables',
        '--no-tablespaces', '--set-gtid-purged=OFF', '--default-character-set=utf8mb4',
        '--skip-triggers', '--skip-events', '--skip-routines', '--hex-blob', '--tz-utc',
        '--column-statistics=0', '--skip-add-drop-table', '--skip-add-locks',
        '--skip-disable-keys', '--comments', '--dump-date',
    ];

    /** @return list<string> */
    public function arguments(string $database, string $credentialFile,array $selected=[]): array
    {
        (new AuthorizedSourcePolicy())->assertSelected($database,$selected);
        if (preg_match('/[\x00-\x1f]/', $credentialFile) === 1 || $credentialFile === '') {
            throw new RuntimeException('mysqldump_credential_path_invalid');
        }
        return [self::BINARY, '--defaults-file=' . $credentialFile, ...self::OPTIONS, '--', $database];
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
        if (!in_array($connection['host'] ?? null, ['127.0.0.1','localhost','::1'], true)
            || !is_int($connection['port'] ?? null) || $connection['port'] < 1 || $connection['port'] > 65535 || $connection['port'] === 3333
            || !is_string($connection['username'] ?? null) || preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.-]{0,31}$/D', $connection['username']) !== 1) {
            throw new RuntimeException('connection_settings_invalid');
        }
    }

    /** @param array<string,mixed> $identity */
    public static function assertServer(array $identity, array $expected = []): void
    {
        if (($identity['version'] ?? '') !== self::VERSION
            || !is_string($identity['server_uuid'] ?? null) || preg_match('/^[a-f0-9-]{36}$/iD', $identity['server_uuid']) !== 1
            || (int) ($identity['port'] ?? 0) < 1 || (int) ($identity['port'] ?? 0) > 65535 || (int) ($identity['port'] ?? 0) === 3333
            || !is_string($identity['account'] ?? null) || !str_contains($identity['account'], '@')
            || ($identity['roles'] ?? '') !== 'NONE') {
            throw new RuntimeException('mysqldump_server_or_account_identity_mismatch');
        }
        foreach (['server_uuid','port','account'] as $key) {
            if (isset($expected[$key]) && (string) $identity[$key] !== (string) $expected[$key]) { throw new RuntimeException('connection_server_identity_changed'); }
        }
        if (isset($expected['username']) && explode('@', $identity['account'], 2)[0] !== $expected['username']) { throw new RuntimeException('connection_authenticated_account_mismatch'); }
    }

    /** @return array<string,mixed> */
    public static function verifyConnection(mysqli $connection, array $expected = []): array
    {
        $identity = $connection->query(
            'SELECT CURRENT_USER() AS account, CURRENT_ROLE() AS roles, @@version AS version, @@port AS port, @@server_uuid AS server_uuid, @@global.partial_revokes AS partial_revokes'
        )->fetch_assoc();
        self::assertServer(is_array($identity) ? $identity : [], $expected);
        return $identity;
    }
}
