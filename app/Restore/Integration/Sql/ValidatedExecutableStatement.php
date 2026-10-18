<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Sql;

/**
 * Immutable execution token. The source payload is read exactly once before
 * validation, so adapters can never observe a different SQL string later.
 */
final class ValidatedExecutableStatement
{
    private function __construct(
        private readonly RestoreStatementKind $kind,
        private readonly string $sql
    ) {}

    public static function freeze(ExecutableRestoreStatement $statement): self
    {
        $sql = $statement->renderForTarget();
        ExecutableStatementPolicy::assertAllowedSql($statement, $sql);
        return new self($statement->kind(), $sql);
    }

    public function kind(): RestoreStatementKind { return $this->kind; }
    public function sql(): string { return $this->sql; }
}
