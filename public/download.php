<?php

declare(strict_types=1);

use BackupApp\Backup\BackupService;

$config = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

$backupPath = $_SESSION['backup_path'] ?? $config['backup_path'];
$service = new BackupService((string) $backupPath);
$filename = isset($_GET['file']) && is_string($_GET['file']) ? $_GET['file'] : '';
$path = $service->resolveBackupPath($filename);

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . basename($path) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');

readfile($path);
