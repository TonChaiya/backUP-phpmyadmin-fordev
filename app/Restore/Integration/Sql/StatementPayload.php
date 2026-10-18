<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Sql;

interface StatementPayload
{
    public function read(): string;
}
