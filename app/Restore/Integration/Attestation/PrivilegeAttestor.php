<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Attestation;

use BackupApp\Restore\Integration\Policy\RestoreIntegrationPolicy;
use BackupApp\Restore\Integration\Policy\TrustedTargetName;

final class PrivilegeAttestor
{
    private const READER_ALLOWED = ['SELECT', 'SHOW VIEW', 'TRIGGER', 'LOCK TABLES', 'EVENT', 'EXECUTE'];
    private const RUNNER_ALLOWED = ['CREATE', 'DROP', 'ALTER', 'INDEX', 'INSERT', 'SELECT', 'REFERENCES', 'LOCK TABLES', 'CREATE VIEW', 'SHOW VIEW', 'TRIGGER', 'CREATE ROUTINE', 'ALTER ROUTINE', 'EXECUTE', 'EVENT'];

    public function __construct(
        private readonly RestoreIntegrationPolicy $policy,
        private readonly PrivilegeDialect $dialect
    ) {}

    /** @param list<string> $grantLines @param array<string, mixed> $context */
    public function backupReaderEvidence(ServerIdentity $identity, string $account, array $grantLines, array $context = []): PrivilegeEvidence
    {
        return $this->evaluate('backup_reader', $identity, $account, $grantLines, $this->policy->authorizedSources(), self::READER_ALLOWED, ['SELECT', 'SHOW VIEW', 'TRIGGER', 'LOCK TABLES'], $context);
    }

    /** @param list<string> $grantLines @param list<TrustedTargetName> $targets @param array<string, mixed> $context */
    public function restoreRunnerEvidence(ServerIdentity $identity, string $account, array $grantLines, array $targets, array $context = []): PrivilegeEvidence
    {
        $names = array_map(static fn (TrustedTargetName $target): string => $target->value(), $targets);
        return $this->evaluate('restore_runner', $identity, $account, $grantLines, $names, self::RUNNER_ALLOWED, ['CREATE', 'DROP', 'ALTER', 'INDEX', 'INSERT', 'SELECT', 'REFERENCES', 'LOCK TABLES'], $context);
    }

    /** @param list<string> $grantLines @param list<string> $expectedScopes @param list<string> $allowedPrivileges @param list<string> $requiredPrivileges @param array<string, mixed> $context */
    private function evaluate(string $role, ServerIdentity $identity, string $account, array $grantLines, array $expectedScopes, array $allowedPrivileges, array $requiredPrivileges, array $context): PrivilegeEvidence
    {
        $findings = [];
        $scopes = [];
        $scopePrivileges = [];
        if (preg_match('/(?:^|`)root(?:`|@)/i', $account) === 1) { $findings[] = 'root_account_forbidden'; }
        if (($context['roles_resolved'] ?? false) !== true
            || ($context['role_grants_resolved'] ?? false) !== true
            || !is_array($context['active_roles'] ?? null)
            || ($context['active_roles'] ?? []) !== []) {
            $findings[] = 'role_evidence_incomplete_or_active';
        }
        if ($identity->product() === 'mysql' && ($context['partial_revokes'] ?? null) !== false) {
            $findings[] = is_bool($context['partial_revokes'] ?? null) ? 'partial_revokes_mode_unsupported' : 'partial_revokes_unverified';
        }
        if ($identity->product() === 'mariadb'
            && (($context['public_resolved'] ?? false) !== true || ($context['public_grants'] ?? null) !== [])) {
            $findings[] = 'public_privileges_unverified_or_present';
        }
        foreach ($grantLines as $line) {
            if (preg_match('/\b(GRANT OPTION|ALL PRIVILEGES|PROXY|PUBLIC)\b|\bON\s+\*\.\*/i', $line) === 1) {
                if (preg_match('/^GRANT\s+USAGE\s+ON\s+\*\.\*\s+TO\s+(?![\s\S]*\bWITH\s+GRANT\s+OPTION\b)[\s\S]+$/i', trim($line)) === 1) {
                    continue;
                }
                $findings[] = 'broad_or_inherited_privilege';
                continue;
            }
            $parsed = $this->dialect->parseDatabaseGrant($line);
            if ($parsed === null) {
                $findings[] = 'unknown_grant_syntax';
                continue;
            }
            if ($parsed['scope'] === '__wildcard__' || !in_array($parsed['scope'], $expectedScopes, true)) {
                $findings[] = 'unauthorized_or_wildcard_scope';
                continue;
            }
            $scopes[] = $parsed['scope'];
            $scopePrivileges[$parsed['scope']] = array_values(array_unique(array_merge($scopePrivileges[$parsed['scope']] ?? [], $parsed['privileges'])));
            if (array_diff($parsed['privileges'], $allowedPrivileges) !== []) {
                $findings[] = 'excess_privilege';
            }
        }
        if (array_diff($expectedScopes, array_unique($scopes)) !== []) { $findings[] = $role === 'backup_reader' ? 'missing_source_scope' : 'missing_target_scope'; }
        foreach ($expectedScopes as $scope) {
            if (array_diff($requiredPrivileges, $scopePrivileges[$scope] ?? []) !== []) { $findings[] = 'missing_required_privilege'; }
        }
        $findings = array_values(array_unique($findings));
        return new PrivilegeEvidence($role, $identity, $findings === [], $findings, array_values(array_unique($scopes)));
    }
}
