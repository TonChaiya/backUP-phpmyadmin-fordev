<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Execution;

final class ExecutionResult
{
    public function __construct(private readonly bool $succeeded, private readonly int $executed, private readonly string $code) {}
    public function succeeded(): bool { return $this->succeeded; }
    public function executed(): int { return $this->executed; }
    public function code(): string { return $this->code; }
}
