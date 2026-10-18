<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Provisioning;

use BackupApp\Restore\Integration\Policy\RestoreIntegrationPolicy;
use BackupApp\Restore\Integration\Policy\TrustedRunId;

final class CredentialProvisioningPlan
{
    private const READER_PRIVILEGES = ['SELECT', 'SHOW VIEW', 'TRIGGER', 'LOCK TABLES'];
    private const RUNNER_PRIVILEGES = [
        'CREATE',
        'DROP',
        'ALTER',
        'INDEX',
        'INSERT',
        'SELECT',
        'REFERENCES',
        'LOCK TABLES',
        'CREATE VIEW',
    ];

    public function __construct(
        private readonly RestoreIntegrationPolicy $policy,
        private readonly TrustedRunId $runId
    ) {}

    /** @return array<string, mixed> */
    public function publicView(): array
    {
        $sources = $this->policy->authorizedSources();
        $targets = array_map(
            fn (string $source): string => $this->policy->targetFor($source, $this->runId)->value(),
            $sources
        );

        return [
            'mode' => 'provisioning_preparation_only',
            'authorization_status' => 'not_authorized',
            'live_operations' => 'hard_disabled',
            'database_connection' => false,
            'sql_commands_emitted' => false,
            'run_id' => $this->runId->value(),
            'server' => [
                'product' => 'mysql',
                'version' => '8.4.3',
                'endpoint' => '127.0.0.1:3306',
                'forbidden_endpoints' => ['127.0.0.1:3333'],
            ],
            'accounts' => [
                'backup_reader' => array_merge(
                    $this->account('backup_reader', $sources, self::READER_PRIVILEGES),
                    [
                        'privilege_risks' => [
                            'TRIGGER' => 'dual_use_metadata_privilege_requires_live_risk_acceptance',
                        ],
                        'unsupported_object_families' => ['events', 'routines'],
                    ]
                ),
                'restore_runner' => $this->account(
                    'restore_runner',
                    $targets,
                    self::RUNNER_PRIVILEGES
                ),
            ],
            'forbidden_capabilities' => [
                'global_scope',
                'grant_option',
                'roles',
                'wildcard_or_prefix_scope',
                'source_scope_for_restore_runner',
                'runtime_root_account',
            ],
            'verification' => [
                'native_server_identity' => 'not_verified',
                'endpoint_identity' => 'not_verified',
                'current_user' => 'not_verified',
                'actual_grants' => 'not_verified',
                'active_roles' => 'not_verified',
                'partial_revokes' => 'not_verified',
                'source_capabilities' => 'not_verified',
                'target_absence' => 'not_verified',
                'shared_lock_quiescence' => 'not_verified',
                'restore_safety_gates' => 'offline_only',
            ],
        ];
    }

    /** @param list<string> $scopes @param list<string> $privileges @return array<string, mixed> */
    private function account(string $username, array $scopes, array $privileges): array
    {
        return [
            'username' => $username,
            'host' => 'localhost',
            'expected_current_user' => $username . '@localhost',
            'exact_database_scopes' => $scopes,
            'database_grant_scopes' => array_map($this->escapeDatabaseGrantScope(...), $scopes),
            'privileges' => $privileges,
            'global_privileges' => [],
            'roles' => [],
            'grant_option' => false,
        ];
    }

    private function escapeDatabaseGrantScope(string $scope): string
    {
        return strtr($scope, ['\\' => '\\\\', '_' => '\\_', '%' => '\\%']);
    }
}
