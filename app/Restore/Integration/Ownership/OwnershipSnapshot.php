<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Ownership;

final class OwnershipSnapshot
{
    /** @param list<array<string, mixed>> $records */
    public function __construct(private readonly array $records) {}
    public function revision(): int { return count($this->records); }
    public function latestType(): string { return (string) ($this->records[array_key_last($this->records)]['event']['type'] ?? ''); }
    /** @return array<string, mixed> */ public function proposal(): array { return (array) ($this->records[0]['event']['data'] ?? []); }
    /** @return list<array<string, mixed>> */ public function records(): array { return $this->records; }
    /** @return array<string, mixed>|null */
    public function latestEvent(string $type): ?array
    {
        for ($i = count($this->records) - 1; $i >= 0; $i--) {
            if (($this->records[$i]['event']['type'] ?? null) === $type) {
                return (array) ($this->records[$i]['event']['data'] ?? []);
            }
        }
        return null;
    }
}
