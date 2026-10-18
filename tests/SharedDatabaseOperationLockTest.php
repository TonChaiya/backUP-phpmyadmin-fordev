<?php

declare(strict_types=1);

use BackupApp\Backup\BackupExecutionGuard;
use BackupApp\Database\FileDatabaseOperationLock;

test('SharedDatabaseOperationLock excludes backup and restore through one exact lock', function (): void {
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'shared-db-lock-' . bin2hex(random_bytes(6));
    mkdir($path, 0700, true);
    $sharedA = new FileDatabaseOperationLock($path . DIRECTORY_SEPARATOR . 'global.lock');
    $sharedB = new FileDatabaseOperationLock($path . DIRECTORY_SEPARATOR . 'global.lock');
    $backup = new BackupExecutionGuard($path . DIRECTORY_SEPARATOR . 'jobs', $sharedA);

    $backupHandle = $backup->acquireGlobal(false);
    assertTrueValue(is_resource($backupHandle));
    assertSameValue(null, $sharedB->acquire('restore-' . str_repeat('a', 32), false));
    $backup->release($backupHandle);

    $restoreHandle = $sharedB->acquire('restore-' . str_repeat('a', 32), false);
    assertTrueValue(is_resource($restoreHandle));
    assertSameValue(null, $backup->acquireGlobal(false));
    $sharedB->release($restoreHandle);
});

test('SharedDatabaseOperationLock rejects unsafe operation identifiers', function (): void {
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'shared-db-lock-' . bin2hex(random_bytes(6)) . '.lock';
    $lock = new FileDatabaseOperationLock($path);
    foreach (['', '../escape', 'contains space', str_repeat('a', 130)] as $id) {
        $thrown = false;
        try { $lock->acquire($id, false); } catch (DomainException) { $thrown = true; }
        assertTrueValue($thrown, "{$id} must be rejected");
    }
});
