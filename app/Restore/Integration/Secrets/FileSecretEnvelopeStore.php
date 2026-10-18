<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Secrets;

final class FileSecretEnvelopeStore extends AtomicFileSecretEnvelopeStore
{
    public function __construct(string $projectRoot, WindowsAclHardener $acl)
    {
        if ($projectRoot === '' || !is_dir($projectRoot)) {
            throw new SecretProviderException('secret_storage_failed');
        }
        parent::__construct(
            rtrim($projectRoot, '\\/') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR
                . 'private' . DIRECTORY_SEPARATOR . 'restore-integration-secrets',
            $acl,
        );
    }
}
