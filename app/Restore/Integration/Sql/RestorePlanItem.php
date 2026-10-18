<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Sql;

interface RestorePlanItem
{
    public function category(): string;
}
