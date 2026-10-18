<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Sql;

final class RestorePlan
{
    /** @param list<RestorePlanItem> $items @param array<string, list<string>> $objectInventory @param array<string, \BackupApp\Restore\Integration\Validation\TableDataEvidence> $dataEvidence @param array<string, mixed> $schemaEvidence */
    public function __construct(
        private readonly string $sourceDatabase,
        private readonly array $items,
        private readonly int $contentBytes,
        private readonly string $contentSha256,
        private readonly array $objectInventory,
        private readonly array $dataEvidence = [],
        private readonly array $schemaEvidence = [],
        private readonly string $policyVersion = 'restore-integration-v1'
    ) {
    }

    public function sourceDatabase(): string { return $this->sourceDatabase; }
    /** @return list<RestorePlanItem> */
    public function items(): array { return $this->items; }
    public function contentBytes(): int { return $this->contentBytes; }
    public function contentSha256(): string { return $this->contentSha256; }
    /** @return array<string, list<string>> */
    public function objectInventory(): array { return $this->objectInventory; }
    /** @return array<string, \BackupApp\Restore\Integration\Validation\TableDataEvidence> */
    public function dataEvidence(): array { return $this->dataEvidence; }
    /** @return array<string, mixed> */
    public function schemaEvidence(): array { return $this->schemaEvidence; }
    public function policyVersion(): string { return $this->policyVersion; }
}
