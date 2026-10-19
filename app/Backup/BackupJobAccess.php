<?php

declare(strict_types=1);

namespace BackupApp\Backup;

use BackupApp\Database\AuthorizedSourcePolicy;

final class BackupJobAccess
{
    private const MAX_SESSION_JOBS = 20;

    /**
     * @param array<int, string> $selected
     * @param array<int, string> $known
     * @return array<int, string>
     */
    public function select(array $selected, bool $all, array $known): array
    {
        $policy = new AuthorizedSourcePolicy();
        $known = $policy->filterAuthorized($known);

        if ($all) {
            $result = $known;
        } else {
            $result = [];

            foreach ($selected as $database) {
                if (!is_string($database) || !$policy->isAuthorized($database) || !in_array($database, $known, true)) {
                    throw new BackupJobRequestException('ฐานข้อมูลที่เลือกไม่ได้รับอนุญาต', 422);
                }

                if (!in_array($database, $result, true)) {
                    $result[] = $database;
                }
            }
        }

        if ($result === []) {
            throw new BackupJobRequestException('กรุณาเลือกฐานข้อมูลที่ได้รับอนุญาตอย่างน้อย 1 ฐาน', 422);
        }

        return $result;
    }

    public function register(string $jobId): void
    {
        $this->assertValidId($jobId);
        $connectionContext = $this->currentConnectionContext();

        if ($connectionContext === null) {
            throw new BackupJobRequestException('ไม่พบ connection context สำหรับงานสำรอง', 409);
        }

        $ids = is_array($_SESSION['backup_job_ids'] ?? null) ? $_SESSION['backup_job_ids'] : [];
        $ids = array_values(array_filter($ids, static fn (mixed $id): bool => is_string($id) && $id !== $jobId));
        $ids[] = $jobId;
        $_SESSION['backup_job_ids'] = array_slice($ids, -self::MAX_SESSION_JOBS);
        $_SESSION['active_backup_job_id'] = $jobId;
        $contexts = is_array($_SESSION['backup_job_contexts'] ?? null) ? $_SESSION['backup_job_contexts'] : [];
        $contexts[$jobId] = $connectionContext;
        $_SESSION['backup_job_contexts'] = array_intersect_key(
            $contexts,
            array_flip($_SESSION['backup_job_ids'])
        );
    }

    public function owns(string $jobId): bool
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $jobId) !== 1) {
            return false;
        }

        $ids = is_array($_SESSION['backup_job_ids'] ?? null) ? $_SESSION['backup_job_ids'] : [];

        return in_array($jobId, $ids, true);
    }

    /** @return array<int, string> */
    public function ownedJobIds(): array
    {
        $ids = is_array($_SESSION['backup_job_ids'] ?? null) ? $_SESSION['backup_job_ids'] : [];
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

        return array_slice($result, 0, self::MAX_SESSION_JOBS);
    }

    public function assertOwned(string $jobId): void
    {
        if (!$this->owns($jobId)) {
            throw new BackupJobRequestException('ไม่พบงานสำรองที่ได้รับอนุญาต', 404);
        }
    }

    public function activeJobId(): ?string
    {
        $id = $_SESSION['active_backup_job_id'] ?? null;

        return is_string($id) && $this->owns($id) ? $id : null;
    }

    public function rotateConnectionContext(): string
    {
        $context = bin2hex(random_bytes(16));
        $_SESSION['backup_connection_context'] = $context;

        return $context;
    }

    public function currentConnectionContext(): ?string
    {
        $context = $_SESSION['backup_connection_context'] ?? null;

        return is_string($context) && preg_match('/^[a-f0-9]{32}$/D', $context) === 1 ? $context : null;
    }

    public function matchesCurrentConnection(string $jobId): bool
    {
        if (!$this->owns($jobId)) {
            return false;
        }

        $current = $this->currentConnectionContext();
        $contexts = is_array($_SESSION['backup_job_contexts'] ?? null) ? $_SESSION['backup_job_contexts'] : [];
        $jobContext = $contexts[$jobId] ?? null;

        return $current !== null && is_string($jobContext) && hash_equals($jobContext, $current);
    }

    public function clearActive(string $jobId): void
    {
        if (($_SESSION['active_backup_job_id'] ?? null) === $jobId) {
            unset($_SESSION['active_backup_job_id']);
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
            'can_execute' => ($job['status'] ?? null) === 'queued'
                && ($job['execution_claimed_at'] ?? null) === null,
            'summary' => is_array($job['summary'] ?? null) ? $job['summary'] : [],
            'databases' => array_values(array_map(static function (array $database): array {
                $artifact = is_array($database['artifact'] ?? null) ? [
                    'filename' => (string) ($database['artifact']['filename'] ?? ''),
                    'relative_path' => (string) ($database['artifact']['relative_path'] ?? ''),
                    'bytes' => max(0, (int) ($database['artifact']['bytes'] ?? 0)),
                    'sha256' => (string) ($database['artifact']['sha256'] ?? ''),
                    'manifest_path' => (string) ($database['artifact']['manifest_path'] ?? ''),
                ] : null;
                $error = is_array($database['error'] ?? null) ? [
                    'code' => (string) ($database['error']['code'] ?? 'backup_failed'),
                    'message' => (string) ($database['error']['message'] ?? 'ไม่สามารถสำรองฐานข้อมูลนี้ได้'),
                ] : null;

                return [
                    'name' => (string) ($database['name'] ?? ''),
                    'status' => (string) ($database['status'] ?? 'queued'),
                    'started_at' => is_string($database['started_at'] ?? null) ? $database['started_at'] : null,
                    'finished_at' => is_string($database['finished_at'] ?? null) ? $database['finished_at'] : null,
                    'artifact' => $artifact,
                    'error' => $error,
                ];
            }, is_array($job['databases'] ?? null) ? $job['databases'] : [])),
        ];
    }

    private function assertValidId(string $jobId): void
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $jobId) !== 1) {
            throw new BackupJobRequestException('รูปแบบรหัสงานสำรองไม่ถูกต้อง', 400);
        }
    }
}
