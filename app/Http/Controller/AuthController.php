<?php

declare(strict_types=1);

namespace BackupApp\Http\Controller;

use BackupApp\Support\Auth;

final class AuthController
{
    public function __construct(private Auth $auth)
    {
    }

    public function login(string $username, string $password): bool
    {
        return $this->auth->attempt(trim($username), $password);
    }

    public function logout(): void
    {
        $this->auth->logout();
    }
}

