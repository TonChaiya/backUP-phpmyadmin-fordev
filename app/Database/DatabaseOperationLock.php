<?php

declare(strict_types=1);

namespace BackupApp\Database;

interface DatabaseOperationLock
{
    /** @return resource|null */
    public function acquire(string $operationId, bool $blocking = true);
    /** @param resource|null $handle */
    public function release($handle): void;
}
