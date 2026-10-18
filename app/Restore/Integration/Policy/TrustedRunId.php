<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Policy;

use DomainException;

final class TrustedRunId
{
    public function __construct(private readonly string $value)
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $value) !== 1) {
            throw new DomainException('invalid_run_id');
        }
    }
    public function value(): string { return $this->value; }
}
