<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Secrets;

interface NativeHelperDeploymentVerifier
{
    public function verify(string $path): void;
}
