<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Secrets;

enum SecretRole: string
{
    case BackupReader = 'backup_reader';
    case RestoreRunner = 'restore_runner';
}
