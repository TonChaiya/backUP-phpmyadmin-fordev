<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Readiness;

final class ReadinessAssessment
{
    /**
     * @param list<string> $authorizedSources
     * @param list<string> $proposedTargets
     * @param array<string, array{status: string, findings: list<string>}> $checks
     * @param list<string> $stopConditions
     */
    public function __construct(
        private readonly string $mode,
        private readonly string $runId,
        private readonly array $authorizedSources,
        private readonly array $proposedTargets,
        private readonly array $checks,
        private readonly array $stopConditions
    ) {}

    /** @return array<string, mixed> */
    public function publicView(): array
    {
        $ready = $this->checks !== [];
        foreach ($this->checks as $check) {
            if ($check['status'] !== 'pass') {
                $ready = false;
                break;
            }
        }

        return [
            'mode' => $this->mode,
            'overall_status' => $ready ? 'ready_for_live_authorization_review' : 'blocked',
            'ready_for_live_authorization' => $ready,
            'run_id' => $this->runId,
            'expected_server' => [
                'product' => RestoreIntegrationReadinessChecker::EXPECTED_PRODUCT,
                'version' => RestoreIntegrationReadinessChecker::EXPECTED_VERSION,
                'endpoint' => RestoreIntegrationReadinessChecker::EXPECTED_ENDPOINT,
                'forbidden_endpoint' => RestoreIntegrationReadinessChecker::FORBIDDEN_ENDPOINT,
            ],
            'authorized_sources' => $this->authorizedSources,
            'proposed_targets' => $this->proposedTargets,
            'inspection_contract' => [
                'database_discovery' => 'exact_allowlist_only',
                'credentials_accepted_by_plan_cli' => false,
                'raw_grants_persisted' => false,
                'database_connection_in_plan_only_mode' => false,
            ],
            'checks' => $this->checks,
            'stop_conditions' => $this->stopConditions,
            'live_operations' => 'hard_disabled',
        ];
    }
}
