<?php

declare(strict_types=1);

namespace BackupApp\Support;

use RuntimeException;

final class RuntimeDataBootstrap
{
    /** @param array<string, mixed> $config @return array<string, mixed> */
    public function prepare(array $config, string $projectRoot): array
    {
        $mode = is_string($config['runtime_data_mode'] ?? null) ? $config['runtime_data_mode'] : '';
        $root = is_string($config['runtime_data_root'] ?? null) ? $config['runtime_data_root'] : '';
        $paths = (new RuntimeDataPaths($projectRoot, $root, $mode))->paths();

        if ($mode === RuntimeDataPaths::MODE_DEVELOPMENT) {
            foreach (RuntimeDataPaths::writableDirectories($paths) as $directory) {
                if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
                    throw new RuntimeException('runtime_data_directory_create_failed');
                }
            }
        }

        return array_replace($config, $paths);
    }

    public function configureSession(string $path): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || !is_dir($path)
            || session_save_path($path) === false) {
            throw new RuntimeException('runtime_session_path_invalid');
        }
    }
}
