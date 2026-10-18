<?php

declare(strict_types=1);

namespace BackupApp\Restore;

final class RestoreJobAccess
{
    private const MAX_JOBS = 20;

    public function register(string $id): void
    {
        $this->assertId($id);
        $ids = is_array($_SESSION['restore_job_ids'] ?? null) ? $_SESSION['restore_job_ids'] : [];
        $ids = array_values(array_filter($ids, static fn (mixed $value): bool => is_string($value) && $value !== $id));
        $ids[] = $id;
        $_SESSION['restore_job_ids'] = array_slice($ids, -self::MAX_JOBS);
        $_SESSION['active_restore_job_id'] = $id;
    }

    public function owns(string $id): bool
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1) {
            return false;
        }
        $ids = is_array($_SESSION['restore_job_ids'] ?? null) ? $_SESSION['restore_job_ids'] : [];
        return in_array($id, $ids, true);
    }

    /** @return array<int, string> */
    public function ownedJobIds(): array
    {
        $ids = is_array($_SESSION['restore_job_ids'] ?? null) ? $_SESSION['restore_job_ids'] : [];
        $result = [];

        for ($index = count($ids) - 1; $index >= 0; $index--) {
            $id = $ids[$index] ?? null;

            if (is_string($id)
                && preg_match('/^[a-f0-9]{32}$/D', $id) === 1
                && !in_array($id, $result, true)
            ) {
                $result[] = $id;
            }
        }

        return array_slice($result, 0, self::MAX_JOBS);
    }

    public function assertOwned(string $id): void
    {
        if (!$this->owns($id)) {
            throw new RestoreJobRequestException('ไม่พบงานตรวจสอบ Restore ที่ได้รับอนุญาต', 404);
        }
    }

    public function activeJobId(): ?string
    {
        $id = $_SESSION['active_restore_job_id'] ?? null;
        return is_string($id) && $this->owns($id) ? $id : null;
    }

    public function clearActive(string $id): void
    {
        if (($_SESSION['active_restore_job_id'] ?? null) === $id) {
            unset($_SESSION['active_restore_job_id']);
        }
    }

    /** @return array<string, mixed> */
    public function publicView(array $job): array
    {
        return [
            'id' => (string) ($job['id'] ?? ''),
            'status' => (string) ($job['status'] ?? 'queued'),
            'created_at' => (string) ($job['created_at'] ?? ''),
            'updated_at' => (string) ($job['updated_at'] ?? ''),
            'finished_at' => is_string($job['finished_at'] ?? null) ? $job['finished_at'] : null,
            'artifact' => (string) ($job['artifact'] ?? ''),
            'target' => (string) ($job['target'] ?? ''),
            'result' => is_array($job['result'] ?? null) ? $job['result'] : null,
            'can_preflight' => ($job['status'] ?? null) === 'queued' && ($job['preflight_claimed_at'] ?? null) === null,
        ];
    }

    private function assertId(string $id): void
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1) {
            throw new RestoreJobRequestException('รูปแบบรหัสงาน Restore ไม่ถูกต้อง', 400);
        }
    }
}
