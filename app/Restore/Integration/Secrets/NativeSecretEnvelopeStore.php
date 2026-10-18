<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Secrets;

final class NativeSecretEnvelopeStore extends AtomicFileSecretEnvelopeStore
{
    private const DIRECTORY = 'C:\\ProgramData\\BackupPhpMyAdmin\\SecretProvider\\secrets';

    public function __construct(WindowsAclHardener $acl)
    {
        parent::__construct(self::DIRECTORY, $acl);
    }
}
