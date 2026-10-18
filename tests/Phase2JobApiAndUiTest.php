<?php

declare(strict_types=1);

use BackupApp\Backup\BackupJobAccess;
use BackupApp\Support\View;

test('Phase2JobAccess authorizes only session-owned unpredictable job IDs', function (): void {
    $access = new BackupJobAccess();
    $owned = str_repeat('a', 32);
    $foreign = str_repeat('b', 32);
    $firstContext = $access->rotateConnectionContext();
    $access->register($owned);

    assertTrueValue($access->owns($owned));
    assertTrueValue($access->matchesCurrentConnection($owned));
    assertFalseValue($access->owns($foreign));
    assertSameValue($owned, $access->activeJobId());
    assertTrueValue(preg_match('/^[a-f0-9]{32}$/', $firstContext) === 1);
    assertFalseValue(hash_equals($firstContext, $access->rotateConnectionContext()));
    assertFalseValue($access->matchesCurrentConnection($owned));

    $failed = false;
    try {
        $access->assertOwned($foreign);
    } catch (RuntimeException) {
        $failed = true;
    }
    assertTrueValue($failed);
});

test('Phase2JobAccess restricts requested databases to the connected session allowlist', function (): void {
    $access = new BackupJobAccess();
    assertSameValue(['beta', 'alpha'], $access->select(['beta', 'unknown', 'alpha', 'beta'], false, ['alpha', 'beta']));
    assertSameValue(['alpha', 'beta'], $access->select([], true, ['alpha', 'beta']));

    $failed = false;
    try {
        $access->select(['unknown'], false, ['alpha', 'beta']);
    } catch (RuntimeException) {
        $failed = true;
    }
    assertTrueValue($failed);
});

test('Phase2JobAccess response omits claims and any unexpected secret fields', function (): void {
    $access = new BackupJobAccess();
    $view = $access->publicView([
        'id' => str_repeat('a', 32),
        'status' => 'queued',
        'created_at' => '2026-10-16T00:00:00+07:00',
        'updated_at' => '2026-10-16T00:00:00+07:00',
        'execution_claimed_at' => null,
        'revision' => 1,
        'password' => 'SENTINEL-PASSWORD',
        'summary' => ['queued' => 1, 'running' => 0, 'succeeded' => 0, 'failed' => 0, 'interrupted' => 0, 'total' => 1],
        'databases' => [[
            'name' => 'alpha',
            'status' => 'queued',
            'started_at' => null,
            'finished_at' => null,
            'artifact' => [
                'filename' => 'alpha.sql',
                'relative_path' => 'alpha/alpha.sql',
                'bytes' => 1,
                'sha256' => str_repeat('a', 64),
                'manifest_path' => 'alpha/alpha.sql.json',
                'password' => 'SENTINEL-ARTIFACT-PASSWORD',
            ],
            'error' => null,
        ]],
    ]);
    $encoded = (string) json_encode($view);

    assertTrueValue($view['can_execute']);
    assertNotContainsValue('execution_claimed_at', $encoded);
    assertNotContainsValue('SENTINEL-PASSWORD', $encoded);
    assertNotContainsValue('SENTINEL-ARTIFACT-PASSWORD', $encoded);
});

test('Phase2 backup UI uses persisted state polling without fake percentage', function (): void {
    $html = View::render('backup', [
        'pageTitle' => 'Backup',
        'heading' => 'Backup',
        'description' => 'Manual backup',
        'csrfToken' => 'csrf-token',
        'connectionData' => ['host' => '127.0.0.1', 'port' => 3306, 'username' => 'root', 'password' => 'SENTINEL'],
        'databases' => ['alpha', 'beta'],
        'isConnected' => true,
        'activeJobId' => str_repeat('a', 32),
        'pageScript' => 'assets/backup-jobs.js',
    ], 'backup', ['app_name' => 'Backup Manager']);

    foreach (['queued', 'running', 'succeeded', 'failed', 'interrupted'] as $state) {
        assertContainsValue($state, $html);
    }
    assertContainsValue('data-job-api="job.php"', $html);
    assertContainsValue('assets/backup-jobs.js', $html);
    assertNotContainsValue('SENTINEL', $html);
    assertNotContainsValue('progress', strtolower($html));
    assertNotContainsValue('%', $html);

    $javascript = (string) file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'backup-jobs.js');
    assertContainsValue('setTimeout', $javascript);
    assertContainsValue('job.php', $javascript);
    assertNotContainsValue('percent', strtolower($javascript));
});

test('Phase2 job endpoint enforces Auth CSRF and ownership gates in source contract', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'job.php');

    assertContainsValue('isAuthorized', $source);
    assertContainsValue('validateCsrf', $source);
    assertContainsValue('HTTP_X_CSRF_TOKEN', $source);
    assertContainsValue('assertOwned', $source);
    assertContainsValue('matchesCurrentConnection', $source);
    assertContainsValue('session_write_close', $source);
    assertContainsValue('ignore_user_abort', $source);

    $rootRules = (string) file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.htaccess');
    assertContainsValue('PHASE[0-9]+_', $rootRules);
    assertContainsValue('BackupJobRequestException', $source);
    assertContainsValue("], 500)", $source);
});
