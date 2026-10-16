<?php

declare(strict_types=1);

namespace BackupApp\Support;

final class Request
{
    public static function postString(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $default;

        return is_string($value) ? trim($value) : $default;
    }

    /**
     * @return array<int, string>
     */
    public static function postArray(string $key): array
    {
        $value = $_POST[$key] ?? [];

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($item): string {
            return is_string($item) ? trim($item) : '';
        }, $value)));
    }
}
