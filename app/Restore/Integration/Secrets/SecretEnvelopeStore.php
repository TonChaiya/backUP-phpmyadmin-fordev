<?php
declare(strict_types=1);
namespace BackupApp\Restore\Integration\Secrets;

interface SecretEnvelopeStore
{
    public function create(SecretRole $role, string $envelope, WindowsIdentity $identity): void;
    public function read(SecretRole $role): string;
    public function delete(SecretRole $role): void;
}
