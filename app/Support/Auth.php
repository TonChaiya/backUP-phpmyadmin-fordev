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

    public function isAuthorized(string $remoteAddress): bool
    {
        return $this->isDirectLoopback($remoteAddress);
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

