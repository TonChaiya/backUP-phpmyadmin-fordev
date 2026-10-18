<?php

declare(strict_types=1);

namespace BackupApp\Support;

final class Auth
{
    /**
     * @param array{username?: string, password_hash?: string} $config
     */
    public function __construct(private array $config)
    {
    }

    public function isConfigured(): bool
    {
        return trim((string) ($this->config['username'] ?? '')) !== ''
            && trim((string) ($this->config['password_hash'] ?? '')) !== '';
    }

    public function isDirectLoopback(string $remoteAddress): bool
    {
        return $remoteAddress === '127.0.0.1' || $remoteAddress === '::1';
    }

    public function isAuthorized(string $remoteAddress): bool
    {
        if (!$this->isConfigured()) {
            return $this->isDirectLoopback($remoteAddress);
        }

        return ($_SESSION['auth']['authenticated'] ?? false) === true
            && hash_equals(
                (string) $this->config['username'],
                (string) ($_SESSION['auth']['username'] ?? '')
            );
    }

    public function attempt(string $username, string $password): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $validUsername = hash_equals((string) $this->config['username'], $username);
        $validPassword = password_verify($password, (string) $this->config['password_hash']);

        if (!$validUsername || !$validPassword) {
            return false;
        }

        Session::regenerate();
        $_SESSION['auth'] = [
            'authenticated' => true,
            'username' => (string) $this->config['username'],
            'authenticated_at' => time(),
        ];

        return true;
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

