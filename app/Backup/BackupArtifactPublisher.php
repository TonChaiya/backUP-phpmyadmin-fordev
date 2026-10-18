<?php

declare(strict_types=1);

namespace BackupApp\Backup;

use Closure;
use RuntimeException;
use Throwable;

final class BackupArtifactPublisher
{
    private const COMPLETION_MARKER = '-- Backup completed successfully';
    private Closure $move;

    public function __construct(?callable $move = null)
    {
        $this->move = $move !== null
            ? Closure::fromCallable($move)
            : static function (string $source, string $target): bool {
                if (!@link($source, $target)) {
                    return false;
                }

                if (!@unlink($source)) {
                    @unlink($target);

                    return false;
                }

                return true;
            };
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array{bytes: int, sha256: string, manifest_path: string}
     */
    public function publish(string $temporarySql, string $finalSql, array $manifest, string $expectedSha256): array
    {
        $manifestPath = $finalSql . '.json';
        $temporaryManifest = $manifestPath . '.tmp';
        $sqlMoved = false;
        $manifestMoved = false;
        $manifestTempCreated = false;

        try {
            if (!is_file($temporarySql) || !$this->hasCompletionMarker($temporarySql)) {
                throw new RuntimeException('Backup file was not completed');
            }

            $actualSha256 = hash_file('sha256', $temporarySql);
            $actualBytes = filesize($temporarySql);

            if (
                !is_string($actualSha256)
                || $actualBytes === false
                || !hash_equals($expectedSha256, $actualSha256)
                || !hash_equals((string) ($manifest['sha256'] ?? ''), $actualSha256)
                || (int) ($manifest['bytes'] ?? -1) !== $actualBytes
                || ($manifest['complete'] ?? false) !== true
                || (string) ($manifest['filename'] ?? '') !== basename($finalSql)
                || !$this->validRelativePath((string) ($manifest['relative_path'] ?? ''), basename($finalSql))
            ) {
                throw new RuntimeException('Backup integrity verification failed');
            }

            if (file_exists($finalSql) || file_exists($manifestPath) || file_exists($temporaryManifest)) {
                throw new RuntimeException('Backup artifact already exists');
            }

            $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $handle = @fopen($temporaryManifest, 'xb');

            if ($encoded === false || $handle === false) {
                throw new RuntimeException('Cannot create backup manifest');
            }

            $manifestTempCreated = true;

            try {
                $payload = $encoded . "\n";

                if (fwrite($handle, $payload) !== strlen($payload) || !fflush($handle)) {
                    throw new RuntimeException('Cannot create backup manifest');
                }
            } finally {
                fclose($handle);
            }

            if (!$this->verifyPair($temporarySql, $temporaryManifest)) {
                throw new RuntimeException('Backup manifest verification failed');
            }

            if (!(($this->move)($temporaryManifest, $manifestPath))) {
                throw new RuntimeException('Cannot finalize backup manifest');
            }

            $manifestMoved = true;

            if (!(($this->move)($temporarySql, $finalSql))) {
                throw new RuntimeException('Cannot finalize backup file');
            }

            $sqlMoved = true;

            if (!$this->verifyPublished($finalSql, $manifestPath)) {
                throw new RuntimeException('Published backup verification failed');
            }

            @chmod($finalSql, 0640);
            @chmod($manifestPath, 0640);

            return [
                'bytes' => $actualBytes,
                'sha256' => $actualSha256,
                'manifest_path' => $manifestPath,
            ];
        } catch (Throwable $exception) {
            @unlink($temporarySql);

            if ($manifestTempCreated) {
                @unlink($temporaryManifest);
            }

            if ($manifestMoved) {
                @unlink($manifestPath);
            }

            if ($sqlMoved) {
                @unlink($finalSql);
            }

            throw $exception;
        }
    }

    public function verifyPublished(string $sqlPath, string $manifestPath): bool
    {
        return is_file($sqlPath) && is_file($manifestPath) && $this->verifyPair($sqlPath, $manifestPath);
    }

    private function verifyPair(string $sqlPath, string $manifestPath): bool
    {
        if (!$this->hasCompletionMarker($sqlPath)) {
            return false;
        }

        $decoded = json_decode((string) @file_get_contents($manifestPath), true);
        $sha256 = hash_file('sha256', $sqlPath);
        $bytes = filesize($sqlPath);

        $expectedFilename = preg_replace('/\.tmp$/D', '', basename($sqlPath)) ?? basename($sqlPath);

        return is_array($decoded)
            && ($decoded['complete'] ?? false) === true
            && (string) ($decoded['filename'] ?? '') === $expectedFilename
            && is_string($sha256)
            && hash_equals((string) ($decoded['sha256'] ?? ''), $sha256)
            && $bytes !== false
            && (int) ($decoded['bytes'] ?? -1) === $bytes;
    }

    private function hasCompletionMarker(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            $size = filesize($path);

            if ($size === false || fseek($handle, max(0, $size - 4096)) !== 0) {
                return false;
            }

            $contents = stream_get_contents($handle);

            return is_string($contents) && str_ends_with(rtrim($contents), self::COMPLETION_MARKER);
        } finally {
            fclose($handle);
        }
    }

    private function validRelativePath(string $relativePath, string $filename): bool
    {
        $normalized = str_replace('\\', '/', $relativePath);

        return $normalized !== ''
            && !str_starts_with($normalized, '/')
            && preg_match('/^[A-Za-z]:\//', $normalized) !== 1
            && !in_array('..', explode('/', $normalized), true)
            && basename($normalized) === $filename;
    }
}
