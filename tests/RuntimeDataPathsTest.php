<?php

declare(strict_types=1);

use BackupApp\Support\RuntimeDataPaths;
use BackupApp\Support\RuntimeDataBootstrap;

function runtimeDataFixture(string $name): string
{
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'runtime-data-' . $name . '-' . bin2hex(random_bytes(6));
    if (!mkdir($path, 0700, true) && !is_dir($path)) {
        throw new RuntimeException('Cannot create runtime data fixture');
    }
    return $path;
}

function removeRuntimeDataFixture(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isLink() || $item->isFile()) {
            @unlink($item->getPathname());
        } else {
            @rmdir($item->getPathname());
        }
    }
    @rmdir($path);
}

/** @return list<string> */
function runtimeDirectoryNames(): array
{
    return [
        'backups',
        'jobs',
        'logs',
        'restore-jobs',
        'restore-integration-jobs',
        'restore-integration-ownership',
        'database-operations',
        'temporary',
        'sessions',
    ];
}

function createProductionRuntimeTree(string $root): void
{
    foreach (runtimeDirectoryNames() as $name) {
        $path = $root . DIRECTORY_SEPARATOR . $name;
        if (!mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException('Cannot create production runtime fixture');
        }
    }
}

test('Runtime data development default preserves the project storage layout', static function (): void {
    $projectRoot = dirname(__DIR__);
    $config = require $projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
    $paths = (new RuntimeDataPaths(
        $projectRoot,
        (string) $config['runtime_data_root'],
        (string) $config['runtime_data_mode']
    ))->paths();

    $storage = $projectRoot . DIRECTORY_SEPARATOR . 'storage';
    assertSameValue($storage, $paths['runtime_data_root']);
    assertSameValue($storage . DIRECTORY_SEPARATOR . 'backups', $paths['backup_path']);
    assertSameValue($storage . DIRECTORY_SEPARATOR . 'jobs', $paths['job_path']);
    assertSameValue($storage . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'app.log', $paths['log_path']);
    assertSameValue($storage . DIRECTORY_SEPARATOR . 'restore-jobs', $paths['restore_job_path']);
    assertSameValue($storage . DIRECTORY_SEPARATOR . 'temporary', $paths['temporary_path']);
    assertSameValue($storage . DIRECTORY_SEPARATOR . 'sessions', $paths['session_path']);
});

test('Runtime data production root derives every writable component from one approved root', static function (): void {
    $base = runtimeDataFixture('production');
    $project = $base . DIRECTORY_SEPARATOR . 'source';
    $approved = $base . DIRECTORY_SEPARATOR . 'approved-runtime';
    mkdir($project, 0700, true);
    mkdir($approved, 0700, true);
    createProductionRuntimeTree($approved);

    try {
        $canonical = (string) realpath($approved);
        $paths = (new RuntimeDataPaths($project, $approved, 'production', $approved))->paths();
        assertSameValue($canonical, $paths['runtime_data_root']);
        assertSameValue($canonical . DIRECTORY_SEPARATOR . 'restore-integration-jobs', $paths['restore_integration_job_path']);
        assertSameValue($canonical . DIRECTORY_SEPARATOR . 'restore-integration-ownership', $paths['restore_integration_ownership_path']);
        assertSameValue($canonical . DIRECTORY_SEPARATOR . 'database-operations' . DIRECTORY_SEPARATOR . 'global.lock', $paths['database_operation_lock_path']);
        assertSameValue($canonical . DIRECTORY_SEPARATOR . 'temporary', $paths['temporary_path']);
        assertSameValue($canonical . DIRECTORY_SEPARATOR . 'sessions', $paths['session_path']);
        assertSameValue([$canonical . DIRECTORY_SEPARATOR . 'backups'], $paths['allowed_backup_roots']);
    } finally {
        removeRuntimeDataFixture($base);
    }
});

test('Runtime data production mode rejects relative UNC traversal missing and unapproved roots', static function (): void {
    $base = runtimeDataFixture('invalid');
    $project = $base . DIRECTORY_SEPARATOR . 'source';
    $approved = $base . DIRECTORY_SEPARATOR . 'approved-runtime';
    mkdir($project, 0700, true);
    mkdir($approved, 0700, true);
    createProductionRuntimeTree($approved);

    try {
        $candidates = [
            'relative' . DIRECTORY_SEPARATOR . 'runtime',
            '\\\\server\\share\\runtime',
            $approved . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'approved-runtime',
            $base . DIRECTORY_SEPARATOR . 'unapproved-runtime',
            $base . DIRECTORY_SEPARATOR . 'missing-runtime',
        ];
        foreach ($candidates as $candidate) {
            $rejected = false;
            try {
                (new RuntimeDataPaths($project, $candidate, 'production', $approved))->paths();
            } catch (RuntimeException) {
                $rejected = true;
            }
            assertTrueValue($rejected, "Unsafe runtime root was accepted: {$candidate}");
        }
    } finally {
        removeRuntimeDataFixture($base);
    }
});

test('Runtime data production mode rejects storage inside the application source root', static function (): void {
    $base = runtimeDataFixture('source-overlap');
    $project = $base . DIRECTORY_SEPARATOR . 'source';
    $insideSource = $project . DIRECTORY_SEPARATOR . 'storage';
    mkdir($insideSource, 0700, true);
    createProductionRuntimeTree($insideSource);

    try {
        $rejected = false;
        try {
            (new RuntimeDataPaths($project, $insideSource, 'production', $insideSource))->paths();
        } catch (RuntimeException) {
            $rejected = true;
        }
        assertTrueValue($rejected, 'Production storage inside source root was accepted');
    } finally {
        removeRuntimeDataFixture($base);
    }
});

test('Runtime data production mode rejects a junction in a derived directory', static function (): void {
    if (PHP_OS_FAMILY !== 'Windows') {
        skipTest('NTFS junction fixture is Windows-specific');
    }
    $base = runtimeDataFixture('junction');
    $project = $base . DIRECTORY_SEPARATOR . 'source';
    $approved = $base . DIRECTORY_SEPARATOR . 'approved-runtime';
    $target = $base . DIRECTORY_SEPARATOR . 'junction-target';
    mkdir($project, 0700, true);
    mkdir($approved, 0700, true);
    mkdir($target, 0700, true);
    foreach (array_diff(runtimeDirectoryNames(), ['jobs']) as $name) {
        mkdir($approved . DIRECTORY_SEPARATOR . $name, 0700, true);
    }
    $junction = $approved . DIRECTORY_SEPARATOR . 'jobs';
    $quotedJunction = str_replace("'", "''", $junction);
    $quotedTarget = str_replace("'", "''", $target);
    $script = "\$ErrorActionPreference='Stop'; New-Item -ItemType Junction -Path '{$quotedJunction}'"
        . " -Target '{$quotedTarget}' | Out-Null";
    $process = proc_open(
        ['powershell.exe', '-NoLogo', '-NoProfile', '-NonInteractive', '-Command', $script],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        removeRuntimeDataFixture($base);
        skipTest('Cannot start junction fixture command');
    }
    foreach ($pipes as $pipe) { stream_get_contents($pipe); fclose($pipe); }
    $created = proc_close($process) === 0 && is_dir($junction);
    if (!$created) {
        removeRuntimeDataFixture($base);
        skipTest('Junction creation is unavailable');
    }

    try {
        $rejected = false;
        try {
            (new RuntimeDataPaths($project, $approved, 'production', $approved))->paths();
        } catch (RuntimeException) {
            $rejected = true;
        }
        assertTrueValue($rejected, 'Junction-backed runtime directory was accepted');
    } finally {
        @rmdir($junction);
        removeRuntimeDataFixture($base);
    }
});

test('Runtime data paths keep backup and restore on one exact shared operation lock', static function (): void {
    $base = runtimeDataFixture('shared-lock');
    $project = $base . DIRECTORY_SEPARATOR . 'source';
    $storage = $project . DIRECTORY_SEPARATOR . 'storage';
    mkdir($storage, 0700, true);

    try {
        $paths = (new RuntimeDataPaths($project, $storage, 'development'))->paths();
        $expected = realpath($storage) . DIRECTORY_SEPARATOR . 'database-operations' . DIRECTORY_SEPARATOR . 'global.lock';
        assertSameValue($expected, $paths['database_operation_lock_path']);
        assertSameValue($paths['database_operation_lock_path'], $paths['backup_database_operation_lock_path']);
        assertSameValue($paths['database_operation_lock_path'], $paths['restore_database_operation_lock_path']);
    } finally {
        removeRuntimeDataFixture($base);
    }
});

test('Runtime data path resolution does not modify existing development storage', static function (): void {
    $base = runtimeDataFixture('no-mutation');
    $project = $base . DIRECTORY_SEPARATOR . 'source';
    $storage = $project . DIRECTORY_SEPARATOR . 'storage';
    mkdir($storage, 0700, true);
    $sentinel = $storage . DIRECTORY_SEPARATOR . 'existing-artifact.sql';
    file_put_contents($sentinel, 'existing fixture');
    $before = hash_file('sha256', $sentinel);
    $beforeEntries = scandir($storage);

    try {
        (new RuntimeDataPaths($project, $storage, 'development'))->paths();
        assertSameValue($before, hash_file('sha256', $sentinel));
        assertSameValue($beforeEntries, scandir($storage));
    } finally {
        removeRuntimeDataFixture($base);
    }
});

test('Runtime data bootstrap creates only missing development directories and preserves existing data', static function (): void {
    $base = runtimeDataFixture('bootstrap');
    $project = $base . DIRECTORY_SEPARATOR . 'source';
    $storage = $project . DIRECTORY_SEPARATOR . 'storage';
    mkdir($storage, 0700, true);
    $sentinel = $storage . DIRECTORY_SEPARATOR . 'existing-history.json';
    file_put_contents($sentinel, '{"existing":true}');
    $before = hash_file('sha256', $sentinel);
    try {
        $config = (new RuntimeDataBootstrap())->prepare([
            'runtime_data_mode' => 'development',
            'runtime_data_root' => $storage,
        ], $project);
        foreach (runtimeDirectoryNames() as $name) {
            assertTrueValue(is_dir($storage . DIRECTORY_SEPARATOR . $name), "Missing runtime directory {$name}");
        }
        assertSameValue($before, hash_file('sha256', $sentinel));
        assertSameValue(
            realpath($storage) . DIRECTORY_SEPARATOR . 'database-operations' . DIRECTORY_SEPARATOR . 'global.lock',
            $config['database_operation_lock_path']
        );
    } finally {
        removeRuntimeDataFixture($base);
    }
});

test('Runtime data bootstrap configures the application-owned session directory before session start', static function (): void {
    $base = runtimeDataFixture('session');
    $sessionPath = $base . DIRECTORY_SEPARATOR . 'sessions';
    mkdir($sessionPath, 0700, true);
    $bootstrap = __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';
    $code = 'require $argv[1]; (new BackupApp\\Support\\RuntimeDataBootstrap())->configureSession($argv[2]); echo session_save_path();';

    try {
        $process = proc_open(
            [PHP_BINARY, '-r', $code, $bootstrap, $sessionPath],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        assertTrueValue(is_resource($process), 'Cannot start session-path verification process');
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        assertSameValue(0, $exit, "Session-path verification failed: {$stderr}");
        assertSameValue($sessionPath, $stdout);
    } finally {
        removeRuntimeDataFixture($base);
    }
});
