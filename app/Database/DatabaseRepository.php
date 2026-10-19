<?php

declare(strict_types=1);

namespace BackupApp\Database;

use DomainException;
use mysqli;

final class DatabaseRepository
{
    /**
     * @return array<int, string>
     */
    public function listDatabases(mysqli $connection): array
    {
        $policy = new AuthorizedSourcePolicy();
        $allowed = $policy->authorizedSources();
        $quoted = array_map(
            static fn (string $database): string => "'" . $database . "'",
            $allowed
        );
        $result = $connection->query(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA'
            . ' WHERE SCHEMA_NAME IN (' . implode(', ', $quoted) . ')'
        );
        $found = [];

        while ($row = $result->fetch_row()) {
            $database = (string) $row[0];

            if (!$policy->isAuthorized($database)) {
                throw new DomainException('catalog_scope_violation');
            }

            $found[] = $database;
        }

        return array_values(array_intersect($allowed, array_unique($found)));
    }
}
