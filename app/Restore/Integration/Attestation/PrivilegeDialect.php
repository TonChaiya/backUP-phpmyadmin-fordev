<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Attestation;

interface PrivilegeDialect
{
    /** @return array{privileges: list<string>, scope: string}|null */
    public function parseDatabaseGrant(string $line): ?array;
}
