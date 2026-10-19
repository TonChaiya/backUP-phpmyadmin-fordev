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
        return $this->catalog($connection)['databases'];
    }

    /** Account-visible schema names only; never enumerate tables or protected schema metadata.
     * @return array{databases:list<string>,system_databases:list<string>,unsupported_databases:list<string>} */
    public function catalog(mysqli $connection):array
    {
        $policy = new AuthorizedSourcePolicy();
        $result = $connection->query(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA'
            . " WHERE LOWER(SCHEMA_NAME) <> 'jhcisdb' ORDER BY SCHEMA_NAME"
        );
        $found = ['databases'=>[],'system_databases'=>[],'unsupported_databases'=>[]];

        while ($row = $result->fetch_row()) {
            $database = (string) $row[0];

            if ($policy->isProtected($database)) {
                throw new DomainException('catalog_scope_violation');
            }

            $key=$policy->isSystem($database)?'system_databases':($policy->isAuthorized($database)?'databases':'unsupported_databases');
            if(!in_array($database,$found[$key],true)){$found[$key][]=$database;}
        }

        return $found;
    }

    public function assertVisible(mysqli $connection,string $database):void
    {
        (new AuthorizedSourcePolicy())->assertAuthorized($database);
        $escaped=$connection->real_escape_string($database);
        $row=$connection->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE LOWER(SCHEMA_NAME) <> 'jhcisdb' AND SCHEMA_NAME = '{$escaped}'")->fetch_assoc();
        if(($row['SCHEMA_NAME']??null)!==$database){throw new DomainException('source_not_visible_to_account');}
    }
}
