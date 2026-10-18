<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Attestation;

final class PrivilegeEvidence
{
    /** @param list<string> $findings @param list<string> $scopes */
    public function __construct(
        private readonly string $role,
        private readonly ServerIdentity $identity,
        private readonly bool $accepted,
        private readonly array $findings,
        private readonly array $scopes
    ) {}
    public function role(): string { return $this->role; }
    public function identity(): ServerIdentity { return $this->identity; }
    public function accepted(): bool { return $this->accepted; }
    /** @return list<string> */ public function findings(): array { return $this->findings; }
    /** @return list<string> */ public function scopes(): array { return $this->scopes; }
}
