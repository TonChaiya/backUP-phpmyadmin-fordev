<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Ownership;

final class CleanupDecisionEngine
{
    /** @param array<string, mixed> $runtime */
    public function authorize(OwnershipSnapshot $journal, array $runtime): CleanupDecision
    {
        $proposal = $journal->proposal();
        $created = $journal->latestEvent('target_created');
        $findings = [];
        foreach (['run_id', 'server_fingerprint', 'target', 'job_id'] as $field) {
            $expected = $field === 'run_id'
                ? (string) ($journal->records()[0]['run_id'] ?? '')
                : (string) ($proposal[$field] ?? '');
            if ($expected === '' || !isset($runtime[$field]) || !hash_equals($expected, (string) $runtime[$field])) {
                $findings[] = $field . '_mismatch';
            }
        }
        if ($created === null || ($created['created'] ?? false) !== true || ($created['preexisting'] ?? true) !== false) {
            $findings[] = 'creation_unproven';
        }
        if ($created === null || !isset($created['marker_hash'], $runtime['marker_hash'])
            || !hash_equals((string) $created['marker_hash'], (string) $runtime['marker_hash'])) {
            $findings[] = 'marker_mismatch';
        }
        foreach (['shared_lock_held', 'session_owned', 'target_exists'] as $flag) {
            if (($runtime[$flag] ?? false) !== true) { $findings[] = $flag . '_required'; }
        }
        $allowed = $findings === [];
        return new CleanupDecision($allowed, $allowed ? 'cleanup_authorized' : 'cleanup_blocked_ownership_unproven', array_values(array_unique($findings)));
    }
}
