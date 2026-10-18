<?php

declare(strict_types=1);

use BackupApp\Restore\BackupProvenanceIndex;
use BackupApp\Restore\RestoreArtifactValidator;
use BackupApp\Restore\RestoreExecutionGuard;
use BackupApp\Restore\RestoreJobAccess;
use BackupApp\Restore\RestoreJobStore;
use BackupApp\Restore\RestorePreflightRunner;
use BackupApp\Restore\RestoreSafetyGate;
use BackupApp\Restore\RestoreTargetValidator;
use BackupApp\Restore\StructuralSqlParser;
use BackupApp\Restore\SqlStructureValidator;
use BackupApp\Support\Logger;

test('Phase3RestoreJob persists safe snapshots and never permits succeeded', function (): void {
    $path = phase2TempDirectory('phase3-job-store');
    try {
        $store = new RestoreJobStore($path);
        $job = $store->create('safe_db/fixture.sql', 'restore_fixture');
        assertSameValue('queued', $job['status']);
        assertNotContainsValue('password', (string) file_get_contents($store->pathFor($job['id'])));

        $failed = false;
        try {
            $store->update($job['id'], static function (array $state): array {
                $state['status'] = 'succeeded';
                return $state;
            });
        } catch (RuntimeException) {
            $failed = true;
        }
        assertTrueValue($failed, 'Phase 3 store must reject succeeded');
        assertSameValue('queued', $store->read($job['id'])['status']);

        $jumpRejected = false;
        try {
            $store->update($job['id'], static function (array $state): array {
                $state['status'] = 'blocked';
                return $state;
            });
        } catch (RuntimeException) {
            $jumpRejected = true;
        }
        assertTrueValue($jumpRejected, 'Queued job must not jump directly to blocked');
    } finally {
        phase2RemoveDirectory($path);
    }
});

test('Phase3RestoreJob valid preflight finishes blocked without execution claim', function (): void {
    $fixture = phase3ArtifactFixture('job-blocked');
    $restoreJobs = dirname($fixture['root']) . DIRECTORY_SEPARATOR . 'restore-jobs';
    try {
        $store = new RestoreJobStore($restoreJobs);
        $guard = new RestoreExecutionGuard($restoreJobs);
        $runner = new RestorePreflightRunner(
            $store,
            $guard,
            new RestoreArtifactValidator($fixture['root'], new BackupProvenanceIndex($fixture['jobs'])),
            new StructuralSqlParser(),
            new RestoreTargetValidator(['mysql', 'sys', 'information_schema', 'performance_schema']),
            new RestoreSafetyGate(),
            new Logger($restoreJobs . DIRECTORY_SEPARATOR . 'test.log')
        );
        $job = $store->create($fixture['relative'], 'restore_fixture');
        $result = $runner->run($job['id']);

        assertSameValue('blocked', $result['status']);
        assertSameValue('live_restore_not_verified', $result['result']['code']);
        assertTrueValue($result['result']['integrity_verified']);
        assertFalseValue(array_key_exists('sql_execution_started_at', $result));
        assertNotContainsValue($fixture['sql'], (string) file_get_contents($store->pathFor($job['id'])));
    } finally {
        phase2RemoveDirectory(dirname($fixture['root']));
    }
});

test('Phase3RestoreJob invalid target fails and duplicate preflight is rejected', function (): void {
    $fixture = phase3ArtifactFixture('job-failed');
    $restoreJobs = dirname($fixture['root']) . DIRECTORY_SEPARATOR . 'restore-jobs';
    try {
        $store = new RestoreJobStore($restoreJobs);
        $guard = new RestoreExecutionGuard($restoreJobs);
        $runner = new RestorePreflightRunner(
            $store,
            $guard,
            new RestoreArtifactValidator($fixture['root'], new BackupProvenanceIndex($fixture['jobs'])),
            new StructuralSqlParser(),
            new RestoreTargetValidator(['mysql', 'sys']),
            new RestoreSafetyGate(),
            new Logger($restoreJobs . DIRECTORY_SEPARATOR . 'test.log')
        );
        $job = $store->create($fixture['relative'], 'jhcisdb');
        $held = $guard->acquireJob($job['id']);
        $duplicateRejected = false;
        try {
            $runner->run($job['id']);
        } catch (RuntimeException) {
            $duplicateRejected = true;
        } finally {
            $guard->release($held);
        }
        assertTrueValue($duplicateRejected);
        assertSameValue('queued', $store->read($job['id'])['status']);

        $result = $runner->run($job['id']);
        assertSameValue('failed', $result['status']);
        assertSameValue('protected_target', $result['result']['code']);
    } finally {
        phase2RemoveDirectory(dirname($fixture['root']));
    }
});

test('Phase3RestoreJob session access exposes only safe blocked preflight state', function (): void {
    $path = phase2TempDirectory('phase3-job-access');
    try {
        $store = new RestoreJobStore($path);
        $job = $store->create('safe_db/fixture.sql', 'restore_fixture');
        $access = new RestoreJobAccess();
        $access->register($job['id']);
        assertTrueValue($access->owns($job['id']));
        $view = $access->publicView($job);
        assertSameValue('queued', $view['status']);
        assertFalseValue(array_key_exists('can_execute', $view));
        assertNotContainsValue('sql_path', json_encode($view) ?: '');
    } finally {
        phase2RemoveDirectory($path);
    }
});

test('Phase3RestoreJob rejects when parsed bytes differ from the verified artifact snapshot', function (): void {
    $fixture = phase3ArtifactFixture('job-snapshot-change');
    $restoreJobs = dirname($fixture['root']) . DIRECTORY_SEPARATOR . 'restore-jobs';
    try {
        $parser = new class implements SqlStructureValidator {
            public function validateFile(string $path, string $expectedDatabase): array
            {
                return [
                    'supported' => true,
                    'database' => $expectedDatabase,
                    'findings' => [],
                    'statement_count' => 1,
                    'content_bytes' => filesize($path),
                    'content_sha256' => str_repeat('0', 64),
                ];
            }
        };
        $store = new RestoreJobStore($restoreJobs);
        $runner = new RestorePreflightRunner(
            $store,
            new RestoreExecutionGuard($restoreJobs),
            new RestoreArtifactValidator($fixture['root'], new BackupProvenanceIndex($fixture['jobs'])),
            $parser,
            new RestoreTargetValidator(['mysql', 'sys']),
            new RestoreSafetyGate(),
            new Logger($restoreJobs . DIRECTORY_SEPARATOR . 'test.log')
        );
        $result = $runner->run($store->create($fixture['relative'], 'restore_fixture')['id']);
        assertSameValue('failed', $result['status']);
        assertSameValue('artifact_changed_during_preflight', $result['result']['code']);
        assertFalseValue($result['result']['integrity_verified']);
    } finally {
        phase2RemoveDirectory(dirname($fixture['root']));
    }
});
