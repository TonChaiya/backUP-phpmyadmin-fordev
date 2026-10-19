<?php

declare(strict_types=1);

namespace BackupApp\Database;

use DomainException;

final class AuthorizedSourcePolicy
{
    private const SOURCES = [
        'autoprintdb',
        'drugmap_db',
        'drug_stock_check',
        'prepack_labels',
    ];

    /** @return list<string> */
    public function authorizedSources(): array
    {
        return self::SOURCES;
    }

    public function isAuthorized(string $database): bool
    {
        return in_array($database, self::SOURCES, true);
    }

    public function assertAuthorized(string $database): void
    {
        if (!$this->isAuthorized($database)) {
            throw new DomainException('source_not_authorized');
        }
    }

    /** @param array<int, mixed> $databases @return list<string> */
    public function filterAuthorized(array $databases): array
    {
        $result = [];

        foreach ($databases as $database) {
            if (is_string($database) && $this->isAuthorized($database) && !in_array($database, $result, true)) {
                $result[] = $database;
            }
        }

        return $result;
    }
}
