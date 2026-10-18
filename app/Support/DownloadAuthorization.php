<?php

declare(strict_types=1);

namespace BackupApp\Support;

final class DownloadAuthorization
{
    private const COMPLETION_MARKER = '-- Backup completed successfully';

    public function __construct(private string $secret)
    {
    }

    public function token(string $relativePath): string
    {
        return hash_hmac('sha256', $this->normalizeRelative($relativePath) ?? '', $this->secret);
    }

    /**
     * @param array<int, array{relative_path?: string}> $inventory
     */
    public function authorize(string $relativePath, string $token, array $inventory): bool
    {
        $normalized = $this->normalizeRelative($relativePath);

        if ($normalized === null || !str_ends_with(strtolower($normalized), '.sql')) {
            return false;
        }

        if ($token === '' || !hash_equals($this->token($normalized), $token)) {
            return false;
        }

        foreach ($inventory as $item) {
            $listed = isset($item['relative_path']) && is_string($item['relative_path'])
                ? $this->normalizeRelative($item['relative_path'])
                : null;

            if ($listed !== null && hash_equals($listed, $normalized)) {
                return true;
            }
        }

        return false;
    }

    public function verifyArtifact(string $path): bool
    {
        if (!is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'sql') {
            return false;
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $size = filesize($path) ?: 0;
        fseek($handle, max(0, $size - 4096));
        $tail = stream_get_contents($handle);
        fclose($handle);

        if ($tail === false || !str_contains($tail, self::COMPLETION_MARKER)) {
            return false;
        }

        $manifestPath = $path . '.json';

        if (!is_file($manifestPath)) {
            return true;
        }

        $manifest = json_decode((string) @file_get_contents($manifestPath), true);
        $expectedHash = is_array($manifest) ? ($manifest['sha256'] ?? null) : null;

        if (($manifest['complete'] ?? false) !== true || !is_string($expectedHash) || !preg_match('/^[a-f0-9]{64}$/i', $expectedHash)) {
            return false;
        }

        $actualHash = hash_file('sha256', $path);

        return is_string($actualHash) && hash_equals(strtolower($expectedHash), strtolower($actualHash));
    }

    private function normalizeRelative(string $path): ?string
    {
        if ($path === '' || str_contains($path, "\0") || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return null;
        }

        $path = str_replace('\\', '/', $path);

        if (str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        $segments = explode('/', $path);

        if (in_array('', $segments, true) || in_array('.', $segments, true) || in_array('..', $segments, true)) {
            return null;
        }

        return implode('/', $segments);
    }
}

