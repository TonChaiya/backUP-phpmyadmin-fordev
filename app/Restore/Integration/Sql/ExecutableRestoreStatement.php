<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Sql;

interface ExecutableRestoreStatement
{
    public function kind(): RestoreStatementKind;
    public function renderForTarget(): string;
}
