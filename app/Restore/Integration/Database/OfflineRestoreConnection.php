<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Database;

/** Marker interface restricted to fake/recording adapters used by offline tests. */
interface OfflineRestoreConnection extends RestoreConnection
{
}
