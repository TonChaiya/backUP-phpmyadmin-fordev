<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Execution;

use BackupApp\Restore\Integration\Ownership\OwnershipMarker;
use DomainException;

final class TargetProvisioningException extends DomainException
{
    public function __construct(
        string $code,
        private readonly bool $targetMayExist,
        private readonly OwnershipMarker $marker,
        ?\Throwable $previous = null
    ) {
        parent::__construct($code, 0, $previous);
    }

    public function targetMayExist(): bool { return $this->targetMayExist; }
    public function marker(): OwnershipMarker { return $this->marker; }
}
