<?php

declare(strict_types=1);

use BackupApp\Backup\BackupService;
use BackupApp\Support\Auth;
use BackupApp\Support\BackupPathPolicy;
use BackupApp\Support\DownloadAuthorization;
use BackupApp\Support\Logger;
use BackupApp\Support\Security;

$config = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
$logger = new Logger((string) $config['log_path']);
$auth = new Auth();
$remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

if (!$auth->isAuthorized($remoteAddress, (string) ($_SERVER['HTTP_HOST'] ?? ''))) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo 'Access denied.';
    exit;
}

try {
    $pathPolicy = new BackupPathPolicy($config['allowed_backup_roots'], dirname(__DIR__));
    $requestedRoot = is_string($_SESSION['backup_path'] ?? null)
        ? $_SESSION['backup_path']
        : (string) $config['backup_path'];

    try {
        $backupPath = $pathPolicy->resolve($requestedRoot);
    } catch (Throwable) {
        $backupPath = $pathPolicy->defaultRoot();
        unset($_SESSION['backup_path']);
    }

    $service = new BackupService($backupPath);
    $filename = isset($_GET['file']) && is_string($_GET['file']) ? $_GET['file'] : '';
    $token = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';
    $authorization = new DownloadAuthorization(Security::csrfToken());

    if (!$authorization->authorize($filename, $token, $service->listBackupFiles())) {
        throw new RuntimeException('Download authorization failed');
    }

    $path = $service->resolveBackupPath($filename);

    if (!$authorization->verifyArtifact($path)) {
        throw new RuntimeException('Backup artifact verification failed');
    }
} catch (Throwable $exception) {
    $logger->exception($exception, Security::connectionPasswordSecrets([], $_SESSION));
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo 'Backup file is not available.';
    exit;
}

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . basename($path) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');

readfile($path);
