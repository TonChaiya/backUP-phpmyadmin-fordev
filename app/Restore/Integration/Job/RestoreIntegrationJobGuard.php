<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Job;

use DomainException;
use RuntimeException;

final class RestoreIntegrationJobGuard
{
    private readonly string $directory;
    public function __construct(string $jobDirectory)
    {
        $this->directory = rtrim($jobDirectory, '/\\') . DIRECTORY_SEPARATOR . 'locks';
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0750, true) && !is_dir($this->directory)) { throw new RuntimeException('integration_lock_directory_unavailable'); }
    }
    public function acquireJob(string $id)
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1) { throw new DomainException('invalid_integration_job_id'); }
        $handle = @fopen($this->directory . DIRECTORY_SEPARATOR . 'job-' . $id . '.lock', 'c+b');
        if ($handle === false) { throw new RuntimeException('integration_job_lock_unavailable'); }
        if (!flock($handle, LOCK_EX | LOCK_NB)) { fclose($handle); return null; }
        @chmod($this->directory . DIRECTORY_SEPARATOR . 'job-' . $id . '.lock', 0640);
        return $handle;
    }
    public function release($handle): void { if (is_resource($handle)) { flock($handle, LOCK_UN); fclose($handle); } }
}
