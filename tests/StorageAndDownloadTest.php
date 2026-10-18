<?php

declare(strict_types=1);

use BackupApp\Support\BackupPathPolicy;
use BackupApp\Support\DownloadAuthorization;
use BackupApp\Http\Controller\SettingsController;

function removeTestTree(string $path): void
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

function storageFixture(): array
{
    $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup-storage-' . bin2hex(random_bytes(6));
    $root = $base . DIRECTORY_SEPARATOR . 'allowed';
    $sibling = $base . DIRECTORY_SEPARATOR . 'allowed-sibling';
    mkdir($root, 0700, true);
    mkdir($sibling, 0700, true);

    return [$base, $root, $sibling];
}

test('backup path policy accepts the allowlisted root and creates contained directories', static function (): void {
    [$base, $root] = storageFixture();

    try {
        $policy = new BackupPathPolicy([$root]);
        $nested = $root . DIRECTORY_SEPARATOR . 'daily' . DIRECTORY_SEPARATOR . 'manual';
        $resolved = $policy->resolve($nested, true);
        assertSameValue(realpath($nested), $resolved);
    } finally {
        removeTestTree($base);
    }
});

test('backup path policy rejects traversal and sibling prefix confusion', static function (): void {
    [$base, $root, $sibling] = storageFixture();

    try {
        $policy = new BackupPathPolicy([$root]);
        $rejected = 0;

        foreach ([$sibling, $root . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . basename($sibling)] as $candidate) {
            try {
                $policy->resolve($candidate);
            } catch (RuntimeException) {
                $rejected++;
            }
        }

        assertSameValue(2, $rejected);
    } finally {
        removeTestTree($base);
    }
});

test('backup path policy rejects relative paths', static function (): void {
    [$base, $root] = storageFixture();

    try {
        $policy = new BackupPathPolicy([$root]);

        try {
            $policy->resolve('relative\\backups');
            throw new RuntimeException('Relative path was accepted');
        } catch (RuntimeException $exception) {
            assertNotContainsValue('was accepted', $exception->getMessage());
        }
    } finally {
        removeTestTree($base);
    }
});

test('backup path policy rejects a configured root that overlaps protected application directories', static function (): void {
    $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup-project-' . bin2hex(random_bytes(6));
    $public = $base . DIRECTORY_SEPARATOR . 'public';
    mkdir($public, 0700, true);
    mkdir($base . DIRECTORY_SEPARATOR . 'app', 0700, true);
    mkdir($base . DIRECTORY_SEPARATOR . 'config', 0700, true);

    try {
        try {
            new BackupPathPolicy([$public], $base);
            throw new RuntimeException('Protected public root was accepted');
        } catch (RuntimeException $exception) {
            assertNotContainsValue('was accepted', $exception->getMessage());
        }
    } finally {
        removeTestTree($base);
    }
});

test('settings fallback resolves only the validated default root', static function (): void {
    [$base, $root, $sibling] = storageFixture();

    try {
        $policy = new BackupPathPolicy([$root]);
        $controller = new SettingsController($policy);
        $_SESSION['backup_path'] = $sibling;
        $active = $controller->activePath($root);
        assertSameValue(realpath($root), $active);
        assertSameValue($policy->resolve($policy->defaultRoot()), $active);
    } finally {
        removeTestTree($base);
    }
});

test('backup path policy rejects a symlink escape when the platform permits symlinks', static function (): void {
    [$base, $root, $sibling] = storageFixture();
    $link = $root . DIRECTORY_SEPARATOR . 'escape';

    try {
        if (!@symlink($sibling, $link)) {
            skipTest('Symlink creation is unavailable on this Windows environment');
        }

        $policy = new BackupPathPolicy([$root]);

        try {
            $policy->resolve($link);
            throw new RuntimeException('Symlink escape was accepted');
        } catch (RuntimeException $exception) {
            assertNotContainsValue('was accepted', $exception->getMessage());
        }
    } finally {
        @unlink($link);
        removeTestTree($base);
    }
});

test('download authorization accepts only a signed listed SQL path', static function (): void {
    $authorization = new DownloadAuthorization('session-secret');
    $path = 'database/backup.sql';
    $inventory = [['relative_path' => $path]];

    assertTrueValue($authorization->authorize($path, $authorization->token($path), $inventory));
    assertFalseValue($authorization->authorize($path, 'invalid', $inventory));
    assertFalseValue($authorization->authorize('database/other.sql', $authorization->token('database/other.sql'), $inventory));
    assertFalseValue($authorization->authorize('database/manifest.json', $authorization->token('database/manifest.json'), [['relative_path' => 'database/manifest.json']]));
});

test('download artifact verification requires completion and enforces a present manifest checksum', static function (): void {
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup-download-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700, true);
    $sql = $directory . DIRECTORY_SEPARATOR . 'backup.sql';
    $authorization = new DownloadAuthorization('session-secret');

    try {
        file_put_contents($sql, "-- fixture only\n-- Backup completed successfully\n");
        assertTrueValue($authorization->verifyArtifact($sql));

        file_put_contents($sql . '.json', json_encode(['sha256' => hash_file('sha256', $sql), 'complete' => true]));
        assertTrueValue($authorization->verifyArtifact($sql));

        file_put_contents($sql . '.json', json_encode(['sha256' => str_repeat('0', 64), 'complete' => true]));
        assertFalseValue($authorization->verifyArtifact($sql));

        file_put_contents($sql, '-- incomplete fixture');
        @unlink($sql . '.json');
        assertFalseValue($authorization->verifyArtifact($sql));
    } finally {
        removeTestTree($directory);
    }
});

