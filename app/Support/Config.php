<?php

declare(strict_types=1);

namespace BackupApp\Support;

use RuntimeException;

final class Config
{
    /**
     * @return array<string, mixed>
     */
    public static function load(string $baseFile, string $localFile): array
    {
        $base = require $baseFile;

        if (!is_array($base)) {
            throw new RuntimeException('Invalid base configuration');
        }

        if (!is_file($localFile)) {
            return $base;
        }

        $local = require $localFile;

        if (!is_array($local)) {
            throw new RuntimeException('Invalid local configuration');
        }

        return array_replace_recursive($base, $local);
    }
}

