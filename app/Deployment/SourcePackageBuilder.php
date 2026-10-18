<?php

declare(strict_types=1);

namespace BackupApp\Deployment;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use FilesystemIterator;

final class SourcePackageBuilder
{
    private const ALLOWED_EXTENSIONS = ['php', 'css', 'js', 'example'];

    /** @return array{build_id:string,package_path:string,application_path:string,artifact_count:int,total_size:int,manifest_digest:string,manifest_file_sha256:string} */
    public function build(
        string $sourceRoot,
        string $allowlistPath,
        string $stagingRoot,
        string $manifestPath,
        string $packageVersion,
    ): array {
        $sourceRoot = $this->canonicalDirectory($sourceRoot);
        $paths = $this->readAllowlist($allowlistPath);
        if (preg_match('/^\d{4}\.\d{2}\.\d{2}(?:\.[A-Za-z0-9.-]+)?$/D', $packageVersion) !== 1) {
            throw new RuntimeException('package_version_invalid');
        }

        $files = [];
        $totalSize = 0;
        foreach ($paths as $relativePath) {
            $sourcePath = $sourceRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $this->assertSafeSourceFile($sourceRoot, $sourcePath, $relativePath);
            $this->assertNoSecretMaterial($sourcePath);
            $size = filesize($sourcePath);
            $sha256 = hash_file('sha256', $sourcePath);
            if (!is_int($size) || !is_string($sha256)) {
                throw new RuntimeException('package_source_read_failed');
            }
            $files[] = ['path' => $relativePath, 'size' => $size, 'sha256' => strtolower($sha256)];
            $totalSize += $size;
        }

        $recordBytes = '';
        foreach ($files as $file) {
            $recordBytes .= $file['path'] . "\0" . $file['size'] . "\0" . $file['sha256'] . "\n";
        }
        $sourceDigest = hash('sha256', $recordBytes);
        $buildId = 'wt-' . substr($sourceDigest, 0, 16);
        $manifest = [
            'schema' => 1,
            'package_version' => $packageVersion,
            'build_id' => $buildId,
            'source_kind' => 'current_working_tree',
            'artifact_count' => count($files),
            'total_size' => $totalSize,
            'files' => $files,
        ];
        $manifest['manifest_digest'] = hash('sha256', $this->canonicalJson($manifest));
        $manifestJson = json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ) . "\n";

        $this->ensureDirectory($stagingRoot);
        $stagingRoot = $this->canonicalDirectory($stagingRoot);
        $packagePath = $stagingRoot . DIRECTORY_SEPARATOR . $buildId;
        $applicationPath = $packagePath . DIRECTORY_SEPARATOR . 'Application';

        if (file_exists($manifestPath)) {
            if (!is_file($manifestPath) || !hash_equals(hash('sha256', $manifestJson), (string) hash_file('sha256', $manifestPath))) {
                throw new RuntimeException('manifest_collision');
            }
        }

        if (file_exists($packagePath)) {
            $this->verifyExistingPackage($packagePath, $files, $manifestJson);
        } else {
            $temporaryPath = $stagingRoot . DIRECTORY_SEPARATOR . '.' . $buildId . '.' . bin2hex(random_bytes(8)) . '.tmp';
            try {
                if (!mkdir($temporaryPath . DIRECTORY_SEPARATOR . 'Application', 0750, true)) {
                    throw new RuntimeException('package_staging_create_failed');
                }
                foreach ($files as $file) {
                    $sourcePath = $sourceRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file['path']);
                    $targetPath = $temporaryPath . DIRECTORY_SEPARATOR . 'Application' . DIRECTORY_SEPARATOR
                        . str_replace('/', DIRECTORY_SEPARATOR, $file['path']);
                    $this->ensureDirectory(dirname($targetPath));
                    if (!copy($sourcePath, $targetPath)
                        || !hash_equals($file['sha256'], (string) hash_file('sha256', $targetPath))) {
                        throw new RuntimeException('package_copy_verification_failed');
                    }
                }
                if (file_put_contents($temporaryPath . DIRECTORY_SEPARATOR . 'source-manifest.json', $manifestJson, LOCK_EX) !== strlen($manifestJson)) {
                    throw new RuntimeException('package_manifest_write_failed');
                }
                if (!rename($temporaryPath, $packagePath)) {
                    throw new RuntimeException('package_publish_failed');
                }
            } finally {
                if (isset($temporaryPath) && is_dir($temporaryPath)) {
                    $this->removeOwnedTemporaryDirectory($temporaryPath, $stagingRoot);
                }
            }
        }

        if (!file_exists($manifestPath)) {
            $manifestDirectory = dirname($manifestPath);
            if (!is_dir($manifestDirectory)) {
                throw new RuntimeException('manifest_directory_missing');
            }
            $temporaryManifest = $manifestDirectory . DIRECTORY_SEPARATOR . '.' . basename($manifestPath)
                . '.' . bin2hex(random_bytes(8)) . '.tmp';
            try {
                if (file_put_contents($temporaryManifest, $manifestJson, LOCK_EX) !== strlen($manifestJson)
                    || !rename($temporaryManifest, $manifestPath)) {
                    throw new RuntimeException('manifest_publish_failed');
                }
            } finally {
                if (isset($temporaryManifest) && is_file($temporaryManifest)) {
                    unlink($temporaryManifest);
                }
            }
        }

        return [
            'build_id' => $buildId,
            'package_path' => $packagePath,
            'application_path' => $applicationPath,
            'artifact_count' => count($files),
            'total_size' => $totalSize,
            'manifest_digest' => $manifest['manifest_digest'],
            'manifest_file_sha256' => (string) hash_file('sha256', $manifestPath),
        ];
    }

    /** @return list<string> */
    private function readAllowlist(string $path): array
    {
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('package_allowlist_missing');
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            throw new RuntimeException('package_allowlist_read_failed');
        }
        $paths = [];
        $seen = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (preg_match('#^(?:app|config|public|views)/(?:[A-Za-z0-9._-]+/)*[A-Za-z0-9._-]+$#D', $line) !== 1
                || str_contains($line, '..') || str_contains($line, '\\')) {
                throw new RuntimeException('package_allowlist_path_invalid');
            }
            $key = strtolower($line);
            if (isset($seen[$key])) {
                throw new RuntimeException('package_allowlist_duplicate');
            }
            $seen[$key] = true;
            $paths[] = $line;
        }
        if ($paths === []) {
            throw new RuntimeException('package_allowlist_empty');
        }
        sort($paths, SORT_STRING);
        return $paths;
    }

    private function assertSafeSourceFile(string $root, string $path, string $relativePath): void
    {
        if (!is_file($path) || is_link($path) || @filetype($path) !== 'file') {
            throw new RuntimeException('package_source_file_unsafe');
        }
        $resolved = realpath($path);
        if (!is_string($resolved) || !str_starts_with(strtolower($resolved), strtolower($root . DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('package_source_scope_invalid');
        }
        $this->assertNoIndirection($root, $relativePath);

        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)
            || strcasecmp($relativePath, 'config/local.php') === 0) {
            throw new RuntimeException('package_source_type_forbidden');
        }
    }

    private function assertNoIndirection(string $root, string $relativePath): void
    {
        $current = $root;
        foreach (explode('/', $relativePath) as $segment) {
            $current .= DIRECTORY_SEPARATOR . $segment;
            $type = @filetype($current);
            if ($type !== 'dir' && $type !== 'file') {
                throw new RuntimeException('package_source_reparse_point');
            }
        }
    }

    private function assertNoSecretMaterial(string $path): void
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('package_source_read_failed');
        }
        $carry = '';
        $first = true;
        try {
            while (!feof($handle)) {
                $chunk = fread($handle, 65536);
                if ($chunk === false) {
                    throw new RuntimeException('package_source_read_failed');
                }
                if ($first) {
                    $first = false;
                    if (str_starts_with($chunk, 'MZ') || str_starts_with($chunk, "\x7fELF")
                        || str_starts_with($chunk, "PK\x03\x04")) {
                        throw new RuntimeException('package_executable_or_archive_detected');
                    }
                }
                if (str_contains($chunk, "\0")) {
                    throw new RuntimeException('package_binary_content_detected');
                }
                $scan = $carry . $chunk;
                if (preg_match('/-----BEGIN [A-Z ]*PRIVATE KEY-----/i', $scan) === 1
                    || preg_match('/AKIA[0-9A-Z]{16}/', $scan) === 1) {
                    throw new RuntimeException('package_secret_material_detected');
                }
                preg_match_all(
                    '/[\'\"](?:password|secret|api[_-]?key|token)[\'\"]\s*=>\s*[\'\"]([^\'\"]{4,})[\'\"]/i',
                    $scan,
                    $literalMatches
                );
                foreach ($literalMatches[1] ?? [] as $literal) {
                    if (!in_array(strtolower($literal), ['[redacted]', 'replace_me', 'replace-with-secure-value'], true)) {
                        throw new RuntimeException('package_secret_material_detected');
                    }
                }
                $carry = substr($scan, -128);
            }
        } finally {
            fclose($handle);
        }
    }

    /** @param list<array{path:string,size:int,sha256:string}> $files */
    private function verifyExistingPackage(string $packagePath, array $files, string $manifestJson): void
    {
        if (!is_dir($packagePath) || is_link($packagePath)) {
            throw new RuntimeException('package_collision');
        }
        $expected = [];
        foreach ($files as $file) {
            $expected[$file['path']] = $file;
            $target = $packagePath . DIRECTORY_SEPARATOR . 'Application' . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $file['path']);
            if (!is_file($target) || is_link($target) || filesize($target) !== $file['size']
                || !hash_equals($file['sha256'], (string) hash_file('sha256', $target))) {
                throw new RuntimeException('package_collision');
            }
        }
        $actual = [];
        $application = $packagePath . DIRECTORY_SEPARATOR . 'Application';
        if (!is_dir($application)) {
            throw new RuntimeException('package_collision');
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($application, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($application) + 1));
                $actual[] = $relative;
            }
        }
        sort($actual, SORT_STRING);
        if ($actual !== array_keys($expected)) {
            throw new RuntimeException('package_collision');
        }
        $packageManifest = $packagePath . DIRECTORY_SEPARATOR . 'source-manifest.json';
        if (!is_file($packageManifest)
            || !hash_equals(hash('sha256', $manifestJson), (string) hash_file('sha256', $packageManifest))) {
            throw new RuntimeException('package_collision');
        }
    }

    private function canonicalDirectory(string $path): string
    {
        if (!is_dir($path) || is_link($path)) {
            throw new RuntimeException('package_directory_unsafe');
        }
        $resolved = realpath($path);
        if (!is_string($resolved) || $resolved === '') {
            throw new RuntimeException('package_directory_unsafe');
        }
        return rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
            throw new RuntimeException('package_directory_create_failed');
        }
    }

    /** @param array<string, mixed> $value */
    private function canonicalJson(array $value): string
    {
        $normalize = function (mixed $item) use (&$normalize): mixed {
            if (!is_array($item)) {
                return $item;
            }
            if (array_is_list($item)) {
                return array_map($normalize, $item);
            }
            ksort($item, SORT_STRING);
            foreach ($item as $key => $child) {
                $item[$key] = $normalize($child);
            }
            return $item;
        };
        return json_encode($normalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function removeOwnedTemporaryDirectory(string $path, string $stagingRoot): void
    {
        if (!str_starts_with($path, $stagingRoot . DIRECTORY_SEPARATOR . '.wt-') || !str_ends_with($path, '.tmp')) {
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
}
