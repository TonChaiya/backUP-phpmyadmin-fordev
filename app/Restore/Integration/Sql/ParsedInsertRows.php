<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Sql;

use BackupApp\Restore\Integration\Validation\TypedSqlValue;

final class ParsedInsertRows
{
    /** @param list<string> $columns @param list<list<TypedSqlValue>> $rows */
    public function __construct(
        private readonly string $table,
        private readonly array $columns,
        private readonly array $rows
    ) {}

    public function table(): string { return $this->table; }
    /** @return list<string> */ public function columns(): array { return $this->columns; }
    /** @return list<list<TypedSqlValue>> */ public function rows(): array { return $this->rows; }
}
