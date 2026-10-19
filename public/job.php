<?php

declare(strict_types=1);

use BackupApp\Backup\BackupExecutionGuard;
use BackupApp\Backup\BackupJobAccess;
use BackupApp\Backup\BackupJobRunner;
use BackupApp\Backup\BackupJobRequestException;
use BackupApp\Backup\BackupJobStore;
use BackupApp\Database\FileDatabaseOperationLock;
use BackupApp\Backup\BackupService;
use BackupApp\Backup\MysqliBackupOperation;
use BackupApp\Database\ConnectionFactory;
use BackupApp\Database\DatabaseRepository;
use BackupApp\Http\Controller\SettingsController;
use BackupApp\Support\Auth;
use BackupApp\Support\BackupPathPolicy;
use BackupApp\Support\Logger;
use BackupApp\Support\Request;
use BackupApp\Support\Security;

$config = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

/** @param array<string, mixed> $payload */
function jobJson(array $payload, int $status = 200): never
{
    if ($status === 419) {
        $protocol = is_string($_SERVER['SERVER_PROTOCOL'] ?? null)
            && preg_match('/^HTTP\/\d(?:\.\d)?$/', $_SERVER['SERVER_PROTOCOL']) === 1
            ? $_SERVER['SERVER_PROTOCOL']
            : 'HTTP/1.1';
        header($protocol . ' 419 Authentication Timeout');
    } else {
        http_response_code($status);
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$logger = new Logger((string) $config['log_path']);
$secrets = Security::connectionPasswordSecrets($_POST, $_SESSION);

try {
    $auth = new Auth();
    $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    if (!$auth->isAuthorized($remoteAddress)) {
        jobJson(['ok' => false, 'error' => 'ไม่อนุญาตให้เข้าถึง'], 403);
    }

    $access = new BackupJobAccess();
    $store = new BackupJobStore((string) $config['job_path']);
    $guard = new BackupExecutionGuard(
        (string) $config['job_path'],
        new FileDatabaseOperationLock((string) $config['database_operation_lock_path'])
    );
    $runner = new BackupJobRunner($store, $guard, $logger);
    $action = $_SERVER['REQUEST_METHOD'] === 'GET'
        ? (is_string($_GET['action'] ?? null) ? trim($_GET['action']) : '')
        : Request::postString('action');

    if ($action === 'status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $statusCsrf = is_string($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : null;

        if (!Security::validateCsrf($statusCsrf)) {
            jobJson(['ok' => false, 'error' => 'คำขอหมดอายุหรือไม่ถูกต้อง'], 419);
        }

        $jobId = is_string($_GET['job_id'] ?? null) ? trim($_GET['job_id']) : '';
        $access->assertOwned($jobId);
        jobJson(['ok' => true, 'job' => $access->publicView($runner->recoverInterrupted($jobId))]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jobJson(['ok' => false, 'error' => 'ไม่รองรับคำขอนี้'], 405);
    }

    $csrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : null;

    if (!Security::validateCsrf($csrf)) {
        jobJson(['ok' => false, 'error' => 'คำขอหมดอายุหรือไม่ถูกต้อง'], 419);
    }

    if ($action === 'create') {
        $known = is_array($_SESSION['databases'] ?? null) ? array_values($_SESSION['databases']) : [];

        if (!is_array($_SESSION['connection'] ?? null)) {
            jobJson(['ok' => false, 'error' => 'กรุณาเชื่อมต่อฐานข้อมูลก่อน'], 409);
        }

        if ($access->currentConnectionContext() === null) {
            $access->rotateConnectionContext();
        }

        $selected = $access->select(Request::postArray('databases'), Request::postString('all') === '1', $known);
        $activeId = $access->activeJobId();

        if ($activeId !== null) {
            if (!$store->exists($activeId)) {
                $access->clearActive($activeId);
                $activeId = null;
            }
        }

        if ($activeId !== null) {
            $active = $access->matchesCurrentConnection($activeId)
                ? $runner->recoverInterrupted($activeId)
                : $runner->interruptQueued(
                    $activeId,
                    'connection_changed',
                    'การเชื่อมต่อเปลี่ยนหลังสร้างงาน งานนี้จะไม่ทำต่ออัตโนมัติ'
                );

            if (in_array($active['status'], ['queued', 'running'], true)) {
                jobJson(['ok' => false, 'error' => 'มีงานสำรองที่ยังไม่เสร็จอยู่แล้ว'], 409);
            }
        }

        $job = $store->create($selected);
        $access->register($job['id']);
        jobJson(['ok' => true, 'job' => $access->publicView($job)], 201);
    }

    if ($action === 'execute') {
        $jobId = Request::postString('job_id');
        $access->assertOwned($jobId);

        if (!$access->matchesCurrentConnection($jobId)) {
            if ($store->exists($jobId)) {
                $runner->interruptQueued(
                    $jobId,
                    'connection_changed',
                    'การเชื่อมต่อเปลี่ยนหลังสร้างงาน งานนี้จะไม่ทำต่ออัตโนมัติ'
                );
            }

            throw new BackupJobRequestException('connection ของงานนี้เปลี่ยนไป กรุณาสร้างงานใหม่', 409);
        }

        $connection = $_SESSION['connection'] ?? null;

        if (!is_array($connection)) {
            jobJson(['ok' => false, 'error' => 'ไม่พบข้อมูลการเชื่อมต่อ'], 409);
        }

        $connectionData = [
            'host' => (string) ($connection['host'] ?? ''),
            'port' => (int) ($connection['port'] ?? 3306),
            'username' => (string) ($connection['username'] ?? ''),
            'password' => (string) ($connection['password'] ?? ''),
        ];
        $executionJob = $store->read($jobId);
        $requestedDatabases = array_values(array_map(
            static fn (array $database): string => (string) ($database['name'] ?? ''),
            is_array($executionJob['databases'] ?? null) ? $executionJob['databases'] : []
        ));
        $secrets = array_values(array_filter([$connectionData['password']], static fn (string $value): bool => $value !== ''));
        $pathPolicy = new BackupPathPolicy($config['allowed_backup_roots'], dirname(__DIR__));
        $activeBackupPath = (new SettingsController($pathPolicy))->activePath((string) $config['backup_path']);
        $operation = new MysqliBackupOperation(
            new ConnectionFactory($config),
            new DatabaseRepository(),
            new BackupService($activeBackupPath),
            $connectionData,
            $requestedDatabases
        );

        session_write_close();
        ignore_user_abort(true);
        @set_time_limit(0);
        $job = $runner->run($jobId, $operation, $secrets);
        jobJson(['ok' => true, 'job' => $access->publicView($job)]);
    }

    jobJson(['ok' => false, 'error' => 'ไม่รู้จักคำสั่งที่ร้องขอ'], 400);
} catch (BackupJobRequestException $exception) {
    jobJson(['ok' => false, 'error' => $exception->getMessage()], $exception->httpStatus());
} catch (Throwable $exception) {
    $logger->exception($exception, $secrets);
    jobJson(['ok' => false, 'error' => 'ไม่สามารถดำเนินการงานสำรองได้'], 500);
}
