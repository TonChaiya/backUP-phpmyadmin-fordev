<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Ownership;

use BackupApp\Restore\Integration\Policy\TrustedRunId;

final class OwnershipMarker
{
    private function __construct(
        private readonly string $table,
        private readonly ?string $nonce,
        private readonly string $hash,
        private readonly string $runId
    ) {}
    public static function generate(TrustedRunId $runId): self
    {
        $nonce = bin2hex(random_bytes(32));
        return new self('__restore_owner_' . substr($runId->value(), 0, 16), $nonce, hash('sha256', $nonce), $runId->value());
    }
    public static function fromEvidence(TrustedRunId $runId, string $nonceHash): self
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $nonceHash) !== 1) { throw new \DomainException('invalid_ownership_marker_hash'); }
        return new self('__restore_owner_' . substr($runId->value(), 0, 16), null, $nonceHash, $runId->value());
    }
    public function table(): string { return $this->table; }
    public function nonce(): string { if ($this->nonce === null) { throw new \DomainException('ownership_nonce_not_available'); } return $this->nonce; }
    public function nonceHash(): string { return $this->hash; }
    /** @return array{table: string, nonce_hash: string, run_id: string} */
    public function publicEvidence(): array { return ['table' => $this->table, 'nonce_hash' => $this->nonceHash(), 'run_id' => $this->runId]; }
}
