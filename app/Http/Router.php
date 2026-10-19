<?php

declare(strict_types=1);

namespace BackupApp\Http;

final class Router
{
    /**
     * @return array{name: string, status: int, view: string}
     */
    public static function resolve(?string $page): array
    {
        $page = $page === null || $page === '' ? 'dashboard' : $page;
        $routes = [
            'dashboard' => 'dashboard',
            'backup' => 'backup',
            'import' => 'import',
            'history' => 'history',
            'settings' => 'settings',
        ];

        if (!isset($routes[$page])) {
            return ['name' => 'not-found', 'status' => 404, 'view' => 'placeholder'];
        }

        return ['name' => $page, 'status' => 200, 'view' => $routes[$page]];
    }
}

