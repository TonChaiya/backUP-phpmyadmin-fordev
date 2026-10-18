<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use BackupApp\Database\FileDatabaseOperationLock;

$options = getopt('', ['legacy:', 'shared:']);
$legacyPath = is_string($options['legacy'] ?? null) ? $options['legacy'] : '';
$sharedPath = is_string($options['shared'] ?? null) ? $options['shared'] : '';
if ($legacyPath === '' || $sharedPath === '' || $legacyPath === $sharedPath) {
    fwrite(STDERR, "Usage: php check-database-operation-lock-migration.php --legacy=<exact-path> --shared=<exact-path>\n");
    exit(2);
}

$legacy = new FileDatabaseOperationLock($legacyPath);
$shared = new FileDatabaseOperationLock($sharedPath);
$legacyHandle = $legacy->acquire('migration-check-legacy', false);
if ($legacyHandle === null) { echo "busy:legacy\n"; exit(3); }
try {
    $sharedHandle = $shared->acquire('migration-check-shared', false);
    if ($sharedHandle === null) { echo "busy:shared\n"; exit(4); }
    try { echo "quiescent\n"; }
    finally { $shared->release($sharedHandle); }
} finally { $legacy->release($legacyHandle); }
