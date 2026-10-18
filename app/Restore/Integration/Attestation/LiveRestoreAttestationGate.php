<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Attestation;

use BackupApp\Restore\Integration\Policy\RestoreIntegrationPolicy;
use BackupApp\Restore\Integration\Policy\TrustedRunId;
use BackupApp\Restore\Integration\Policy\TrustedTargetName;

final class LiveRestoreAttestationGate
{
    public function __construct(private readonly RestoreIntegrationPolicy $policy = new RestoreIntegrationPolicy()) {}
    /** @param list<TrustedTargetName> $targets @return array{open: bool, code: string, checks: array<string, bool>} */
    public function evaluate(
        ServerIdentity $reader,
        ServerIdentity $runner,
        PrivilegeEvidence $readerPrivileges,
        PrivilegeEvidence $runnerPrivileges,
        TrustedRunId $runId,
        array $targets
    ): array
    {
        $checks = [
            'same_server' => $reader->sameServer($runner)
                && $readerPrivileges->identity()->sameServer($reader)
                && $runnerPrivileges->identity()->sameServer($runner),
            'local_supported_server' => $reader->supportedLocalEndpoint() && $runner->supportedLocalEndpoint(),
            'backup_reader_privileges' => $readerPrivileges->accepted() && $readerPrivileges->role() === 'backup_reader',
            'restore_runner_privileges' => $runnerPrivileges->accepted() && $runnerPrivileges->role() === 'restore_runner',
            'backup_reader_exact_scope' => $this->sameScopes($this->policy->authorizedSources(), $readerPrivileges->scopes()),
            'restore_runner_exact_scope' => $this->sameScopes(
                array_map(static fn (TrustedTargetName $target): string => $target->value(), $targets),
                $runnerPrivileges->scopes()
            ),
            'run_id' => preg_match('/^[a-f0-9]{32}$/D', $runId->value()) === 1,
            'targets' => $targets !== [] && count(array_filter($targets, static fn ($t): bool => $t instanceof TrustedTargetName)) === count($targets),
            'target_run_scope' => $targets !== [] && count(array_filter(
                $targets,
                static fn ($target): bool => $target instanceof TrustedTargetName && str_ends_with($target->value(), '_' . $runId->value())
            )) === count($targets),
        ];
        $open = !in_array(false, $checks, true);
        return ['open' => $open, 'code' => $open ? 'attested' : 'attestation_failed', 'checks' => $checks];
    }

    /** @param list<string> $expected @param list<string> $actual */
    private function sameScopes(array $expected, array $actual): bool
    {
        sort($expected, SORT_STRING); sort($actual, SORT_STRING);
        return $expected === $actual;
    }
}
