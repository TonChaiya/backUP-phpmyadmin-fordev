<?php

declare(strict_types=1);

namespace BackupApp\Http\Controller;

use BackupApp\Support\BackupPathPolicy;

final class SettingsController
{
    public function __construct(private BackupPathPolicy $pathPolicy)
    {
    }

    public function activePath(string $configuredDefault): string
    {
        $candidate = is_string($_SESSION['backup_path'] ?? null)
            ? $_SESSION['backup_path']
            : $configuredDefault;

        try {
            return $this->pathPolicy->resolve($candidate);
        } catch (\Throwable) {
            unset($_SESSION['backup_path']);

            return $this->pathPolicy->defaultRoot();
        }
    }

    public function setPath(string $path): string
    {
        $resolved = $this->pathPolicy->resolve($path, true);
        $_SESSION['backup_path'] = $resolved;

        return $resolved;
    }

    public function openFolder(string $path): bool
    {
        $resolved = $this->pathPolicy->resolve($path);

        if (!function_exists('proc_open')) {
            return false;
        }

        $command = match (PHP_OS_FAMILY) {
            'Windows' => ['explorer.exe', $resolved],
            'Darwin' => ['open', $resolved],
            default => ['xdg-open', $resolved],
        };
        $process = @proc_open($command, [
            ['pipe', 'r'],
            ['pipe', 'w'],
            ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($process)) {
            return false;
        }

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_close($process);

        return true;
    }
}

