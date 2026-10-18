<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Sql\Statement;

use BackupApp\Restore\Integration\Sql\AbstractExecutableStatement;
use BackupApp\Restore\Integration\Sql\RestoreStatementKind;
use BackupApp\Restore\Integration\Sql\StatementPayload;

final class CreateTable extends AbstractExecutableStatement
{
    public function __construct(string|StatementPayload $sql, private readonly string $table) { parent::__construct($sql); }
    public function table(): string { return $this->table; }
    public function kind(): RestoreStatementKind { return RestoreStatementKind::CREATE_TABLE; }
}
