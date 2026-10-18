<?php

declare(strict_types=1);

namespace BackupApp\Restore\Sql;

final class LexedStatement
{
    public function __construct(
        private readonly string $sql,
        private readonly int $startOffset,
        private readonly int $endOffset,
        private readonly string $delimiter
    ) {
    }

    public function sql(): string { return $this->sql; }
    public function startOffset(): int { return $this->startOffset; }
    public function endOffset(): int { return $this->endOffset; }
    public function delimiter(): string { return $this->delimiter; }
}
