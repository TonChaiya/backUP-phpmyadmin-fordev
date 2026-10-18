<?php

declare(strict_types=1);

namespace BackupApp\Restore;

use RuntimeException;

final class RestoreExecutionGuard
{
    private string $directory;

    public function __construct(string $jobDirectory)
    {
        $this->directory = $jobDirectory . DIRECTORY_SEPARATOR . 'locks';
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Cannot create restore lock directory');
        }
    }

    /** @return resource|null */
    public function acquireJob(string $id)
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1) {
            throw new RuntimeException('Invalid restore job identifier');
        }
        return $this->acquire($this->directory . DIRECTORY_SEPARATOR . 'job-' . $id . '.lock', false);
    }

    /** @return resource|null */
    public function acquireGlobal(bool $blocking = true)
    {
        return $this->acquire($this->directory . DIRECTORY_SEPARATOR . 'preflight-global.lock', $blocking);
    }

    /** @param resource|null $handle */
    public function release($handle): void
    {
        if (is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return resource|null */
    private function acquire(string $path, bool $blocking)
    {
        $handle = @fopen($path, 'c+b');
        if ($handle === false) {
            throw new RuntimeException('Cannot open restore preflight lock');
        }
        if (!flock($handle, LOCK_EX | ($blocking ? 0 : LOCK_NB))) {
            fclose($handle);
            return null;
        }
        @chmod($path, 0640);
        return $handle;
    }
}
