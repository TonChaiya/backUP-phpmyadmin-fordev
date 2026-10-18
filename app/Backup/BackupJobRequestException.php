<?php

declare(strict_types=1);

namespace BackupApp\Backup;

use RuntimeException;

final class BackupJobRequestException extends RuntimeException
{
    public function __construct(string $message, private int $httpStatus)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
