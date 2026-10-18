<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Execution;

use BackupApp\Restore\Integration\Database\RestoreConnection;
use BackupApp\Restore\Integration\Ownership\CleanupDecisionEngine;
use BackupApp\Restore\Integration\Ownership\OwnershipSnapshot;
use BackupApp\Restore\Integration\Ownership\OwnershipMarker;
use BackupApp\Restore\Integration\Policy\TrustedTargetName;
use DomainException;

final class CleanupCoordinator
{
    public function __construct(private readonly CleanupDecisionEngine $decisions) {}
    /** @param array<string, mixed> $runtime @return array{allowed: bool, verified_absent: bool, code: string} */
    public function cleanup(OwnershipSnapshot $snapshot, array $runtime, TrustedTargetName $target, OwnershipMarker $marker, RestoreConnection $connection): array
    {
        $decision = $this->decisions->authorize($snapshot, $runtime);
        if (!$decision->allowed()) { return ['allowed' => false, 'verified_absent' => false, 'code' => $decision->code()]; }
        if (!hash_equals($target->value(), (string) $runtime['target'])) { throw new DomainException('cleanup_target_mismatch'); }
        if (!hash_equals((string) $runtime['server_fingerprint'], $connection->serverIdentityFingerprint())) {
            return ['allowed' => false, 'verified_absent' => false, 'code' => 'cleanup_blocked_server_identity_mismatch'];
        }
        $connection->selectTarget($target);
        if ($connection->currentDatabase() !== $target->value()) {
            return ['allowed' => false, 'verified_absent' => false, 'code' => 'cleanup_blocked_target_context_mismatch'];
        }
        $actualMarkerHash = $connection->readOwnershipMarkerHash($marker);
        if (!hash_equals($marker->nonceHash(), (string) $runtime['marker_hash'])
            || !is_string($actualMarkerHash) || !hash_equals($marker->nonceHash(), $actualMarkerHash)) {
            return ['allowed' => false, 'verified_absent' => false, 'code' => 'cleanup_blocked_marker_mismatch'];
        }
        $connection->dropExactTarget($target);
        $absent = !$connection->targetExists($target);
        return ['allowed' => true, 'verified_absent' => $absent, 'code' => $absent ? 'cleanup_verified' : 'cleanup_absence_unproven'];
    }
}
