<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Sql;

abstract class AbstractExecutableStatement implements RestorePlanItem, ExecutableRestoreStatement
{
    private readonly StatementPayload $payload;
    public function __construct(string|StatementPayload $sql)
    {
        $this->payload = is_string($sql) ? new InlineStatementPayload($sql) : $sql;
    }

    public function renderForTarget(): string { return $this->payload->read(); }
    public function category(): string { return $this->kind()->value; }
}
