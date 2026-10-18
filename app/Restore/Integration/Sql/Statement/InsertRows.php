<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Sql\Statement;

use BackupApp\Restore\Integration\Sql\AbstractExecutableStatement;
use BackupApp\Restore\Integration\Sql\RestoreStatementKind;
use BackupApp\Restore\Integration\Sql\StatementPayload;

final class InsertRows extends AbstractExecutableStatement
{
    /** @param list<string> $columns */
    public function __construct(string|StatementPayload $sql, private readonly string $table, private readonly array $columns, private readonly int $rowCount)
    {
        parent::__construct($sql);
    }

    public function kind(): RestoreStatementKind { return RestoreStatementKind::INSERT_ROWS; }
    public function table(): string { return $this->table; }
    /** @return list<string> */ public function columns(): array { return $this->columns; }
    public function rowCount(): int { return $this->rowCount; }
}
