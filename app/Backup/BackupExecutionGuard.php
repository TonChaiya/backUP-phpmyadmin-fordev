<?php

declare(strict_types=1);

namespace BackupApp\Backup;

use BackupApp\Database\DatabaseOperationLock;
use BackupApp\Database\FileDatabaseOperationLock;
use RuntimeException;

final class BackupExecutionGuard
{
    private string $lockDirectory;
    private DatabaseOperationLock $sharedLock;

    public function __construct(string $jobDirectory, ?DatabaseOperationLock $sharedLock = null)
    {
        $this->lockDirectory = $jobDirectory . DIRECTORY_SEPARATOR . 'locks';

        if (!is_dir($this->lockDirectory) && !@mkdir($this->lockDirectory, 0750, true) && !is_dir($this->lockDirectory)) {
            throw new RuntimeException('Cannot create backup lock directory');
        }
        $this->sharedLock = $sharedLock ?? new FileDatabaseOperationLock($this->lockDirectory . DIRECTORY_SEPARATOR . 'global.lock');
    }

    /** @return resource|null */
    public function acquireJob(string $jobId)
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $jobId) !== 1) {
            throw new RuntimeException('Invalid backup job identifier');
        }

        return $this->acquire($this->lockDirectory . DIRECTORY_SEPARATOR . 'job-' . $jobId . '.lock', false);
    }

    /** @return resource|null */
    public function acquireGlobal(bool $blocking = true)
    {
        return $this->sharedLock->acquire('backup-global', $blocking);
    }

    /** @param resource|null $handle */
    public function release($handle): void
    {
        if (!is_resource($handle)) {
            return;
        }

        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /** @return resource|null */
    private function acquire(string $path, bool $blocking)
    {
        $handle = @fopen($path, 'c+b');

        if ($handle === false) {
            throw new RuntimeException('Cannot open backup execution lock');
        }

        $operation = LOCK_EX | ($blocking ? 0 : LOCK_NB);

        if (!flock($handle, $operation)) {
            fclose($handle);

            return null;
        }

        @chmod($path, 0640);

        return $handle;
    }
}
