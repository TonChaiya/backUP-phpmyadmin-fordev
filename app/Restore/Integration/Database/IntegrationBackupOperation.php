<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Database;

use BackupApp\Backup\BackupOperation;
use BackupApp\Restore\Integration\Policy\RestoreIntegrationPolicy;
use DomainException;

final class IntegrationBackupOperation implements BackupOperation
{
    /** @param list<string> $availableSources */
    public function __construct(
        private readonly BackupOperation $delegate,
        private readonly RestoreIntegrationPolicy $policy,
        private readonly array $availableSources
    ) {
        foreach ($availableSources as $source) { $this->policy->assertSource($source); }
    }
    public function start(): void { $this->delegate->start(); }
    public function backup(string $database): array
    {
        $this->policy->assertSource($database);
        if (!in_array($database, $this->availableSources, true)) { throw new DomainException('source_not_available_in_exact_catalog'); }
        return $this->delegate->backup($database);
    }
    public function finish(): void { $this->delegate->finish(); }
}
