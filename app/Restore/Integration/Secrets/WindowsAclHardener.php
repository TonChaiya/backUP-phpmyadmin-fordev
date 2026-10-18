<?php
declare(strict_types=1);
namespace BackupApp\Restore\Integration\Secrets;

interface WindowsAclHardener
{
    public function hardenDirectory(string $path, WindowsIdentity $identity): void;
    public function hardenFile(string $path, WindowsIdentity $identity): void;
}
