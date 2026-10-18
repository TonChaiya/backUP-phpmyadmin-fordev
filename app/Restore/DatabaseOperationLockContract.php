<?php

declare(strict_types=1);

namespace BackupApp\Restore;

use BackupApp\Database\DatabaseOperationLock;

/**
 * Future contract only. Any database-mutating restore executor must share an
 * exclusive host-local implementation with backup operations before it can
 * connect. Phase 3 intentionally provides no implementation or wiring.
 */
interface DatabaseOperationLockContract extends DatabaseOperationLock
{
}
