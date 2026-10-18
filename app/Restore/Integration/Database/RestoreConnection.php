<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Database;

use BackupApp\Restore\Integration\Ownership\OwnershipMarker;
use BackupApp\Restore\Integration\Policy\TrustedTargetName;
use BackupApp\Restore\Integration\Sql\ValidatedExecutableStatement;

interface RestoreConnection
{
    public function serverIdentityFingerprint(): string;
    public function targetExists(TrustedTargetName $target): bool;
    public function createTarget(TrustedTargetName $target, string $charset, string $collation): void;
    public function selectTarget(TrustedTargetName $target): void;
    public function currentDatabase(): ?string;
    public function execute(ValidatedExecutableStatement $statement): void;
    public function createOwnershipMarker(OwnershipMarker $marker): void;
    public function readOwnershipMarkerHash(OwnershipMarker $marker): ?string;
    public function dropExactTarget(TrustedTargetName $target): void;
    /** @return array<string, mixed> */ public function schemaSnapshot(): array;
    /** @param list<string> $columns @return iterable<list<array<string, mixed>>> */ public function rowBatches(string $table, array $columns, int $batchSize): iterable;
    public function close(): void;
}
