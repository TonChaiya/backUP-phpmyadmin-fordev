<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Sql;

final class InlineStatementPayload implements StatementPayload
{
    public function __construct(private readonly string $sql) {}
    public function read(): string { return $this->sql; }
}
