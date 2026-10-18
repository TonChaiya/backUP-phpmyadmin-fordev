<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Secrets;

use Throwable;

abstract class AtomicFileSecretEnvelopeStore implements SecretEnvelopeStore
{
    private const MAX_BYTES = 65536;

    protected function __construct(
        private readonly string $directory,
        private readonly WindowsAclHardener $acl,
    ) {
        if ($directory === '' || (!str_starts_with($directory, DIRECTORY_SEPARATOR)
            && preg_match('/^[A-Za-z]:[\\\\\/]/D', $directory) !== 1)) {
            throw new SecretProviderException('secret_storage_failed');
        }
    }

    public function pathFor(SecretRole $role): string
    {
        $name = $role === SecretRole::BackupReader ? 'backup-reader.dpapi.json' : 'restore-runner.dpapi.json';
        return $this->directory . DIRECTORY_SEPARATOR . $name;
    }

    public function create(SecretRole $role, string $envelope, WindowsIdentity $identity): void
    {
        if ($envelope === '' || strlen($envelope) > self::MAX_BYTES) { throw new SecretProviderException('secret_envelope_invalid'); }
        $final = $this->pathFor($role);
        if (is_file($final)) { throw new SecretProviderException('secret_already_exists'); }
        $temp = null;
        $published = false;
        try {
            if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
                throw new SecretProviderException('secret_storage_failed');
            }
            $this->acl->hardenDirectory($this->directory, $identity);
            if (is_file($final)) { throw new SecretProviderException('secret_already_exists'); }
            $temp = $this->directory . DIRECTORY_SEPARATOR . '.' . basename($final) . '.' . bin2hex(random_bytes(12)) . '.tmp';
            $handle = @fopen($temp, 'x+b');
            if ($handle === false) { throw new SecretProviderException('secret_storage_failed'); }
            try {
                $this->acl->hardenFile($temp, $identity);
                $offset = 0;
                while ($offset < strlen($envelope)) {
                    $written = fwrite($handle, substr($envelope, $offset));
                    if (!is_int($written) || $written < 1) { throw new SecretProviderException('secret_storage_failed'); }
                    $offset += $written;
                }
                if (!fflush($handle)) { throw new SecretProviderException('secret_storage_failed'); }
            } finally { fclose($handle); }
            if (is_file($final) || !@rename($temp, $final)) { throw new SecretProviderException(is_file($final) ? 'secret_already_exists' : 'secret_storage_failed'); }
            $temp = null;
            $published = true;
            $this->acl->hardenFile($final, $identity);
        } catch (SecretProviderException $exception) {
            if (is_string($temp) && is_file($temp)) { @unlink($temp); }
            if ($published && is_file($final)) { @unlink($final); }
            throw $exception;
        } catch (Throwable) {
            if (is_string($temp) && is_file($temp)) { @unlink($temp); }
            if ($published && is_file($final)) { @unlink($final); }
            throw new SecretProviderException('secret_storage_failed');
        }
    }

    public function read(SecretRole $role): string
    {
        $path = $this->pathFor($role);
        if (!is_file($path)) { throw new SecretProviderException('secret_missing'); }
        $size = @filesize($path);
        if (!is_int($size) || $size < 1 || $size > self::MAX_BYTES) { throw new SecretProviderException('secret_envelope_invalid'); }
        $content = @file_get_contents($path);
        if (!is_string($content) || strlen($content) !== $size) { throw new SecretProviderException('secret_storage_failed'); }
        return $content;
    }

    public function delete(SecretRole $role): void
    {
        $path = $this->pathFor($role);
        if (!is_file($path)) { throw new SecretProviderException('secret_missing'); }
        if (!@unlink($path)) { throw new SecretProviderException('secret_storage_failed'); }
    }
}
