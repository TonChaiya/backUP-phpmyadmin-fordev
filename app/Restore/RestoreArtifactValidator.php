<?php

declare(strict_types=1);

namespace BackupApp\Restore;

/** Legacy History metadata only; not an Import eligibility or execution gate. */
final class RestoreArtifactValidator
{
    public const RESTORE_ORDER = [
        'session_setup',
        'drop_views',
        'tables_without_foreign_keys',
        'data_in_foreign_key_dependency_order',
        'auto_increment_values_after_data',
        'foreign_keys_after_all_tables_exist',
        'triggers',
        'view_placeholders',
        'views',
        'routines',
        'events',
        'session_restore',
    ];

    private const MAX_MANIFEST_BYTES = 1048576;
    private string $root;

    public function __construct(string $backupRoot, private BackupProvenanceIndex $provenance)
    {
        $resolved = realpath($backupRoot);
        $this->root = $resolved !== false ? rtrim($resolved, DIRECTORY_SEPARATOR) : rtrim($backupRoot, DIRECTORY_SEPARATOR);
    }

    /** @return array<string, mixed> */
    public function inspect(string $relativePath): array
    {
        $base = $this->baseResult($relativePath);
        $sqlPath = $this->resolve($relativePath);
        $manifestPath = $sqlPath !== null ? $this->containedFile($sqlPath . '.json') : null;

        if ($sqlPath === null || $manifestPath === null) {
            return array_merge($base, ['status' => 'missing_pair']);
        }

        $manifest = $this->manifest($manifestPath);
        $bytes = filesize($sqlPath);

        if (!$this->validMetadata($manifest, $relativePath, basename($sqlPath), $bytes)) {
            return array_merge($base, ['status' => 'invalid_metadata']);
        }

        if (!$this->provenanceMatches($manifest, $relativePath)) {
            return array_merge($base, ['status' => 'unverified_origin', 'database' => (string) $manifest['database']]);
        }

        return array_merge($base, [
            'status' => 'candidate',
            'database' => (string) $manifest['database'],
            'bytes' => (int) $bytes,
        ]);
    }

    /** @return array<string, mixed> */
    private function baseResult(string $relativePath): array
    {
        return [
            'relative_path' => str_replace('\\', '/', $relativePath),
            'eligible' => false,
            'integrity_verified' => false,
            'database' => null,
            'bytes' => null,
        ];
    }

    private function resolve(string $relativePath): ?string
    {
        $normalized = str_replace('\\', '/', trim($relativePath));

        if ($normalized === '' || str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized) === 1
            || in_array('..', explode('/', $normalized), true) || !str_ends_with(strtolower($normalized), '.sql')) {
            return null;
        }

        $candidate = realpath($this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized));
        $prefix = $this->root . DIRECTORY_SEPARATOR;

        return $candidate !== false && str_starts_with($candidate, $prefix) ? $candidate : null;
    }

    private function containedFile(string $path): ?string
    {
        $resolved = realpath($path);
        $prefix = $this->root . DIRECTORY_SEPARATOR;

        return $resolved !== false && is_file($resolved) && str_starts_with($resolved, $prefix)
            ? $resolved
            : null;
    }

    /** @return array<string, mixed>|null */
    private function manifest(string $path): ?array
    {
        $size = filesize($path);

        if ($size === false || $size < 2 || $size > self::MAX_MANIFEST_BYTES) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function validMetadata(?array $manifest, string $relativePath, string $filename, int|false $bytes): bool
    {
        if ($manifest === null || $bytes === false) {
            return false;
        }

        $normalized = str_replace('\\', '/', $relativePath);

        return ($manifest['complete'] ?? null) === true
            && is_string($manifest['filename'] ?? null) && $manifest['filename'] === $filename
            && is_string($manifest['relative_path'] ?? null)
            && str_replace('\\', '/', $manifest['relative_path']) === $normalized
            && is_string($manifest['database'] ?? null) && $manifest['database'] !== ''
            && is_string($manifest['created_at'] ?? null) && $this->validTimestamp($manifest['created_at'])
            && is_int($manifest['bytes'] ?? null) && $manifest['bytes'] === $bytes
            && is_string($manifest['sha256'] ?? null)
            && preg_match('/^[a-f0-9]{64}$/D', strtolower($manifest['sha256'])) === 1
            && $this->validStats($manifest['stats'] ?? null)
            && ($manifest['restore_order'] ?? null) === self::RESTORE_ORDER;
    }

    private function validTimestamp(string $timestamp): bool
    {
        $date = \DateTimeImmutable::createFromFormat(DATE_ATOM, $timestamp);

        return $date !== false && $date->format(DATE_ATOM) === $timestamp;
    }

    private function validStats(mixed $stats): bool
    {
        $keys = [
            'tables',
            'views',
            'triggers',
            'procedures',
            'functions',
            'events',
            'foreign_keys',
            'auto_increment_tables',
            'myisam_lock_sections',
            'rows',
        ];

        if (!is_array($stats) || array_keys($stats) !== $keys) {
            return false;
        }

        foreach ($stats as $value) {
            if (!is_int($value) || $value < 0) {
                return false;
            }
        }

        return true;
    }

    private function provenanceMatches(array $manifest, string $relativePath): bool
    {
        $origin = $this->provenance->find($relativePath);

        return is_array($origin)
            && hash_equals((string) ($origin['database'] ?? ''), (string) ($manifest['database'] ?? ''))
            && (int) ($origin['bytes'] ?? -1) === (int) ($manifest['bytes'] ?? -2)
            && hash_equals(strtolower((string) ($origin['sha256'] ?? '')), strtolower((string) ($manifest['sha256'] ?? '')));
    }

}
