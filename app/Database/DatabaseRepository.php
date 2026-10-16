<?php

declare(strict_types=1);

namespace BackupApp\Database;

use mysqli;

final class DatabaseRepository
{
    /**
     * @param array<int, string> $excludedDatabases
     * @return array<int, string>
     */
    public function listDatabases(mysqli $connection, array $excludedDatabases): array
    {
        $result = $connection->query('SHOW DATABASES');
        $databases = [];

        while ($row = $result->fetch_row()) {
            $database = (string) $row[0];

            if (!in_array($database, $excludedDatabases, true)) {
                $databases[] = $database;
            }
        }

        sort($databases, SORT_NATURAL | SORT_FLAG_CASE);

        return $databases;
    }
}
