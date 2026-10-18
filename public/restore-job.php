<?php

declare(strict_types=1);

use BackupApp\Http\Controller\SettingsController;
use BackupApp\Restore\BackupProvenanceIndex;
use BackupApp\Restore\RestoreArtifactValidator;
use BackupApp\Restore\RestoreExecutionGuard;
use BackupApp\Restore\RestoreJobAccess;
use BackupApp\Restore\RestoreJobRequestException;
use BackupApp\Restore\RestoreJobStore;
use BackupApp\Restore\RestorePreflightRunner;
use BackupApp\Restore\RestoreSafetyGate;
use BackupApp\Restore\RestoreTargetValidator;
use BackupApp\Restore\StructuralSqlParser;
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
function restoreJobJson(array $payload, int $status = 200): never
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

try {
    $auth = new Auth($config['auth']);
    if (!$auth->isAuthorized((string) ($_SERVER['REMOTE_ADDR'] ?? ''))) {
        restoreJobJson(['ok' => false, 'error' => 'ไม่อนุญาตให้เข้าถึง'], 403);
    }

    $pathPolicy = new BackupPathPolicy($config['allowed_backup_roots'], dirname(__DIR__));
    $backupRoot = (new SettingsController($pathPolicy))->activePath((string) $config['backup_path']);
    $access = new RestoreJobAccess();
    $store = new RestoreJobStore((string) $config['restore_job_path']);
    $guard = new RestoreExecutionGuard((string) $config['restore_job_path']);
    $runner = new RestorePreflightRunner(
        $store,
        $guard,
        new RestoreArtifactValidator($backupRoot, new BackupProvenanceIndex((string) $config['job_path'])),
        new StructuralSqlParser(),
        new RestoreTargetValidator($config['excluded_databases']),
        new RestoreSafetyGate(),
        $logger
    );
    $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $action = $method === 'GET'
        ? (is_string($_GET['action'] ?? null) ? trim($_GET['action']) : '')
        : Request::postString('action');

    if ($action === 'status' && $method === 'GET') {
        $csrf = is_string($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : null;
        if (!Security::validateCsrf($csrf)) {
            restoreJobJson(['ok' => false, 'error' => 'คำขอหมดอายุหรือไม่ถูกต้อง'], 419);
        }
        $id = is_string($_GET['job_id'] ?? null) ? trim($_GET['job_id']) : '';
        $access->assertOwned($id);
        restoreJobJson(['ok' => true, 'job' => $access->publicView($runner->recoverInterrupted($id))]);
    }

    if ($method !== 'POST') {
        restoreJobJson(['ok' => false, 'error' => 'ไม่รองรับคำขอนี้'], 405);
    }
    $csrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : null;
    if (!Security::validateCsrf($csrf)) {
        restoreJobJson(['ok' => false, 'error' => 'คำขอหมดอายุหรือไม่ถูกต้อง'], 419);
    }

    if ($action === 'create') {
        $artifact = Request::postString('artifact');
        $target = Request::postString('target');
        $inspection = (new RestoreArtifactValidator($backupRoot, new BackupProvenanceIndex((string) $config['job_path'])))->inspect($artifact);
        if (($inspection['status'] ?? null) !== 'candidate') {
            throw new RestoreJobRequestException('ไฟล์นี้ไม่มี Phase 2 provenance ที่พร้อมสำหรับ preflight', 422);
        }

        $activeId = $access->activeJobId();
        if ($activeId !== null && $store->exists($activeId)) {
            $active = $runner->recoverInterrupted($activeId);
            if (in_array($active['status'], ['queued', 'running'], true)) {
                throw new RestoreJobRequestException('มีงานตรวจสอบ Restore ที่ยังไม่เสร็จอยู่แล้ว', 409);
            }
        }

        $job = $store->create($artifact, $target);
        $access->register($job['id']);
        restoreJobJson(['ok' => true, 'job' => $access->publicView($job)], 201);
    }

    if ($action === 'preflight') {
        $id = Request::postString('job_id');
        $access->assertOwned($id);
        session_write_close();
        ignore_user_abort(true);
        @set_time_limit(0);
        restoreJobJson(['ok' => true, 'job' => $access->publicView($runner->run($id))]);
    }

    restoreJobJson(['ok' => false, 'error' => 'ไม่รู้จักคำสั่งที่ร้องขอ'], 400);
} catch (RestoreJobRequestException $exception) {
    restoreJobJson(['ok' => false, 'error' => $exception->getMessage()], $exception->httpStatus());
} catch (Throwable $exception) {
    $logger->exception($exception, Security::connectionPasswordSecrets($_POST, $_SESSION));
    restoreJobJson(['ok' => false, 'error' => 'ไม่สามารถดำเนินการ Restore preflight ได้'], 500);
}
