<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Job;

final class RestoreIntegrationJobAccess
{
    public function register(string $id): void
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1) { throw new RestoreIntegrationJobException('invalid_job_id'); }
        $ids = array_values(array_filter((array) ($_SESSION['restore_integration_job_ids'] ?? []), static fn ($v): bool => is_string($v) && $v !== $id));
        $ids[] = $id; $_SESSION['restore_integration_job_ids'] = array_slice($ids, -20); $_SESSION['active_restore_integration_job_id'] = $id;
    }
    public function owns(string $id): bool { return preg_match('/^[a-f0-9]{32}$/D', $id) === 1 && in_array($id, (array) ($_SESSION['restore_integration_job_ids'] ?? []), true); }
    /** @param array<string, mixed> $job @return array<string, mixed> */
    public function publicView(array $job): array
    {
        return [
            'id' => (string) ($job['id'] ?? ''), 'run_id' => (string) ($job['run_id'] ?? ''), 'status' => (string) ($job['status'] ?? 'queued'),
            'created_at' => (string) ($job['created_at'] ?? ''), 'updated_at' => (string) ($job['updated_at'] ?? ''),
            'finished_at' => $job['finished_at'] ?? null, 'items' => is_array($job['items'] ?? null) ? $job['items'] : [],
            'can_execute' => false, 'live_execution_enabled' => false,
        ];
    }
}
