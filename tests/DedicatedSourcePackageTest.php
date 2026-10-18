<?php

declare(strict_types=1);

use BackupApp\Deployment\SourcePackageBuilder;

function packageFixtureRoot(string $name): string
{
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dedicated-package-' . $name . '-' . bin2hex(random_bytes(5));
    if (!mkdir($path, 0700, true) && !is_dir($path)) {
        throw new RuntimeException('Cannot create package fixture');
    }
    return $path;
}

function removePackageFixture(string $path): void
{
    if (!is_dir($path) || !str_starts_with(basename($path), 'dedicated-package-')) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

/** @param array<string, string> $files */
function writePackageFixture(string $root, array $files): void
{
    foreach ($files as $path => $contents) {
        $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true) && !is_dir(dirname($target))) {
            throw new RuntimeException('Cannot create fixture directory');
        }
        file_put_contents($target, $contents);
    }
}

test('source package copies only exact allowlisted files and emits deterministic evidence', function (): void {
    $root = packageFixtureRoot('deterministic');
    try {
        $source = $root . '/source';
        $staging = $root . '/staging';
        mkdir($source, 0700, true);
        writePackageFixture($source, [
            'app/bootstrap.php' => "<?php\nreturn 'app';\n",
            'app/redacted.php' => "<?php\nreturn ['secret' => '[REDACTED]'];\n",
            'public/index.php' => "<?php\necho 'public';\n",
            'public/assets/app.css' => "body { color: #123; }\n",
            'config/app.php' => "<?php\nreturn [];\n",
            'config/local.php.example' => "<?php\nreturn ['auth' => ['password_hash' => 'replace_me']];\n",
            'config/local.php' => "<?php\nreturn ['secret' => 'must-not-copy'];\n",
            'storage/backups/private.sql' => 'backup data',
            'tools/debug.php' => '<?php phpinfo();',
            'public/unexpected.exe' => "MZ\x00\x01",
        ]);
        $allowlist = $root . '/allowlist.txt';
        file_put_contents($allowlist, implode("\n", [
            'app/bootstrap.php', 'app/redacted.php', 'config/app.php', 'config/local.php.example',
            'public/assets/app.css', 'public/index.php',
        ]) . "\n");
        $manifestPath = $root . '/source-manifest.json';

        $result = (new SourcePackageBuilder())->build($source, $allowlist, $staging, $manifestPath, '2026.10.18');
        $manifest = json_decode((string) file_get_contents($manifestPath), true, 32, JSON_THROW_ON_ERROR);

        assertSameValue(6, $manifest['artifact_count'] ?? null);
        assertSameValue(6, $result['artifact_count'] ?? null);
        assertSameValue('2026.10.18', $manifest['package_version'] ?? null);
        assertTrueValue(preg_match('/^wt-[a-f0-9]{16}$/', (string) ($manifest['build_id'] ?? '')) === 1);
        assertTrueValue(preg_match('/^[a-f0-9]{64}$/', (string) ($manifest['manifest_digest'] ?? '')) === 1);
        assertSameValue([
            'app/bootstrap.php', 'app/redacted.php', 'config/app.php', 'config/local.php.example',
            'public/assets/app.css', 'public/index.php',
        ], array_column($manifest['files'] ?? [], 'path'));

        $application = (string) ($result['application_path'] ?? '');
        assertTrueValue(is_file($application . '/app/bootstrap.php'));
        assertFalseValue(file_exists($application . '/config/local.php'));
        assertFalseValue(file_exists($application . '/storage/backups/private.sql'));
        assertFalseValue(file_exists($application . '/public/unexpected.exe'));

        $fileRecords = [];
        foreach ($manifest['files'] as $file) {
            $fileRecords[] = $file['path'] . "\0" . $file['size'] . "\0" . $file['sha256'] . "\n";
            assertSameValue(hash_file('sha256', $source . '/' . $file['path']), $file['sha256']);
            assertSameValue(filesize($source . '/' . $file['path']), $file['size']);
        }
        $sourceDigest = hash('sha256', implode('', $fileRecords));
        assertSameValue('wt-' . substr($sourceDigest, 0, 16), $manifest['build_id']);
        assertSameValue(hash_file('sha256', $manifestPath), $result['manifest_file_sha256'] ?? null);

        $again = (new SourcePackageBuilder())->build($source, $allowlist, $staging, $manifestPath, '2026.10.18');
        assertSameValue($result, $again);
    } finally {
        removePackageFixture($root);
    }
});

test('source package rejects unsafe allowlists secret material and mutated immutable packages', function (): void {
    $root = packageFixtureRoot('reject');
    try {
        $source = $root . '/source';
        mkdir($source, 0700, true);
        writePackageFixture($source, [
            'app/good.php' => "<?php\nreturn true;\n",
            'app/key.php' => "<?php\n/* -----BEGIN PRIVATE KEY----- */\n",
        ]);
        $builder = new SourcePackageBuilder();

        foreach ([
            ["../outside.php\n", 'package_allowlist_path_invalid'],
            ["app/good.php\napp/good.php\n", 'package_allowlist_duplicate'],
            ["app/key.php\n", 'package_secret_material_detected'],
        ] as [$allowlistText, $expected]) {
            $allowlist = $root . '/allowlist-' . bin2hex(random_bytes(3)) . '.txt';
            file_put_contents($allowlist, $allowlistText);
            try {
                $builder->build($source, $allowlist, $root . '/stage-' . bin2hex(random_bytes(3)), $root . '/manifest-' . bin2hex(random_bytes(3)) . '.json', '2026.10.18');
                throw new RuntimeException('Expected package rejection');
            } catch (RuntimeException $exception) {
                assertSameValue($expected, $exception->getMessage());
            }
        }

        $allowlist = $root . '/allowlist-good.txt';
        file_put_contents($allowlist, "app/good.php\n");
        $manifest = $root . '/manifest-good.json';
        $result = $builder->build($source, $allowlist, $root . '/stage-good', $manifest, '2026.10.18');
        file_put_contents($result['application_path'] . '/app/good.php', 'tampered');
        try {
            $builder->build($source, $allowlist, $root . '/stage-good', $manifest, '2026.10.18');
            throw new RuntimeException('Expected immutable package collision');
        } catch (RuntimeException $exception) {
            assertSameValue('package_collision', $exception->getMessage());
        }
    } finally {
        removePackageFixture($root);
    }
});

