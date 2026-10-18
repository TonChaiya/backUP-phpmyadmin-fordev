<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Database;

use DomainException;

final class RestoreConnectionFactory
{
    /** @param array<string, mixed> $backupCredentials @param array<string, mixed> $runnerCredentials */
    public function assertSeparatedRoles(array $backupCredentials, array $runnerCredentials): void
    {
        $reader = strtolower(trim((string) ($backupCredentials['username'] ?? '')));
        $runner = strtolower(trim((string) ($runnerCredentials['username'] ?? '')));
        if ($reader === '' || $runner === '' || $reader === $runner) { throw new DomainException('credential_roles_not_separated'); }
        foreach ([$reader, $runner] as $account) {
            if ($account === 'root' || str_starts_with($account, 'root@')) { throw new DomainException('root_account_forbidden'); }
        }
        if ((string) ($backupCredentials['password'] ?? '') === '' || (string) ($runnerCredentials['password'] ?? '') === '') {
            throw new DomainException('credential_missing');
        }
    }

    /** @param array<string, mixed> $sessionCredentials */
    public function makeRunner(array $sessionCredentials): RestoreConnection
    {
        $account = strtolower((string) ($sessionCredentials['username'] ?? ''));
        if ($account === 'root' || str_starts_with($account, 'root@')) { throw new DomainException('root_account_forbidden'); }
        throw new DomainException('live_restore_execution_disabled');
    }
}
