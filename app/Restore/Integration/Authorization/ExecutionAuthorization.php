<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Authorization;

interface ExecutionAuthorization
{
    public function validFor(string $jobId, string $runId, string $serverFingerprint): bool;
    /** @param list<string> $targets */
    public function validForTargets(array $targets): bool;
    /** @param array<string, string> $artifactSha256 */
    public function validForArtifacts(array $artifactSha256): bool;
    public function validForContext(string $contextId): bool;
    public function consume(): void;
}
