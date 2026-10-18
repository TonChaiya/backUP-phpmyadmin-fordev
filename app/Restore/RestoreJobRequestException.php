<?php

declare(strict_types=1);

namespace BackupApp\Restore;

use RuntimeException;

final class RestoreJobRequestException extends RuntimeException
{
    public function __construct(string $message, private int $status = 400)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->status;
    }
}
