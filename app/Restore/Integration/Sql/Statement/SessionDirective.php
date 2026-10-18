<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Sql\Statement;

use BackupApp\Restore\Integration\Sql\AbstractExecutableStatement;
use BackupApp\Restore\Integration\Sql\RestoreStatementKind;

final class SessionDirective extends AbstractExecutableStatement
{
    public function kind(): RestoreStatementKind { return RestoreStatementKind::SESSION_DIRECTIVE; }
}
