<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Http;

use BackupApp\Support\Auth;
use BackupApp\Support\Security;

final class RestoreIntegrationApiAuthorization
{
    /** @return array{allowed: bool, code: string} */
    public function authorize(Auth $auth, string $remoteAddress, ?string $csrf, string $action): array
    {
        if (!$auth->isAuthorized($remoteAddress)) { return ['allowed' => false, 'code' => 'authentication_required']; }
        if (!Security::validateCsrf($csrf)) { return ['allowed' => false, 'code' => 'csrf_invalid']; }
        if (in_array($action, ['authorize', 'execute', 'cleanup'], true)) { return ['allowed' => false, 'code' => 'live_restore_execution_disabled']; }
        if (!in_array($action, ['proposal', 'status'], true)) { return ['allowed' => false, 'code' => 'unknown_action']; }
        return ['allowed' => true, 'code' => 'authorized_offline_action'];
    }
}
