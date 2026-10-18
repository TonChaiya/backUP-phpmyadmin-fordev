<?php

declare(strict_types=1);

namespace BackupApp\Restore;

final class RestoreSafetyGate
{
    /** @return array{open: false, code: string, checks: array<string, string>} */
    public function evaluate(): array
    {
        return [
            'open' => false,
            'code' => 'live_restore_not_verified',
            'checks' => [
                'isolated_instance' => 'not_verified',
                'dedicated_credentials' => 'not_verified',
                'target_privileges' => 'not_verified',
                'integration_test' => 'not_verified',
            ],
        ];
    }
}
