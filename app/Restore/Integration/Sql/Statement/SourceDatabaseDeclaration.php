<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Sql\Statement;

use BackupApp\Restore\Integration\Sql\RestorePlanItem;

final class SourceDatabaseDeclaration implements RestorePlanItem
{
    public function __construct(private readonly string $database) {}
    public function database(): string { return $this->database; }
    public function category(): string { return 'source_database_declaration'; }
}
