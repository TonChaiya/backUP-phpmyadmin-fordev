<?php

declare(strict_types=1);

use BackupApp\Backup\BackupExecutionGuard;
use BackupApp\Backup\BackupJobRunner;
use BackupApp\Backup\BackupJobStore;
use BackupApp\Backup\BackupOperation;
use BackupApp\Support\Logger;

final class Phase2FakeBackupOperation implements BackupOperation
{
    /** @var array<int, string> */
    public array $calls = [];
    public bool $active = false;
    public bool $finished = false;

    /** @param array<int, string> $failDatabases */
    public function __construct(private array $failDatabases = [], private bool $failStart = false)
    {
    }

    public function start(): void
    {
        if ($this->failStart) {
            throw new RuntimeException('SENTINEL-START-DETAIL');
        }
    }

    public function backup(string $database): array
    {
        if ($this->active) {
            throw new RuntimeException('Concurrent invocation detected');
        }

        $this->active = true;
        $this->calls[] = $database;

        try {
            if (in_array($database, $this->failDatabases, true)) {
                throw new RuntimeException('SENTINEL-DB-DETAIL-' . $database);
            }

            return [
                'database' => $database,
                'filename' => $database . '.sql',
                'relative_path' => $database . '/' . $database . '.sql',
                'bytes' => 123,
                'sha256' => str_repeat('a', 64),
                'manifest_path' => $database . '/' . $database . '.sql.json',
            ];
        } finally {
            $this->active = false;
        }
    }

    public function finish(): void
    {
        $this->finished = true;
    }
}

test('Phase2JobRunner executes sequentially in declared order and continues after failure', function (): void {
    $path = phase2TempDirectory('runner-order');

    try {
        $store = new BackupJobStore($path);
        $guard = new BackupExecutionGuard($path);
        $runner = new BackupJobRunner($store, $guard, new Logger($path . DIRECTORY_SEPARATOR . 'test.log'));
        $job = $store->create(['alpha', 'beta', 'gamma']);
        $operation = new Phase2FakeBackupOperation(['beta']);
        $result = $runner->run($job['id'], $operation, ['SENTINEL-DB-DETAIL-beta']);

        assertSameValue(['alpha', 'beta', 'gamma'], $operation->calls);
        assertTrueValue($operation->finished);
        assertSameValue(['succeeded', 'failed', 'succeeded'], array_column($result['databases'], 'status'));
        assertSameValue('failed', $result['status']);
        assertSameValue(2, $result['summary']['succeeded']);
        assertSameValue(1, $result['summary']['failed']);
        assertSameValue('ไม่สามารถสำรองฐานข้อมูลนี้ได้', $result['databases'][1]['error']['message']);
        assertNotContainsValue('SENTINEL', (string) file_get_contents($store->pathFor($job['id'])));
        assertNotContainsValue('SENTINEL-DB-DETAIL-beta', (string) file_get_contents($path . DIRECTORY_SEPARATOR . 'test.log'));
    } finally {
        phase2RemoveDirectory($path);
    }
});

test('Phase2JobRunner marks every queued database failed when operation start fails', function (): void {
    $path = phase2TempDirectory('runner-start');

    try {
        $store = new BackupJobStore($path);
        $runner = new BackupJobRunner($store, new BackupExecutionGuard($path), new Logger($path . DIRECTORY_SEPARATOR . 'test.log'));
        $job = $store->create(['alpha', 'beta']);
        $result = $runner->run($job['id'], new Phase2FakeBackupOperation([], true), ['SENTINEL-START-DETAIL']);

        assertSameValue('failed', $result['status']);
        assertSameValue(['failed', 'failed'], array_column($result['databases'], 'status'));
        assertSameValue(2, $result['summary']['failed']);
    } finally {
        phase2RemoveDirectory($path);
    }
});

test('Phase2JobRunner rejects duplicate ownership without mutating the job', function (): void {
    $path = phase2TempDirectory('runner-duplicate');

    try {
        $store = new BackupJobStore($path);
        $guard = new BackupExecutionGuard($path);
        $job = $store->create(['alpha']);
        $held = $guard->acquireJob($job['id']);
        $failed = false;

        try {
            (new BackupJobRunner($store, $guard, new Logger($path . DIRECTORY_SEPARATOR . 'test.log')))
                ->run($job['id'], new Phase2FakeBackupOperation());
        } catch (RuntimeException) {
            $failed = true;
        } finally {
            $guard->release($held);
        }

        assertTrueValue($failed);
        assertSameValue('queued', $store->read($job['id'])['status']);
        assertSameValue(null, $store->read($job['id'])['execution_claimed_at']);
    } finally {
        phase2RemoveDirectory($path);
    }
});

test('Phase2JobRunner recovery interrupts only a claimed orphan and never retries it', function (): void {
    $path = phase2TempDirectory('runner-interrupted');

    try {
        $store = new BackupJobStore($path);
        $runner = new BackupJobRunner($store, new BackupExecutionGuard($path), new Logger($path . DIRECTORY_SEPARATOR . 'test.log'));
        $unclaimed = $store->create(['waiting']);
        assertSameValue('queued', $runner->recoverInterrupted($unclaimed['id'])['status']);

        $claimed = $store->create(['alpha', 'beta']);
        $store->update($claimed['id'], static function (array $job): array {
            $job['execution_claimed_at'] = date('c');
            $job['status'] = 'running';
            $job['databases'][0]['status'] = 'running';

            return $job;
        });
        $recovered = $runner->recoverInterrupted($claimed['id']);

        assertSameValue('interrupted', $recovered['status']);
        assertSameValue(['interrupted', 'interrupted'], array_column($recovered['databases'], 'status'));
        assertSameValue(2, $recovered['summary']['interrupted']);

        $failed = false;
        try {
            $runner->run($claimed['id'], new Phase2FakeBackupOperation());
        } catch (RuntimeException) {
            $failed = true;
        }
        assertTrueValue($failed, 'Interrupted job must not auto retry');
    } finally {
        phase2RemoveDirectory($path);
    }
});

test('Phase2JobRunner interrupts an unclaimed queued job after connection context changes', function (): void {
    $path = phase2TempDirectory('runner-connection-change');

    try {
        $store = new BackupJobStore($path);
        $runner = new BackupJobRunner($store, new BackupExecutionGuard($path), new Logger($path . DIRECTORY_SEPARATOR . 'test.log'));
        $job = $store->create(['alpha', 'beta']);
        $interrupted = $runner->interruptQueued($job['id'], 'connection_changed', 'การเชื่อมต่อเปลี่ยนหลังสร้างงาน');

        assertSameValue('interrupted', $interrupted['status']);
        assertSameValue(['interrupted', 'interrupted'], array_column($interrupted['databases'], 'status'));
        assertSameValue('connection_changed', $interrupted['databases'][0]['error']['code']);
    } finally {
        phase2RemoveDirectory($path);
    }
});
