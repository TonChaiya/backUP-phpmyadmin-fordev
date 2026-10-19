<?php

declare(strict_types=1);

namespace BackupApp\Support;

final class Auth
{
    /**
     * @param array{username?: string, password_hash?: string} $config
     */
    public function __construct(array $config = [])
    {
    }

    public function isConfigured(): bool
    {
        return false; // Local-only manager: no application login screen.
    }

    public function isDirectLoopback(string $remoteAddress): bool
    {
        return $remoteAddress === '127.0.0.1' || $remoteAddress === '::1';
    }

    public function isAuthorized(string $remoteAddress, ?string $host = null): bool
    {
        if (!$this->isDirectLoopback($remoteAddress)) { return false; }
        // One-argument CLI callers are isolated tests; HTTP entrypoints always supply Host explicitly.
        if ($host === null && PHP_SAPI === 'cli') { return true; }
        $host ??= (string) ($_SERVER['HTTP_HOST'] ?? '');
        if (preg_match('/^(?:localhost|127\.0\.0\.1|\[::1\])(?::([0-9]{1,5}))?$/iD', $host, $match) !== 1) { return false; }
        return !isset($match[1]) || ((int) $match[1] >= 1 && (int) $match[1] <= 65535);
    }

    public function attempt(string $username, string $password): bool
    {
        return false; // Application login is retired; access remains direct-loopback only.
    }

    public function logout(): void
    {
        unset(
            $_SESSION['auth'],
            $_SESSION['connection'],
            $_SESSION['databases'],
            $_SESSION['backup_job_ids'],
            $_SESSION['active_backup_job_id'],
            $_SESSION['backup_job_contexts'],
            $_SESSION['backup_connection_context'],
            $_SESSION['restore_integration_job_ids'],
            $_SESSION['active_restore_integration_job_id']
        );
        Session::regenerate();
    }
}

