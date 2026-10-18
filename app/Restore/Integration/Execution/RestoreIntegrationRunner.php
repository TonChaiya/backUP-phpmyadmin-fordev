<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Execution;

use BackupApp\Restore\Integration\Authorization\ExecutionAuthorization;
use BackupApp\Restore\Integration\Attestation\LiveRestoreAttestationGate;
use BackupApp\Restore\Integration\Attestation\PrivilegeEvidence;
use BackupApp\Restore\Integration\Attestation\ServerIdentity;
use BackupApp\Restore\Integration\Database\RestoreConnectionFactory;
use BackupApp\Restore\Integration\Database\OfflineRestoreConnection;
use BackupApp\Database\DatabaseOperationLock;
use BackupApp\Restore\Integration\Policy\TrustedRunId;
use BackupApp\Restore\Integration\Policy\TrustedTargetName;
use BackupApp\Restore\Integration\Policy\RestoreIntegrationPolicy;
use BackupApp\Restore\Integration\Sql\RestorePlan;
use BackupApp\Restore\Integration\Ownership\OwnershipJournal;
use BackupApp\Restore\Integration\Ownership\OwnershipEvent;
use BackupApp\Restore\Integration\Validation\PostRestoreValidator;
use DomainException;

final class RestoreIntegrationRunner
{
    public function __construct(
        private readonly RestoreConnectionFactory $connections = new RestoreConnectionFactory(),
        private readonly LiveRestoreAttestationGate $attestation = new LiveRestoreAttestationGate(),
        private readonly ?DatabaseOperationLock $operationLock = null,
        private readonly RestoreIntegrationPolicy $policy = new RestoreIntegrationPolicy(),
        private readonly TargetDatabaseProvisioner $provisioner = new TargetDatabaseProvisioner(),
        private readonly TypedRestoreExecutor $executor = new TypedRestoreExecutor(),
        private readonly PostRestoreValidator $validator = new PostRestoreValidator(),
        private readonly CleanupCoordinator $cleanup = new CleanupCoordinator(new \BackupApp\Restore\Integration\Ownership\CleanupDecisionEngine())
    ) {}

    /** @param array<string, mixed> $backupCredentials @param array<string, mixed> $runnerCredentials */
    public function run(string $jobId, array $backupCredentials, array $runnerCredentials, ExecutionAuthorization $authorization): array
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $jobId) !== 1) { throw new DomainException('invalid_integration_job_id'); }
        $this->connections->assertSeparatedRoles($backupCredentials, $runnerCredentials);
        throw new DomainException('live_restore_execution_disabled');
    }

    /** @param list<TrustedTargetName> $targets @return array{ready: false, code: string, checks: array<string, bool>} */
    public function assessOfflineReadiness(
        string $jobId,
        TrustedRunId $runId,
        ServerIdentity $reader,
        ServerIdentity $runner,
        PrivilegeEvidence $readerPrivileges,
        PrivilegeEvidence $runnerPrivileges,
        array $targets,
        ExecutionAuthorization $authorization,
        array $artifactSha256 = [],
        string $contextId = ''
    ): array {
        $gate = $this->attestation->evaluate($reader, $runner, $readerPrivileges, $runnerPrivileges, $runId, $targets);
        $checks = $gate['checks'];
        $checks['authorization'] = preg_match('/^[a-f0-9]{32}$/D', $jobId) === 1
            && $authorization->validFor($jobId, $runId->value(), $runner->fingerprint());
        $checks['exact_target_scope'] = $authorization->validForTargets(
            array_map(static fn (TrustedTargetName $target): string => $target->value(), $targets)
        );
        $checks['artifact_scope'] = $authorization->validForArtifacts($artifactSha256);
        $checks['context_scope'] = $authorization->validForContext($contextId);
        $checks['shared_lock_contract'] = false;
        if ($this->operationLock !== null && preg_match('/^[a-f0-9]{32}$/D', $jobId) === 1) {
            $handle = $this->operationLock->acquire('restore-' . $jobId, false);
            if (is_resource($handle)) {
                $checks['shared_lock_contract'] = true;
                $this->operationLock->release($handle);
            }
        }
        return ['ready' => false, 'code' => in_array(false, $checks, true) ? 'safety_gate_failed' : 'live_restore_execution_disabled', 'checks' => $checks];
    }

    /**
     * Executes the complete orchestration only against an explicitly marked
     * fake adapter. Production mysqli does not implement this interface.
     * @return array{status: string, code: string, comparison: array<string, mixed>|null, cleanup: array<string, mixed>|null}
     */
    public function runOfflineFixture(
        string $jobId,
        TrustedRunId $runId,
        string $artifactPath,
        RestorePlan $plan,
        TrustedTargetName $target,
        ServerIdentity $readerIdentity,
        ServerIdentity $runnerIdentity,
        PrivilegeEvidence $readerPrivileges,
        PrivilegeEvidence $runnerPrivileges,
        ExecutionAuthorization $authorization,
        string $contextId,
        OfflineRestoreConnection $connection,
        OwnershipJournal $journal
    ): array {
        if ($this->operationLock === null) { throw new DomainException('shared_operation_lock_required'); }
        $expectedTarget = $this->policy->targetFor($plan->sourceDatabase(), $runId);
        if (!$target->equals($expectedTarget)) { throw new DomainException('exact_target_scope_mismatch'); }
        $gate = $this->attestation->evaluate(
            $readerIdentity, $runnerIdentity, $readerPrivileges, $runnerPrivileges, $runId, [$target]
        );
        if (!$gate['open'] || !$authorization->validFor($jobId, $runId->value(), $runnerIdentity->fingerprint())
            || !$authorization->validForTargets([$target->value()])
            || !$authorization->validForArtifacts([$plan->sourceDatabase() => $plan->contentSha256()])
            || !$authorization->validForContext($contextId)) {
            throw new DomainException('restore_safety_gate_closed');
        }
        $lock = $this->operationLock->acquire('restore-' . $jobId, false);
        if (!is_resource($lock)) { throw new DomainException('database_operation_busy'); }
        $marker = null; $comparison = null; $cleanup = null; $code = 'restore_failed';
        try {
            $this->assertArtifactUnchanged($artifactPath, $plan);
            if (!hash_equals($runnerIdentity->fingerprint(), $connection->serverIdentityFingerprint())) {
                throw new DomainException('server_identity_mismatch');
            }
            $authorization->consume();
            $journal->create([
                'server_fingerprint' => $runnerIdentity->fingerprint(), 'source' => $plan->sourceDatabase(),
                'target' => $target->value(), 'job_id' => $jobId,
            ]);
            $created = $this->provisioner->createNew($target, $runId, $connection, $journal, $plan->objectInventory());
            $marker = $created['marker'];
            $this->executor->execute($plan, $target, $connection);
            $report = $this->validator->validate($plan, $connection, $marker);
            $comparison = $report->publicView();
            $code = $report->mandatoryPassed() ? 'comparison_passed' : 'comparison_failed';
        } catch (TargetProvisioningException $exception) {
            $code = $exception->getMessage();
            if ($exception->targetMayExist()) {
                $cleanup = ['allowed' => false, 'verified_absent' => false,
                    'code' => 'cleanup_blocked_ownership_unproven', 'retained_exact_target' => $target->value()];
            }
        } catch (\Throwable $exception) {
            $code = $exception instanceof DomainException ? $exception->getMessage() : 'restore_execution_failed';
        } finally {
            try {
                if ($marker !== null) {
                    $runtime = [
                        'run_id' => $runId->value(), 'server_fingerprint' => $runnerIdentity->fingerprint(),
                        'target' => $target->value(), 'job_id' => $jobId, 'marker_hash' => $marker->nonceHash(),
                        'shared_lock_held' => true, 'session_owned' => true, 'target_exists' => $connection->targetExists($target),
                    ];
                    $cleanup = $this->cleanup->cleanup($journal->read(), $runtime, $target, $marker, $connection);
                    $journal->append(new OwnershipEvent('cleanup_result', $cleanup));
                }
            } catch (\Throwable) {
                $cleanup = ['allowed' => false, 'verified_absent' => false, 'code' => 'cleanup_failed_closed'];
                $code = 'cleanup_failed_closed';
            } finally {
                $this->operationLock->release($lock);
            }
        }
        $succeeded = $code === 'comparison_passed' && ($comparison['mandatory_passed'] ?? false) === true
            && ($cleanup['verified_absent'] ?? false) === true;
        return ['status' => $succeeded ? 'succeeded' : 'failed', 'code' => $succeeded ? 'offline_fixture_completed' : $code,
            'comparison' => $comparison, 'cleanup' => $cleanup];
    }

    private function assertArtifactUnchanged(string $path, RestorePlan $plan): void
    {
        $size = @filesize($path); $sha256 = @hash_file('sha256', $path);
        if (!is_int($size) || !is_string($sha256) || $size !== $plan->contentBytes()
            || !hash_equals($plan->contentSha256(), $sha256)) {
            throw new DomainException('artifact_changed_before_execution');
        }
    }
}
