<?php

declare(strict_types=1);

namespace BackupApp\Support;

use RuntimeException;

final class RuntimeDataPaths
{
    public const MODE_DEVELOPMENT = 'development';
    public const MODE_PRODUCTION = 'production';
    public const APPROVED_PRODUCTION_ROOT = 'C:\\ProgramData\\BackupPhpMyAdmin\\RuntimeData';

    private const DIRECTORY_KEYS = [
        'backup_path' => 'backups',
        'job_path' => 'jobs',
        'log_directory' => 'logs',
        'restore_job_path' => 'restore-jobs',
        'restore_integration_job_path' => 'restore-integration-jobs',
        'restore_integration_ownership_path' => 'restore-integration-ownership',
        'database_operation_directory' => 'database-operations',
        'temporary_path' => 'temporary',
        'session_path' => 'sessions',
    ];

    public function __construct(
        private readonly string $projectRoot,
        private readonly string $configuredRoot,
        private readonly string $mode,
        private readonly string $approvedProductionRoot = self::APPROVED_PRODUCTION_ROOT,
    ) {
    }

    /** @return array<string, mixed> */
    public function paths(): array
    {
        if (!in_array($this->mode, [self::MODE_DEVELOPMENT, self::MODE_PRODUCTION], true)) {
            throw new RuntimeException('runtime_data_mode_invalid');
        }

        $projectRoot = $this->existingCanonicalDirectory($this->projectRoot);
        $root = $this->lexicalLocalPath($this->configuredRoot);

        if ($this->mode === self::MODE_PRODUCTION) {
            $approved = $this->lexicalLocalPath($this->approvedProductionRoot);
            if (!$this->samePath($root, $approved)) {
                throw new RuntimeException('runtime_data_root_unapproved');
            }
            $root = $this->existingCanonicalDirectory($root, true);
            if ($this->isWithin($root, $projectRoot)) {
                throw new RuntimeException('runtime_data_root_overlaps_source');
            }
        } else {
            $root = $this->existingCanonicalDirectory($root);
        }

        $paths = ['runtime_data_root' => $root];
        foreach (self::DIRECTORY_KEYS as $key => $name) {
            $paths[$key] = $root . DIRECTORY_SEPARATOR . $name;
        }

        if ($this->mode === self::MODE_PRODUCTION) {
            foreach (self::DIRECTORY_KEYS as $key => $_name) {
                $paths[$key] = $this->existingCanonicalDirectory((string) $paths[$key], true);
            }
        }

        $paths['log_path'] = $paths['log_directory'] . DIRECTORY_SEPARATOR . 'app.log';
        $paths['database_operation_lock_path'] = $paths['database_operation_directory']
            . DIRECTORY_SEPARATOR . 'global.lock';
        $paths['backup_database_operation_lock_path'] = $paths['database_operation_lock_path'];
        $paths['restore_database_operation_lock_path'] = $paths['database_operation_lock_path'];
        $paths['allowed_backup_roots'] = [$paths['backup_path']];

        unset($paths['log_directory'], $paths['database_operation_directory']);
        return $paths;
    }

    /** @param array<string, mixed> $paths @return list<string> */
    public static function writableDirectories(array $paths): array
    {
        $keys = [
            'backup_path',
            'job_path',
            'restore_job_path',
            'restore_integration_job_path',
            'restore_integration_ownership_path',
            'temporary_path',
            'session_path',
        ];
        $directories = [];
        foreach ($keys as $key) {
            if (!is_string($paths[$key] ?? null) || $paths[$key] === '') {
                throw new RuntimeException('runtime_data_path_invalid');
            }
            $directories[] = $paths[$key];
        }
        foreach (['log_path', 'database_operation_lock_path'] as $fileKey) {
            if (!is_string($paths[$fileKey] ?? null) || $paths[$fileKey] === '') {
                throw new RuntimeException('runtime_data_path_invalid');
            }
            $directories[] = dirname($paths[$fileKey]);
        }
        return array_values(array_unique($directories));
    }

    private function lexicalLocalPath(string $path): string
    {
        if ($path === '' || preg_match('/[\x00-\x1f]/', $path) === 1
            || str_starts_with($path, '\\\\') || str_starts_with($path, '//')
            || preg_match('#(?:^|[\\\\/])\.\.?([\\\\/]|$)#D', $path) === 1
            || preg_match('/^[A-Za-z]:[\\\\\/]/D', $path) !== 1) {
            throw new RuntimeException('runtime_data_root_invalid');
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $normalized = rtrim($normalized, DIRECTORY_SEPARATOR);
        if ($normalized === '' || preg_match('/^[A-Za-z]:$/D', $normalized) === 1) {
            throw new RuntimeException('runtime_data_root_invalid');
        }
        return $normalized;
    }

    private function existingCanonicalDirectory(string $path, bool $rejectIndirection = false): string
    {
        $lexical = $this->lexicalLocalPath($path);
        if (!is_dir($lexical) || is_link($lexical)) {
            throw new RuntimeException('runtime_data_directory_unsafe');
        }
        if ($rejectIndirection) {
            $this->assertNoPathIndirection($lexical);
        }
        $resolved = realpath($lexical);
        if (!is_string($resolved) || $resolved === '') {
            throw new RuntimeException('runtime_data_directory_unsafe');
        }
        $resolved = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $resolved), DIRECTORY_SEPARATOR);
        return $resolved;
    }

    private function assertNoPathIndirection(string $path): void
    {
        $drive = substr($path, 0, 2);
        $relative = ltrim(substr($path, 2), DIRECTORY_SEPARATOR);
        $current = $drive . DIRECTORY_SEPARATOR;
        foreach (explode(DIRECTORY_SEPARATOR, $relative) as $segment) {
            if ($segment === '') {
                continue;
            }
            $current = rtrim($current, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $segment;
            if (@filetype($current) !== 'dir') {
                throw new RuntimeException('runtime_data_reparse_point_rejected');
            }
        }
    }

    private function isWithin(string $candidate, string $parent): bool
    {
        $candidate = rtrim($candidate, DIRECTORY_SEPARATOR);
        $parent = rtrim($parent, DIRECTORY_SEPARATOR);
        return $this->samePath($candidate, $parent)
            || str_starts_with(strtolower($candidate), strtolower($parent . DIRECTORY_SEPARATOR));
    }

    private function samePath(string $left, string $right): bool
    {
        return strcasecmp(
            rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $left), DIRECTORY_SEPARATOR),
            rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $right), DIRECTORY_SEPARATOR)
        ) === 0;
    }
}
