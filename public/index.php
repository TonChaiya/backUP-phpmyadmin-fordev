<?php

declare(strict_types=1);

use BackupApp\Backup\BackupService;
use BackupApp\Backup\BackupJobAccess;
use BackupApp\Backup\BackupJobStore;
use BackupApp\Database\ConnectionFactory;
use BackupApp\Database\DatabaseRepository;
use BackupApp\Http\Controller\AuthController;
use BackupApp\Http\Controller\BackupController;
use BackupApp\Http\Controller\SettingsController;
use BackupApp\Http\Router;
use BackupApp\Restore\BackupProvenanceIndex;
use BackupApp\Restore\RestoreArtifactValidator;
use BackupApp\Restore\RestoreJobAccess;
use BackupApp\Restore\RestoreJobStore;
use BackupApp\Restore\RestoreSafetyGate;
use BackupApp\Restore\Integration\Job\RestoreIntegrationJobAccess;
use BackupApp\Restore\Integration\Policy\RestoreIntegrationPolicy;
use BackupApp\Support\Auth;
use BackupApp\Support\BackupPathPolicy;
use BackupApp\Support\DownloadAuthorization;
use BackupApp\Support\Flash;
use BackupApp\Support\Logger;
use BackupApp\Support\Request;
use BackupApp\Support\Security;
use BackupApp\Support\Session;
use BackupApp\Support\View;

$config = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");

$logger = new Logger((string) $config['log_path']);
$auth = new Auth($config['auth']);
$authController = new AuthController($auth);
$remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$csrfToken = Security::csrfToken();

function redirectTo(string $page = 'dashboard'): never
{
    $location = $page === 'login' ? 'index.php' : 'index.php?page=' . rawurlencode($page);
    header('Location: ' . $location, true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = Request::postString('action');

    if (!Security::validateCsrf(isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
        Flash::add('error', 'คำขอหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่');
        redirectTo($auth->isAuthorized($remoteAddress) ? 'dashboard' : 'login');
    }

    if ($action === 'login') {
        $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';

        if ($authController->login(Request::postString('username'), $password)) {
            Flash::add('success', 'เข้าสู่ระบบสำเร็จ');
            redirectTo('dashboard');
        }

        Flash::add('error', 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
        redirectTo('login');
    }

    if (!$auth->isAuthorized($remoteAddress)) {
        http_response_code(403);
        echo View::render('placeholder', [
            'pageTitle' => 'Access denied',
            'heading' => 'ไม่อนุญาตให้เข้าถึง',
            'description' => 'ตั้งค่า Administrator authentication ก่อนใช้งานจาก LAN หรือ Remote',
            'placeholderTitle' => 'Access denied',
            'placeholderMessage' => 'ระบบอนุญาตโหมดที่ยังไม่ตั้งรหัสผ่านเฉพาะ direct loopback เท่านั้น',
            'flashMessages' => Flash::pull(),
        ], 'not-found', $config, false);
        exit;
    }

    if ($action === 'logout') {
        $authController->logout();
        Session::destroy();
        header('Location: index.php', true, 303);
        exit;
    }

    try {
        $pathPolicy = new BackupPathPolicy($config['allowed_backup_roots'], dirname(__DIR__));
        $settingsController = new SettingsController($pathPolicy);
        $activeBackupPath = $settingsController->activePath((string) $config['backup_path']);
        $backupController = new BackupController(
            new ConnectionFactory($config),
            new DatabaseRepository(),
            new BackupService($activeBackupPath),
            $config
        );

        if ($action === 'connect') {
            $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
            $port = max(1, min(65535, (int) Request::postString('port', '3306')));
            $backupController->connect(
                Request::postString('host', '127.0.0.1'),
                $port,
                Request::postString('username', 'root'),
                $password
            );
            redirectTo('backup');
        }

        if ($action === 'backup' || $action === 'backup_all') {
            Flash::add('warning', 'งานสำรองต้องเริ่มจากหน้าสถานะ Backup Job');
            redirectTo('backup');
        }

        if ($action === 'set_backup_path' || $action === 'open_backup_folder') {
            $resolved = $settingsController->setPath(Request::postString('backup_path'));

            if ($action === 'set_backup_path') {
                Flash::add('success', 'บันทึกตำแหน่งจัดเก็บไฟล์สำรองแล้ว');
            } elseif ($settingsController->openFolder($resolved)) {
                Flash::add('success', 'เปิดโฟลเดอร์จัดเก็บไฟล์สำรองแล้ว');
            } else {
                Flash::add('warning', 'ไม่สามารถเปิดโฟลเดอร์อัตโนมัติได้ กรุณาเปิดจาก path ที่แสดงไว้');
            }

            redirectTo('settings');
        }

        Flash::add('warning', 'ไม่รู้จักคำสั่งที่ร้องขอ');
        redirectTo('dashboard');
    } catch (Throwable $exception) {
        $logger->exception($exception, Security::connectionPasswordSecrets($_POST, $_SESSION));
        Flash::add('error', 'ไม่สามารถดำเนินการได้ กรุณาตรวจสอบการตั้งค่าหรือติดต่อผู้ดูแลระบบ');
        redirectTo(in_array($action, ['connect', 'backup', 'backup_all'], true) ? 'backup' : 'settings');
    }
}

if (!$auth->isAuthorized($remoteAddress)) {
    if (!$auth->isConfigured()) {
        http_response_code(403);
        echo View::render('placeholder', [
            'pageTitle' => 'Authentication required',
            'heading' => 'ต้องตั้งค่า Authentication',
            'description' => 'Remote access ถูกปิดไว้โดยค่าเริ่มต้น',
            'placeholderTitle' => 'อนุญาตเฉพาะ Direct Loopback',
            'placeholderMessage' => 'คัดลอก config/local.php.example เป็น config/local.php แล้วตั้งค่า password hash ก่อนใช้งานจาก LAN',
            'flashMessages' => Flash::pull(),
        ], 'not-found', $config, false);
        exit;
    }

    echo View::render('login', [
        'pageTitle' => 'Login',
        'heading' => 'Database Backup Manager',
        'description' => 'เข้าสู่ระบบด้วยบัญชีผู้ดูแลที่กำหนดใน Local Configuration',
        'csrfToken' => $csrfToken,
        'flashMessages' => Flash::pull(),
    ], 'login', $config, false);
    exit;
}

try {
    $pathPolicy = new BackupPathPolicy($config['allowed_backup_roots'], dirname(__DIR__));
    $settingsController = new SettingsController($pathPolicy);
    $activeBackupPath = $settingsController->activePath((string) $config['backup_path']);
    $backupService = new BackupService($activeBackupPath);
    $backupController = new BackupController(
        new ConnectionFactory($config),
        new DatabaseRepository(),
        $backupService,
        $config
    );
    $backupFiles = array_slice($backupService->listBackupFiles(), 0, (int) $config['max_backup_files']);
    $restoreArtifacts = new RestoreArtifactValidator(
        $activeBackupPath,
        new BackupProvenanceIndex((string) $config['job_path'])
    );
    foreach ($backupFiles as &$backupFile) {
        $inspection = $restoreArtifacts->inspect((string) $backupFile['relative_path']);
        $backupFile['restore_status'] = (string) $inspection['status'];
        $backupFile['database'] = is_string($inspection['database'] ?? null)
            ? $inspection['database']
            : (string) ($backupFile['folder'] ?? 'ไม่ทราบ');
        $backupFile['integrity_status'] = in_array($inspection['status'], ['candidate', 'unverified_origin'], true)
            ? 'not_checked'
            : 'unavailable';
        $backupFile['provenance_status'] = $inspection['status'] === 'candidate'
            ? 'verified'
            : ($inspection['status'] === 'unverified_origin' ? 'unverified_origin' : 'unavailable');
        $backupFile['restore_eligibility'] = $inspection['status'] === 'candidate'
            ? 'preflight_required'
            : 'ineligible';
    }
    unset($backupFile);
} catch (Throwable $exception) {
    $logger->exception($exception, Security::connectionPasswordSecrets([], $_SESSION));
    http_response_code(500);
    echo View::render('placeholder', [
        'pageTitle' => 'Configuration error',
        'heading' => 'ไม่สามารถเริ่มระบบได้',
        'description' => 'กรุณาตรวจสอบ Local Configuration และ Server Log',
        'placeholderTitle' => 'การตั้งค่า Storage ไม่พร้อมใช้งาน',
        'placeholderMessage' => 'ระบบหยุดก่อนเข้าถึงไฟล์หรือฐานข้อมูลเพื่อความปลอดภัย',
    ], 'not-found', $config, false);
    exit;
}

$route = Router::resolve(isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : null);
$databases = $backupController->databases();
$downloadAuthorization = new DownloadAuthorization($csrfToken);
$restoreCandidates = array_values(array_filter(
    array_map(static fn (array $file): array => [
        'name' => (string) $file['name'],
        'relative_path' => (string) $file['relative_path'],
        'status' => (string) ($file['restore_status'] ?? 'not_checked'),
    ], $backupFiles),
    static fn (array $file): bool => $file['status'] === 'candidate'
));

foreach ($backupFiles as &$file) {
    $relativePath = (string) $file['relative_path'];
    $file['download_url'] = 'download.php?file=' . rawurlencode($relativePath)
        . '&token=' . rawurlencode($downloadAuthorization->token($relativePath));
}
unset($file);

$common = [
    'csrfToken' => $csrfToken,
    'flashMessages' => Flash::pull(),
    'authConfigured' => $auth->isConfigured(),
];
$connectionData = $backupController->connectionData();
$backupJobAccess = new BackupJobAccess();
$restoreJobAccess = new RestoreJobAccess();
$recentBackupJobs = [];
$recentRestoreJobs = [];

try {
    $backupJobStore = new BackupJobStore((string) $config['job_path']);
    foreach (array_slice($backupJobAccess->ownedJobIds(), 0, 5) as $jobId) {
        try {
            $recentBackupJobs[] = $backupJobAccess->publicView($backupJobStore->read($jobId));
        } catch (Throwable) {
            // Stale or corrupt historical entries are omitted from presentation.
        }
    }

    $restoreJobStore = new RestoreJobStore((string) $config['restore_job_path']);
    foreach (array_slice($restoreJobAccess->ownedJobIds(), 0, 5) as $jobId) {
        try {
            $recentRestoreJobs[] = $restoreJobAccess->publicView($restoreJobStore->read($jobId));
        } catch (Throwable) {
            // Stale or corrupt historical entries are omitted from presentation.
        }
    }
} catch (Throwable $exception) {
    $logger->exception($exception, Security::connectionPasswordSecrets([], $_SESSION));
}

$restoreSafety = (new RestoreSafetyGate())->evaluate();
$pageData = match ($route['name']) {
    'dashboard' => [
        'pageTitle' => 'Dashboard',
        'heading' => 'Dashboard',
        'description' => 'ภาพรวมการสำรองฐานข้อมูลแบบ Manual',
        'databaseCount' => count($databases),
        'backupCount' => count($backupFiles),
        'latestBackupLabel' => isset($backupFiles[0]) ? date('Y-m-d H:i', (int) $backupFiles[0]['modified']) : 'ยังไม่มี',
        'isConnected' => isset($_SESSION['connection']),
        'recentBackupJobs' => $recentBackupJobs,
        'restoreSafety' => $restoreSafety,
    ],
    'backup' => [
        'pageTitle' => 'Backup',
        'heading' => 'Manual Backup',
        'description' => 'เลือกหนึ่งฐาน หลายฐาน หรือทุกฐาน ระบบจะทำงานตามลำดับทีละฐาน',
        'connectionData' => $connectionData,
        'databases' => $databases,
        'isConnected' => isset($_SESSION['connection']),
        'activeJobId' => (new BackupJobAccess())->activeJobId(),
        'pageScript' => 'assets/backup-jobs.js',
    ],
    'history' => [
        'pageTitle' => 'History',
        'heading' => 'Backup History',
        'description' => 'ไฟล์ SQL ที่ระบบตรวจพบภายใต้ Storage Root ที่ได้รับอนุญาต',
        'backupFiles' => $backupFiles,
    ],
    'settings' => [
        'pageTitle' => 'Settings',
        'heading' => 'Settings',
        'description' => 'กำหนดตำแหน่งจัดเก็บภายใต้ Allowlist ที่ผู้ดูแลระบบอนุญาต',
        'activeBackupPath' => $activeBackupPath,
        'connectionData' => $connectionData,
        'isConnected' => isset($_SESSION['connection']),
        'authMode' => $auth->isConfigured() ? 'configured' : 'loopback_only',
        'runtimeInfo' => [
            'php_version' => PHP_VERSION,
            'environment' => PHP_OS_FAMILY,
        ],
    ],
    'restore' => [
        'pageTitle' => 'Restore',
        'heading' => 'Restore Preflight',
        'description' => 'ตรวจ provenance, SHA-256, SQL structure และ safety gates แบบ Fail-Closed โดยไม่มีการส่งคำสั่งไปยังฐานข้อมูล',
        'restoreCandidates' => $restoreCandidates,
        'selectedRestoreArtifact' => isset($_GET['artifact']) && is_string($_GET['artifact']) ? $_GET['artifact'] : '',
        'activeRestoreJobId' => $restoreJobAccess->activeJobId(),
        'recentRestoreJobs' => $recentRestoreJobs,
        'restoreSafety' => $restoreSafety,
        'pageScript' => 'assets/restore-jobs.js',
    ],
    'restore-integration' => [
        'pageTitle' => 'Restore Integration',
        'heading' => 'Restore Integration',
        'description' => 'Offline proposal และ safety state สำหรับ Milestones 1–9 โดย Live execution ปิดอยู่',
        'authorizedSources' => (new RestoreIntegrationPolicy())->authorizedSources(),
        'activeIntegrationJobId' => (new RestoreIntegrationJobAccess())->owns((string) ($_SESSION['active_restore_integration_job_id'] ?? ''))
            ? (string) $_SESSION['active_restore_integration_job_id'] : '',
        'pageScript' => 'assets/restore-integration.js',
    ],
    default => [
        'pageTitle' => 'Page not found',
        'heading' => 'ไม่พบหน้าที่ร้องขอ',
        'description' => 'ระบบอนุญาตเฉพาะ route ที่กำหนดไว้เท่านั้น',
        'placeholderTitle' => '404',
        'placeholderMessage' => 'ตรวจสอบ URL หรือเลือกหน้าจากเมนูหลัก',
    ],
};

http_response_code($route['status']);
echo View::render($route['view'], array_merge($common, $pageData), $route['name'], $config);
