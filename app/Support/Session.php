<?php

declare(strict_types=1);

namespace BackupApp\Support;

final class Session
{
    /**
     * @return array{lifetime: int, path: string, domain: string, secure: bool, httponly: bool, samesite: string}
     */
    public static function cookieOptions(bool $https): array
    {
        return [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Strict',
        ];
    }

    public static function start(bool $https): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');
        session_name('backup_manager_session');
        session_set_cookie_params(self::cookieOptions($https));
        session_start();
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Strict',
            ]);
        }

        session_destroy();
    }

    public static function requestIsHttps(array $server): bool
    {
        return isset($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off';
    }
}

