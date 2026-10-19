<?php

declare(strict_types=1);

namespace BackupApp\Database;

use DomainException;

final class AuthorizedSourcePolicy
{
    private const SYSTEM = ['information_schema','performance_schema','mysql','sys'];

    /** @return list<string> */
    public function systemSchemas(): array
    {
        return self::SYSTEM;
    }

    public function isProtected(string $database):bool {return strtolower($database)==='jhcisdb';}
    public function isSystem(string $database):bool {return in_array(strtolower($database),self::SYSTEM,true);}

    public function isAuthorized(string $database): bool
    {
        // Eligibility only, not evidence of account access. Discovery and execution must verify visibility.
        return !$this->isProtected($database) && !$this->isSystem($database)
            && preg_match('/^[^\x00-\x1f\x7f\/\\\\:*?"<>|]{1,64}$/uD',$database)===1
            && !str_contains($database,'..') && !in_array($database,['.','..'],true)
            && preg_match('/[ .]$/uD',$database)!==1
            && preg_match('/^(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/iD',$database)!==1;
    }

    public function assertAuthorized(string $database): void
    {
        if (!$this->isAuthorized($database)) {
            throw new DomainException('source_not_authorized');
        }
    }

    public function assertSelected(string $database,array $selected):void
    {
        $this->assertAuthorized($database);
        if(!in_array($database,$selected,true)){throw new DomainException('source_not_in_selected_scope');}
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
