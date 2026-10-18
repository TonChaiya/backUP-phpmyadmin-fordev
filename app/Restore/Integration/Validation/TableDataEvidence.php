<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Validation;

final class TableDataEvidence
{
    /** @param list<string> $columns */
    public function __construct(
        private readonly string $table,
        private readonly array $columns,
        private readonly int $rowCount,
        private readonly string $multisetSha256,
        private readonly string $algorithm = 'sha256-sorted-row-sha256-v1'
    ) {}

    public function table(): string { return $this->table; }
    /** @return list<string> */ public function columns(): array { return $this->columns; }
    public function rowCount(): int { return $this->rowCount; }
    public function multisetSha256(): string { return $this->multisetSha256; }
    public function algorithm(): string { return $this->algorithm; }
}
