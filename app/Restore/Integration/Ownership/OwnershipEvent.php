<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Ownership;

use DomainException;

final class OwnershipEvent
{
    /** @param array<string, mixed> $data */
    public function __construct(private readonly string $type, private readonly array $data)
    {
        if (preg_match('/^[a-z][a-z0-9_]{2,63}$/D', $type) !== 1) {
            throw new DomainException('invalid_ownership_event');
        }
        foreach (array_keys($data) as $key) {
            if (preg_match('/password|credential|secret|nonce/i', (string) $key) === 1) {
                throw new DomainException('secret_not_allowed_in_journal');
            }
        }
    }
    public function type(): string { return $this->type; }
    /** @return array<string, mixed> */ public function data(): array { return $this->data; }
}
