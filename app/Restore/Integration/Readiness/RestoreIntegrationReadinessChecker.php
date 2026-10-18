<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Readiness;

use BackupApp\Restore\Integration\Attestation\PrivilegeEvidence;
use BackupApp\Restore\Integration\Attestation\ServerIdentity;
use BackupApp\Restore\Integration\Policy\RestoreIntegrationPolicy;
use BackupApp\Restore\Integration\Policy\TrustedRunId;

final class RestoreIntegrationReadinessChecker
{
    public const EXPECTED_PRODUCT = 'mysql';
    public const EXPECTED_VERSION = '8.4.3';
    public const EXPECTED_ENDPOINT = '127.0.0.1:3306';
    public const FORBIDDEN_ENDPOINT = '127.0.0.1:3333';

    public function __construct(private readonly RestoreIntegrationPolicy $policy) {}

    public function planOnly(TrustedRunId $runId): ReadinessAssessment
    {
        return $this->assess($runId, ReadinessEvidence::planOnly(), 'plan_only');
    }

    public function assess(
        TrustedRunId $runId,
        ReadinessEvidence $evidence,
        string $mode = 'offline_evidence'
    ): ReadinessAssessment {
        $sources = $this->policy->authorizedSources();
        $targets = array_map(
            fn (string $source): string => $this->policy->targetFor($source, $runId)->value(),
            $sources
        );
        $readerIdentity = $evidence->readerIdentity();
        $runnerIdentity = $evidence->runnerIdentity();

        $checks = [
            'expected_endpoint' => $this->endpointCheck($readerIdentity, $runnerIdentity),
            'native_server_identity' => $this->identityCheck($readerIdentity, $runnerIdentity),
            'mysql_version_dialect' => $this->versionCheck($readerIdentity, $runnerIdentity),
            'same_server_identity' => $this->sameServerCheck($readerIdentity, $runnerIdentity),
            'dedicated_credentials' => $this->credentialCheck($evidence),
            'exact_privilege_scope' => $this->privilegeCheck(
                $evidence,
                $readerIdentity,
                $runnerIdentity,
                $sources,
                $targets
            ),
            'grant_wildcard_semantics' => $this->attestationFacetCheck($evidence, 'unauthorized_or_wildcard_scope'),
            'active_roles_partial_revokes' => $this->roleModeCheck($evidence),
            'source_allowlist' => $this->exactBooleanMapCheck(
                $evidence->sourceCapabilities(),
                $sources,
                'source_scope_outside_allowlist',
                'source_capability_missing',
                'source_capability_denied'
            ),
            'target_isolation' => $this->exactBooleanMapCheck(
                $evidence->targetAbsence(),
                $targets,
                'target_scope_outside_proposal',
                'target_absence_not_verified',
                'target_collision'
            ),
            'shared_lock_readiness' => $this->lockCheck($evidence),
            'restore_safety_gate' => $evidence->restoreExecutionHardDisabled()
                ? self::check('pass')
                : self::check('blocked', ['live_execution_not_hard_disabled']),
        ];

        return new ReadinessAssessment(
            $mode,
            $runId->value(),
            $sources,
            $targets,
            $checks,
            [
                'server_identity_unverified_or_mismatch',
                'endpoint_not_exactly_127.0.0.1:3306',
                'port_3333_detected',
                'dedicated_credentials_missing_or_not_separated',
                'privilege_scope_unproven_or_excessive',
                'wildcard_or_cross_schema_grant_detected',
                'active_role_or_partial_revokes_unresolved',
                'source_outside_exact_allowlist',
                'target_collision_or_isolation_unproven',
                'shared_lock_quiescence_unproven',
                'restore_execution_not_hard_disabled',
            ]
        );
    }

    /** @return array{status: string, findings: list<string>} */
    private function endpointCheck(?ServerIdentity $reader, ?ServerIdentity $runner): array
    {
        if ($reader === null || $runner === null) {
            return self::check('not_verified', ['endpoint_evidence_missing']);
        }
        $endpoints = [$reader->endpoint(), $runner->endpoint()];
        if (in_array(self::FORBIDDEN_ENDPOINT, $endpoints, true)) {
            return self::check('blocked', ['endpoint_drift_forbidden']);
        }
        if ($reader->endpoint() !== self::EXPECTED_ENDPOINT || $runner->endpoint() !== self::EXPECTED_ENDPOINT) {
            return self::check('blocked', ['unexpected_endpoint']);
        }
        return self::check('pass');
    }

    /** @return array{status: string, findings: list<string>} */
    private function identityCheck(?ServerIdentity $reader, ?ServerIdentity $runner): array
    {
        if ($reader === null || $runner === null) {
            return self::check('not_verified', ['native_server_identity_missing']);
        }
        foreach ([$reader, $runner] as $identity) {
            if (!$identity->supportedLocalEndpoint()
                || $identity->product() !== self::EXPECTED_PRODUCT
                || !str_starts_with($identity->stableId(), self::EXPECTED_PRODUCT . ':')) {
                return self::check('blocked', ['native_server_identity_invalid']);
            }
        }
        return self::check('pass');
    }

    /** @return array{status: string, findings: list<string>} */
    private function versionCheck(?ServerIdentity $reader, ?ServerIdentity $runner): array
    {
        if ($reader === null || $runner === null) {
            return self::check('not_verified', ['version_dialect_evidence_missing']);
        }
        if ($reader->product() !== self::EXPECTED_PRODUCT || $runner->product() !== self::EXPECTED_PRODUCT
            || $reader->version() !== self::EXPECTED_VERSION || $runner->version() !== self::EXPECTED_VERSION) {
            return self::check('blocked', ['unexpected_version_or_dialect']);
        }
        return self::check('pass');
    }

    /** @return array{status: string, findings: list<string>} */
    private function sameServerCheck(?ServerIdentity $reader, ?ServerIdentity $runner): array
    {
        if ($reader === null || $runner === null) {
            return self::check('not_verified', ['same_server_evidence_missing']);
        }
        return $reader->sameServer($runner)
            ? self::check('pass')
            : self::check('blocked', ['server_identity_mismatch']);
    }

    /** @return array{status: string, findings: list<string>} */
    private function credentialCheck(ReadinessEvidence $evidence): array
    {
        $values = [
            $evidence->backupReaderCredentialAvailable(),
            $evidence->restoreRunnerCredentialAvailable(),
            $evidence->credentialsSeparated(),
        ];
        if (in_array(null, $values, true)) {
            return self::check('not_verified', ['dedicated_credential_evidence_missing']);
        }
        return in_array(false, $values, true)
            ? self::check('blocked', ['dedicated_credentials_missing_or_not_separated'])
            : self::check('pass');
    }

    /** @param list<string> $sources @param list<string> $targets @return array{status: string, findings: list<string>} */
    private function privilegeCheck(
        ReadinessEvidence $evidence,
        ?ServerIdentity $readerIdentity,
        ?ServerIdentity $runnerIdentity,
        array $sources,
        array $targets
    ): array {
        $reader = $evidence->readerPrivileges();
        $runner = $evidence->runnerPrivileges();
        if ($reader === null || $runner === null || $readerIdentity === null || $runnerIdentity === null) {
            return self::check('not_verified', ['privilege_attestation_missing']);
        }
        if (!$reader->identity()->sameServer($readerIdentity)
            || !$runner->identity()->sameServer($runnerIdentity)) {
            return self::check('blocked', ['privilege_identity_mismatch']);
        }
        if (!$reader->accepted() || !$runner->accepted()
            || $reader->findings() !== [] || $runner->findings() !== []
            || $reader->role() !== 'backup_reader' || $runner->role() !== 'restore_runner'
            || !$this->sameSet($reader->scopes(), $sources) || !$this->sameSet($runner->scopes(), $targets)) {
            return self::check('blocked', ['privilege_scope_not_exact']);
        }
        return self::check('pass');
    }

    /** @return array{status: string, findings: list<string>} */
    private function attestationFacetCheck(ReadinessEvidence $evidence, string $finding): array
    {
        $items = [$evidence->readerPrivileges(), $evidence->runnerPrivileges()];
        if (in_array(null, $items, true)) {
            return self::check('not_verified', ['grant_semantics_not_attested']);
        }
        foreach ($items as $item) {
            if (!$item instanceof PrivilegeEvidence || !$item->accepted() || in_array($finding, $item->findings(), true)) {
                return self::check('blocked', ['grant_wildcard_or_scope_risk']);
            }
        }
        return self::check('pass');
    }

    /** @return array{status: string, findings: list<string>} */
    private function roleModeCheck(ReadinessEvidence $evidence): array
    {
        $items = [$evidence->readerPrivileges(), $evidence->runnerPrivileges()];
        if (in_array(null, $items, true)) {
            return self::check('not_verified', ['active_roles_partial_revokes_not_attested']);
        }
        $unsafe = ['role_evidence_incomplete_or_active', 'partial_revokes_mode_unsupported', 'partial_revokes_unverified'];
        foreach ($items as $item) {
            if (!$item instanceof PrivilegeEvidence || !$item->accepted() || array_intersect($unsafe, $item->findings()) !== []) {
                return self::check('blocked', ['active_roles_or_partial_revokes_risk']);
            }
        }
        return self::check('pass');
    }

    /**
     * @param array<string, bool> $actual
     * @param list<string> $expected
     * @return array{status: string, findings: list<string>}
     */
    private function exactBooleanMapCheck(
        array $actual,
        array $expected,
        string $outsideFinding,
        string $missingFinding,
        string $falseFinding
    ): array {
        if (array_diff(array_keys($actual), $expected) !== []) {
            return self::check('blocked', [$outsideFinding]);
        }
        if (array_diff($expected, array_keys($actual)) !== []) {
            return self::check('not_verified', [$missingFinding]);
        }
        foreach ($expected as $name) {
            if (($actual[$name] ?? false) !== true) {
                return self::check('blocked', [$falseFinding]);
            }
        }
        return self::check('pass');
    }

    /** @return array{status: string, findings: list<string>} */
    private function lockCheck(ReadinessEvidence $evidence): array
    {
        $values = [
            $evidence->sharedLockConfigured(),
            $evidence->legacyLockQuiescent(),
            $evidence->sharedLockQuiescent(),
        ];
        if (in_array(null, $values, true)) {
            return self::check('not_verified', ['shared_lock_quiescence_not_verified']);
        }
        return in_array(false, $values, true)
            ? self::check('blocked', ['shared_lock_not_ready'])
            : self::check('pass');
    }

    /** @param list<string> $left @param list<string> $right */
    private function sameSet(array $left, array $right): bool
    {
        sort($left, SORT_STRING);
        sort($right, SORT_STRING);
        return $left === $right;
    }

    /** @param list<string> $findings @return array{status: string, findings: list<string>} */
    private static function check(string $status, array $findings = []): array
    {
        return ['status' => $status, 'findings' => $findings];
    }
}
