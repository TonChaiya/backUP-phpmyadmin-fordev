<?php

declare(strict_types=1);

use BackupApp\Backup\BackupService;
use BackupApp\Backup\BackupJobAccess;
use BackupApp\Backup\BackupJobStore;
use BackupApp\Database\ConnectionFactory;
use BackupApp\Database\DatabaseRepository;
use BackupApp\Http\Controller\BackupController;
use BackupApp\Http\Controller\SettingsController;
use BackupApp\Http\Router;
use BackupApp\Restore\BackupProvenanceIndex;
use BackupApp\Restore\RestoreArtifactValidator;
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
$auth = new Auth();
$remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
if (!$auth->isAuthorized($remoteAddress)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Direct loopback access only.';
    exit;
}
$csrfToken = Security::csrfToken();

function redirectTo(string $page = 'dashboard'): never
{
    $location = 'index.php?page=' . rawurlencode($page);
    header('Location: ' . $location, true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = Request::postString('action');

    if (!Security::validateCsrf(isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
        Flash::add('error', 'คำขอหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่');
        redirectTo('dashboard');
    }

    if ($action === 'logout') {
        $auth->logout();
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
            new DatabaseRepository()
        );

        if ($action === 'connect') {
            $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
            $port = max(1, min(65535, (int) Request::postString('port', '3306')));
            $backupController->connect(
                Request::postString('host', '127.0.0.1'),
                $port,
                Request::postString('username', 'backup_reader'),
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
        Flash::add('error', $action === 'connect'
            ? 'เชื่อมต่อไม่สำเร็จ: ใช้บัญชี backup_reader ที่ 127.0.0.1:3306 และรหัส MySQL ของบัญชีนี้ หากไม่มีรหัสให้ติดต่อผู้ดูแล ระบบไม่รีเซ็ตหรือเพิ่มสิทธิ์อัตโนมัติ'
            : 'ไม่สามารถดำเนินการได้ กรุณาตรวจสอบการตั้งค่าหรือติดต่อผู้ดูแลระบบ');
        redirectTo(in_array($action, ['connect', 'backup', 'backup_all'], true) ? 'backup' : 'settings');
    }
}

try {
    $pathPolicy = new BackupPathPolicy($config['allowed_backup_roots'], dirname(__DIR__));
    $settingsController = new SettingsController($pathPolicy);
    $activeBackupPath = $settingsController->activePath((string) $config['backup_path']);
    $backupService = new BackupService($activeBackupPath);
    $backupController = new BackupController(
        new ConnectionFactory($config),
        new DatabaseRepository()
    );
    $backupFiles = array_slice($backupService->listBackupFiles(), 0, (int) $config['max_backup_files']);
    $backupProvenance = new BackupProvenanceIndex((string) $config['job_path']);
    $restoreArtifacts = new RestoreArtifactValidator(
        $activeBackupPath,
        $backupProvenance
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
        $dumpMetadata = $backupService->mysqldumpHistoryMetadata(
            (string) $backupFile['relative_path'],
            $backupProvenance->find((string) $backupFile['relative_path'])
        );
        if ($dumpMetadata !== null) {
            $backupFile = array_merge($backupFile, $dumpMetadata);
            try {
                (new \BackupApp\Import\ImportArtifactVerifier($activeBackupPath, (string) $config['job_path']))->inspect((string) $backupFile['relative_path']);
                $backupFile['import_status'] = 'ready';
            } catch (Throwable) { $backupFile['import_status'] = 'unsupported'; }
        }
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

foreach ($backupFiles as &$file) {
    $relativePath = (string) $file['relative_path'];
    $file['download_url'] = 'download.php?file=' . rawurlencode($relativePath)
        . '&token=' . rawurlencode($downloadAuthorization->token($relativePath));
}
unset($file);

$common = [
    'csrfToken' => $csrfToken,
    'flashMessages' => Flash::pull(),
];
$connectionData = $backupController->connectionData();
$backupJobAccess = new BackupJobAccess();
$recentBackupJobs = [];

try {
    $backupJobStore = new BackupJobStore((string) $config['job_path']);
    foreach (array_slice($backupJobAccess->ownedJobIds(), 0, 5) as $jobId) {
        try {
            $recentBackupJobs[] = $backupJobAccess->publicView($backupJobStore->read($jobId));
        } catch (Throwable) {
            // Stale or corrupt historical entries are omitted from presentation.
        }
    }

} catch (Throwable $exception) {
    $logger->exception($exception, Security::connectionPasswordSecrets([], $_SESSION));
}

$importArtifacts = $route['name'] === 'import'
    ? (new \BackupApp\Import\ImportArtifactVerifier($activeBackupPath, (string) $config['job_path']))->catalog()
    : [];
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
    ],
    'backup' => [
        'pageTitle' => 'Export',
        'heading' => 'Manual Export',
        'description' => 'เลือกหนึ่งฐาน หลายฐาน หรือทุกฐาน ระบบจะทำงานตามลำดับทีละฐาน',
        'connectionData' => $connectionData,
        'databases' => $databases,
        'isConnected' => isset($_SESSION['connection']),
        'activeJobId' => (new BackupJobAccess())->activeJobId(),
        'pageScript' => 'assets/backup-jobs.js',
    ],
    'history' => [
        'pageTitle' => 'History',
        'heading' => 'Export History',
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
        'authMode' => 'loopback_only',
        'runtimeInfo' => [
            'php_version' => PHP_VERSION,
            'environment' => PHP_OS_FAMILY,
        ],
    ],
    'import' => [
        'pageTitle' => 'Import',
        'heading' => 'Import into a new database',
        'description' => 'ตรวจไฟล์จริงและใช้ mysql.exe เฉพาะฐานทดลองใหม่ที่มีสิทธิ์แยกจากฐานต้นทาง',
        'importArtifacts' => $importArtifacts,
        'selectedImportArtifact' => is_string($_GET['artifact'] ?? null) ? $_GET['artifact'] : '',
        'activeImportJobId' => is_string($_SESSION['local_import_active'] ?? null) ? $_SESSION['local_import_active'] : '',
        'pageScript' => 'assets/local-import.js',
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
