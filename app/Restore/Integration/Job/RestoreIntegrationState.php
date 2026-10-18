<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Job;

use DomainException;

final class RestoreIntegrationState
{
    public const STATES = ['queued', 'validating', 'running', 'succeeded', 'failed', 'interrupted', 'blocked'];
    private const TRANSITIONS = [
        'queued' => ['queued', 'validating', 'interrupted'],
        'validating' => ['validating', 'running', 'blocked', 'failed', 'interrupted'],
        'running' => ['running', 'succeeded', 'failed', 'interrupted', 'blocked'],
        'succeeded' => ['succeeded'], 'failed' => ['failed'], 'interrupted' => ['interrupted'], 'blocked' => ['blocked'],
    ];
    public static function assertTransition(string $from, string $to): void
    {
        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) { throw new DomainException('invalid_restore_integration_transition'); }
    }
}
