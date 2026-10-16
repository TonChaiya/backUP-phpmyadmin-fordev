<?php

declare(strict_types=1);

namespace BackupApp\Support;

final class Security
{
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function validateCsrf(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function safeFilename(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $name) ?? 'backup';

        return trim($name, '._') ?: 'backup';
    }
}
