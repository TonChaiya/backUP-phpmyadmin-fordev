<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Database;

interface MetadataConnection
{
    /** @param list<string> $exactNames @return list<string> */
    public function authorizedSchemas(array $exactNames): array;
    /** @return array{product?: string, version?: string, stable_id?: string, endpoint?: string} */
    public function serverIdentityFacts(): array;
    public function currentAccount(): string;
    /** @return list<string> */
    public function grantLines(): array;
    /** @return array<string, mixed> */
    public function privilegeContextFacts(): array;
    public function close(): void;
}
