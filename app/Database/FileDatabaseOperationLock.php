<?php

declare(strict_types=1);

namespace BackupApp\Database;

use DomainException;
use RuntimeException;

final class FileDatabaseOperationLock implements DatabaseOperationLock
{
    public function __construct(private readonly string $path)
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create database operation lock directory');
        }
    }

    public function acquire(string $operationId, bool $blocking = true)
    {
        if (preg_match('/^[a-z0-9][a-z0-9_-]{0,127}$/D', $operationId) !== 1) {
            throw new DomainException('Invalid database operation identifier');
        }
        $handle = @fopen($this->path, 'c+b');
        if ($handle === false) { throw new RuntimeException('Cannot open database operation lock'); }
        if (!flock($handle, LOCK_EX | ($blocking ? 0 : LOCK_NB))) {
            fclose($handle);
            return null;
        }
        ftruncate($handle, 0);
        fwrite($handle, json_encode(['operation_id' => $operationId, 'pid' => getmypid(), 'acquired_at' => gmdate('c')], JSON_THROW_ON_ERROR));
        fflush($handle);
        @chmod($this->path, 0640);
        return $handle;
    }

    public function release($handle): void
    {
        if (!is_resource($handle)) { return; }
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
