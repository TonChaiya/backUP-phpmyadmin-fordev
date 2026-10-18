<?php

declare(strict_types=1);

namespace BackupApp\Support;

use RuntimeException;

final class View
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $config
     */
    public static function render(
        string $view,
        array $data,
        string $activePage,
        array $config,
        bool $showNavigation = true
    ): string {
        if (preg_match('/^[a-z-]+$/', $view) !== 1) {
            throw new RuntimeException('Invalid view');
        }

        $viewRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views';
        $viewFile = $viewRoot . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . $view . '.php';

        if (!is_file($viewFile)) {
            throw new RuntimeException('View not found');
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        ob_start();
        require $viewRoot . DIRECTORY_SEPARATOR . 'layout.php';

        return (string) ob_get_clean();
    }

    public static function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $bytes;

        foreach ($units as $unit) {
            if ($size < 1024 || $unit === 'GB') {
                return number_format($size, $unit === 'B' ? 0 : 2) . ' ' . $unit;
            }

            $size /= 1024;
        }

        return $bytes . ' B';
    }
}

