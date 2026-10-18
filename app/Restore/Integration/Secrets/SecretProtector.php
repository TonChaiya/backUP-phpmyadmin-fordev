<?php
declare(strict_types=1);
namespace BackupApp\Restore\Integration\Secrets;
interface SecretProtector
{
    public function protect(#[\SensitiveParameter] string $plaintext, string $entropy, WindowsIdentity $identity): string;
    public function unprotect(string $ciphertext, string $entropy, WindowsIdentity $identity): string;
}
