<?php

declare(strict_types=1);

use BackupApp\Restore\Integration\Http\RestoreIntegrationApiAuthorization;
use BackupApp\Restore\Integration\Job\RestoreIntegrationJobAccess;
use BackupApp\Restore\Integration\Job\RestoreIntegrationJobStore;
use BackupApp\Restore\Integration\Policy\RestoreIntegrationPolicy;
use BackupApp\Restore\Integration\Policy\TrustedRunId;
use BackupApp\Support\Auth;
use BackupApp\Support\Request;

$config = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

/** @param array<string, mixed> $payload */
function restoreIntegrationJson(array $payload, int $status = 200): never
{
    http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit;
}

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$action = $method === 'GET' ? (string) ($_GET['action'] ?? '') : Request::postString('action');
$csrf = $method === 'GET' ? (is_string($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : null)
    : (is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null);
$auth = new Auth($config['auth']);
$decision = (new RestoreIntegrationApiAuthorization())->authorize($auth, (string) ($_SERVER['REMOTE_ADDR'] ?? ''), $csrf, $action);
if (!$decision['allowed']) {
    $status = $decision['code'] === 'authentication_required' ? 403 : ($decision['code'] === 'csrf_invalid' ? 419 : 423);
    restoreIntegrationJson(['ok' => false, 'error' => $decision['code'], 'live_execution_enabled' => false], $status);
}

$store = new RestoreIntegrationJobStore((string) $config['restore_integration_job_path']);
$access = new RestoreIntegrationJobAccess();
if ($action === 'proposal' && $method === 'POST') {
    $run = new TrustedRunId(bin2hex(random_bytes(16))); $policy = new RestoreIntegrationPolicy(); $items = [];
    foreach ($policy->authorizedSources() as $source) {
        $items[] = ['source' => $source, 'artifact' => '', 'artifact_sha256' => '', 'target' => $policy->targetFor($source, $run)->value()];
    }
    $job = $store->create($run, $items); $access->register($job['id']);
    restoreIntegrationJson(['ok' => true, 'job' => $access->publicView($job)], 201);
}
if ($action === 'status' && $method === 'GET') {
    $id = is_string($_GET['job_id'] ?? null) ? $_GET['job_id'] : '';
    if (!$access->owns($id)) { restoreIntegrationJson(['ok' => false, 'error' => 'job_not_owned'], 404); }
    restoreIntegrationJson(['ok' => true, 'job' => $access->publicView($store->read($id))]);
}
restoreIntegrationJson(['ok' => false, 'error' => 'method_not_allowed'], 405);
