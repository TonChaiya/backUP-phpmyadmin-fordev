<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Policy;

use DomainException;

final class RestoreIntegrationPolicy
{
    private const SOURCES = ['autoprintdb', 'drugmap_db', 'drug_stock_check', 'prepack_labels'];
    private const PROTECTED = ['jhcisdb', 'mysql', 'sys', 'information_schema', 'performance_schema'];

    /** @return list<string> */
    public function authorizedSources(): array { return self::SOURCES; }

    public function assertSource(string $name): void
    {
        if (!in_array($name, self::SOURCES, true)) {
            throw new DomainException('source_not_authorized');
        }
    }

    public function targetFor(string $source, TrustedRunId $runId): TrustedTargetName
    {
        $this->assertSource($source);
        return new TrustedTargetName('restore_test_' . $source . '_' . $runId->value());
    }

    public function assertTargetNotProtected(TrustedTargetName $target): void
    {
        if (in_array(strtolower($target->value()), self::PROTECTED, true)) {
            throw new DomainException('protected_target');
        }
    }
}
