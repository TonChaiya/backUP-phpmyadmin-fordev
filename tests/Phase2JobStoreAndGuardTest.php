<?php

declare(strict_types=1);

use BackupApp\Backup\BackupExecutionGuard;
use BackupApp\Backup\BackupJobStore;

function phase2TempDirectory(string $suffix): string
{
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup-app-phase2-' . $suffix . '-' . bin2hex(random_bytes(5));

    if (!mkdir($path, 0700, true) && !is_dir($path)) {
        throw new RuntimeException('Cannot create test directory');
    }

    return $path;
}

function phase2RemoveDirectory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    ) as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($path);
}

test('Phase2JobStore creates unpredictable job with truthful queued summary', function (): void {
    $path = phase2TempDirectory('store-create');

    try {
        $store = new BackupJobStore($path);
        $job = $store->create(['alpha', 'beta']);

        assertTrueValue(preg_match('/^[a-f0-9]{32}$/', $job['id']) === 1);
        assertSameValue('queued', $job['status']);
        assertSameValue(['queued' => 2, 'running' => 0, 'succeeded' => 0, 'failed' => 0, 'interrupted' => 0, 'total' => 2], $job['summary']);
        assertSameValue(['alpha', 'beta'], array_column($job['databases'], 'name'));
        assertSameValue($job, $store->read($job['id']));
    } finally {
        phase2RemoveDirectory($path);
    }
});

test('Phase2JobStore appends atomic snapshots and ignores an incomplete trailing record', function (): void {
    $path = phase2TempDirectory('store-partial');

    try {
        $store = new BackupJobStore($path);
        $created = $store->create(['alpha']);
        $updated = $store->update($created['id'], static function (array $job): array {
            $job['status'] = 'running';
            $job['databases'][0]['status'] = 'running';

            return $job;
        });
        file_put_contents($store->pathFor($created['id']), '{"partial":', FILE_APPEND);

        $afterPartial = $store->update($created['id'], static function (array $job): array {
            $job['status'] = 'succeeded';
            $job['databases'][0]['status'] = 'succeeded';

            return $job;
        });

        $read = $store->read($created['id']);
        assertSameValue('succeeded', $read['status']);
        assertSameValue('succeeded', $read['databases'][0]['status']);
        assertSameValue($updated['revision'] + 1, $afterPartial['revision']);
        assertSameValue($afterPartial['revision'], $read['revision']);
    } finally {
        phase2RemoveDirectory($path);
    }
});

test('Phase2JobStore rejects traversal IDs and never persists supplied secret fields', function (): void {
    $path = phase2TempDirectory('store-security');

    try {
        $store = new BackupJobStore($path);
        $failed = false;

        try {
            $store->read('../escape');
        } catch (RuntimeException) {
            $failed = true;
        }

        assertTrueValue($failed, 'Traversal job ID must be rejected');
        $job = $store->create(['alpha']);
        $store->update($job['id'], static function (array $state): array {
            $state['password'] = 'SENTINEL-DB-PASSWORD';
            $state['session_token'] = 'SENTINEL-SESSION';

            return $state;
        });
        $contents = (string) file_get_contents($store->pathFor($job['id']));
        assertNotContainsValue('SENTINEL-DB-PASSWORD', $contents);
        assertNotContainsValue('SENTINEL-SESSION', $contents);
    } finally {
        phase2RemoveDirectory($path);
    }
});

test('Phase2ExecutionGuard rejects duplicate job ownership and excludes global concurrency', function (): void {
    $path = phase2TempDirectory('guard');

    try {
        $guardA = new BackupExecutionGuard($path);
        $guardB = new BackupExecutionGuard($path);
        $jobId = str_repeat('a', 32);
        $jobLock = $guardA->acquireJob($jobId);
        assertTrueValue(is_resource($jobLock));
        assertSameValue(null, $guardB->acquireJob($jobId));

        $globalLock = $guardA->acquireGlobal(false);
        assertTrueValue(is_resource($globalLock));
        assertSameValue(null, $guardB->acquireGlobal(false));

        $guardA->release($globalLock);
        $guardA->release($jobLock);
        $nextJobLock = $guardB->acquireJob($jobId);
        assertTrueValue(is_resource($nextJobLock));
        $guardB->release($nextJobLock);
    } finally {
        phase2RemoveDirectory($path);
    }
});

test('Phase2ExecutionGuard enforces locks across separate PHP processes on Windows', function (): void {
    $path = phase2TempDirectory('guard-process');
    $process = null;
    $pipes = [];

    try {
        $jobId = str_repeat('c', 32);
        $code = <<<'PHP'
require getenv('PHASE2_TEST_BOOTSTRAP');
$guard = new BackupApp\Backup\BackupExecutionGuard(getenv('PHASE2_LOCK_DIR'));
$job = $guard->acquireJob(getenv('PHASE2_LOCK_JOB'));
$global = $guard->acquireGlobal(false);
echo "READY\n";
flush();
sleep(2);
$guard->release($global);
$guard->release($job);
PHP;
        $environment = array_merge($_ENV, [
            'PHASE2_TEST_BOOTSTRAP' => __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php',
            'PHASE2_LOCK_DIR' => $path,
            'PHASE2_LOCK_JOB' => $jobId,
        ]);
        $process = proc_open([PHP_BINARY, '-r', $code], [
            ['pipe', 'r'],
            ['pipe', 'w'],
            ['pipe', 'w'],
        ], $pipes, null, $environment);

        if (!is_resource($process)) {
            skipTest('Cannot start a second PHP process');
        }

        assertSameValue("READY\n", fgets($pipes[1]));
        $guard = new BackupExecutionGuard($path);
        assertSameValue(null, $guard->acquireJob($jobId));
        assertSameValue(null, $guard->acquireGlobal(false));
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $pipes = [];
        assertSameValue(0, proc_close($process));
        $process = null;

        $jobLock = $guard->acquireJob($jobId);
        $globalLock = $guard->acquireGlobal(false);
        assertTrueValue(is_resource($jobLock));
        assertTrueValue(is_resource($globalLock));
        $guard->release($globalLock);
        $guard->release($jobLock);
    } finally {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
        phase2RemoveDirectory($path);
    }
});
