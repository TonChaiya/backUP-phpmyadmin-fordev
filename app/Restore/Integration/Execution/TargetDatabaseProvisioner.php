<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Execution;

use BackupApp\Restore\Integration\Database\RestoreConnection;
use BackupApp\Restore\Integration\Ownership\OwnershipEvent;
use BackupApp\Restore\Integration\Ownership\OwnershipJournal;
use BackupApp\Restore\Integration\Ownership\OwnershipMarker;
use BackupApp\Restore\Integration\Policy\TrustedRunId;
use BackupApp\Restore\Integration\Policy\TrustedTargetName;
use DomainException;

final class TargetDatabaseProvisioner
{
    /** @param array<string, list<string>> $inventory @return array{created: true, marker_hash: string, marker: OwnershipMarker} */
    public function createNew(TrustedTargetName $target, TrustedRunId $runId, RestoreConnection $connection, OwnershipJournal $journal, array $inventory): array
    {
        if ($connection->targetExists($target)) { throw new DomainException('target_collision'); }
        $marker = OwnershipMarker::generate($runId);
        foreach ($inventory as $names) {
            if (in_array($marker->table(), $names, true)) { throw new DomainException('ownership_marker_collision'); }
        }
        $targetMayExist = false;
        try {
            $connection->createTarget($target, 'utf8mb4', 'utf8mb4_unicode_ci');
            $targetMayExist = true;
            $connection->selectTarget($target);
            if ($connection->currentDatabase() !== $target->value()) { throw new DomainException('target_selection_failed'); }
            $connection->createOwnershipMarker($marker);
            if (!hash_equals($marker->nonceHash(), (string) $connection->readOwnershipMarkerHash($marker))) { throw new DomainException('ownership_marker_unproven'); }
            $journal->append(new OwnershipEvent('target_created', ['created' => true, 'preexisting' => false, 'marker_hash' => $marker->nonceHash()]));
        } catch (\Throwable $exception) {
            if ($targetMayExist) {
                try {
                    $journal->append(new OwnershipEvent('provision_failed', [
                        'target_may_exist' => true,
                        'ownership_verified' => false,
                        'code' => $exception instanceof DomainException ? $exception->getMessage() : 'target_provision_failed',
                    ]));
                } catch (\Throwable) {
                    // The original failure remains authoritative; cleanup stays blocked.
                }
            }
            throw new TargetProvisioningException(
                $exception instanceof DomainException ? $exception->getMessage() : 'target_provision_failed',
                $targetMayExist,
                $marker,
                $exception
            );
        }
        return ['created' => true, 'marker_hash' => $marker->nonceHash(), 'marker' => $marker];
    }
}
