<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Authorization;

use DomainException;

final class LiveAuthorizationReceipt implements ExecutionAuthorization
{
    private bool $consumed = false;
    /** @param list<string> $targets */
    public function __construct(
        private readonly string $jobId,
        private readonly string $runId,
        private readonly string $serverFingerprint,
        private readonly array $targets,
        private readonly int $expiresAt,
        private readonly string $contextId,
        private readonly array $artifactSha256 = []
    ) {
        if (preg_match('/^[a-f0-9]{32}$/D', $jobId) !== 1 || preg_match('/^[a-f0-9]{32}$/D', $runId) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $serverFingerprint) !== 1 || $targets === [] || $contextId === '') {
            throw new DomainException('invalid_live_authorization_receipt');
        }
        foreach ($artifactSha256 as $source => $sha256) {
            if (!is_string($source) || preg_match('/^[a-f0-9]{64}$/D', (string) $sha256) !== 1) {
                throw new DomainException('invalid_live_authorization_artifact');
            }
        }
    }
    public function validFor(string $jobId, string $runId, string $serverFingerprint): bool
    {
        return !$this->consumed && time() <= $this->expiresAt
            && hash_equals($this->jobId, $jobId) && hash_equals($this->runId, $runId)
            && hash_equals($this->serverFingerprint, $serverFingerprint);
    }
    public function consume(): void
    {
        if ($this->consumed || time() > $this->expiresAt) { throw new DomainException('authorization_receipt_unusable'); }
        $this->consumed = true;
    }
    public function validForTargets(array $targets): bool
    {
        $expected = $this->targets;
        sort($expected, SORT_STRING); sort($targets, SORT_STRING);
        return !$this->consumed && $expected === $targets;
    }
    public function validForArtifacts(array $artifactSha256): bool
    {
        $expected = $this->artifactSha256; ksort($expected, SORT_STRING); ksort($artifactSha256, SORT_STRING);
        return !$this->consumed && $expected !== [] && $expected === $artifactSha256;
    }
    public function validForContext(string $contextId): bool
    {
        return !$this->consumed && $contextId !== '' && hash_equals(hash('sha256', $this->contextId), hash('sha256', $contextId));
    }
    /** @return array<string, mixed> */
    public function privateRecord(): array
    {
        return ['job_id' => $this->jobId, 'run_id' => $this->runId, 'server_fingerprint' => $this->serverFingerprint,
            'targets' => $this->targets, 'artifact_sha256' => $this->artifactSha256, 'expires_at' => $this->expiresAt,
            'context_id_hash' => hash('sha256', $this->contextId), 'consumed' => $this->consumed];
    }
}
