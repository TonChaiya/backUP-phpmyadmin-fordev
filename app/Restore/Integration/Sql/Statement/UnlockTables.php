<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Sql\Statement;

use BackupApp\Restore\Integration\Sql\AbstractExecutableStatement;
use BackupApp\Restore\Integration\Sql\RestoreStatementKind;

final class UnlockTables extends AbstractExecutableStatement
{
    public function kind(): RestoreStatementKind { return RestoreStatementKind::UNLOCK_TABLES; }
}
