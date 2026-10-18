<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Ownership;

final class CleanupDecision
{
    /** @param list<string> $findings */
    public function __construct(private readonly bool $allowed, private readonly string $code, private readonly array $findings) {}
    public function allowed(): bool { return $this->allowed; }
    public function code(): string { return $this->code; }
    /** @return list<string> */ public function findings(): array { return $this->findings; }
}
