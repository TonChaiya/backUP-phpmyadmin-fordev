<?php

declare(strict_types=1);

use BackupApp\Restore\Integration\Attestation\PrivilegeEvidence;
use BackupApp\Restore\Integration\Attestation\ServerIdentity;
use BackupApp\Restore\Integration\Policy\RestoreIntegrationPolicy;
use BackupApp\Restore\Integration\Policy\TrustedRunId;
use BackupApp\Restore\Integration\Readiness\ReadinessEvidence;
use BackupApp\Restore\Integration\Readiness\RestoreIntegrationReadinessChecker;

/** @return list<string> */
function readinessTargetNames(RestoreIntegrationPolicy $policy, TrustedRunId $runId): array
{
    return array_map(
        static fn (string $source): string => $policy->targetFor($source, $runId)->value(),
        $policy->authorizedSources()
    );
}

test('RestoreIntegrationReadiness plan-only mode is fail-closed and proposes exact scopes', function (): void {
    $policy = new RestoreIntegrationPolicy();
    $runId = new TrustedRunId('0123456789abcdef0123456789abcdef');
    $result = (new RestoreIntegrationReadinessChecker($policy))->planOnly($runId)->publicView();

    assertSameValue('plan_only', $result['mode']);
    assertSameValue('blocked', $result['overall_status']);
    assertFalseValue($result['ready_for_live_authorization']);
    assertSameValue('127.0.0.1:3306', $result['expected_server']['endpoint']);
    assertSameValue('mysql', $result['expected_server']['product']);
    assertSameValue('8.4.3', $result['expected_server']['version']);
    assertSameValue($policy->authorizedSources(), $result['authorized_sources']);
    assertSameValue(readinessTargetNames($policy, $runId), $result['proposed_targets']);
    assertSameValue('exact_allowlist_only', $result['inspection_contract']['database_discovery']);
    assertFalseValue($result['inspection_contract']['credentials_accepted_by_plan_cli']);
    assertFalseValue($result['inspection_contract']['database_connection_in_plan_only_mode']);
    assertSameValue('not_verified', $result['checks']['native_server_identity']['status']);
    assertSameValue('not_verified', $result['checks']['dedicated_credentials']['status']);
    assertSameValue('pass', $result['checks']['restore_safety_gate']['status']);
});

test('RestoreIntegrationReadiness accepts complete typed offline evidence only for authorization review', function (): void {
    $policy = new RestoreIntegrationPolicy();
    $runId = new TrustedRunId('0123456789abcdef0123456789abcdef');
    $targets = readinessTargetNames($policy, $runId);
    $identity = new ServerIdentity('mysql', '8.4.3', 'mysql:fixture-native-id', '127.0.0.1:3306');
    $reader = new PrivilegeEvidence('backup_reader', $identity, true, [], $policy->authorizedSources());
    $runner = new PrivilegeEvidence('restore_runner', $identity, true, [], $targets);
    $allSources = array_fill_keys($policy->authorizedSources(), true);
    $allTargetsAbsent = array_fill_keys($targets, true);
    $evidence = new ReadinessEvidence(
        $identity,
        $identity,
        true,
        true,
        true,
        $reader,
        $runner,
        $allSources,
        $allTargetsAbsent,
        true,
        true,
        true,
        true
    );

    $result = (new RestoreIntegrationReadinessChecker($policy))->assess($runId, $evidence)->publicView();
    assertSameValue('ready_for_live_authorization_review', $result['overall_status']);
    assertTrueValue($result['ready_for_live_authorization']);
    foreach ($result['checks'] as $check) {
        assertSameValue('pass', $check['status']);
    }
});

test('RestoreIntegrationReadiness blocks endpoint drift to port 3333', function (): void {
    $policy = new RestoreIntegrationPolicy();
    $runId = new TrustedRunId('0123456789abcdef0123456789abcdef');
    $drifted = new ServerIdentity('mysql', '8.4.3', 'mysql:fixture-native-id', '127.0.0.1:3333');
    $evidence = ReadinessEvidence::planOnly()->withServerIdentities($drifted, $drifted);

    $result = (new RestoreIntegrationReadinessChecker($policy))->assess($runId, $evidence)->publicView();
    assertSameValue('blocked', $result['checks']['expected_endpoint']['status']);
    assertContainsValue('endpoint_drift_forbidden', implode(',', $result['checks']['expected_endpoint']['findings']));
    assertFalseValue($result['ready_for_live_authorization']);
});

test('RestoreIntegrationReadiness blocks source evidence outside exact allowlist', function (): void {
    $policy = new RestoreIntegrationPolicy();
    $runId = new TrustedRunId('0123456789abcdef0123456789abcdef');
    $evidence = ReadinessEvidence::planOnly()->withSourceCapabilities([
        'autoprintdb' => true,
        'drugmap_db' => true,
        'drug_stock_check' => true,
        'prepack_labels' => true,
        'other_database' => true,
    ]);

    $result = (new RestoreIntegrationReadinessChecker($policy))->assess($runId, $evidence)->publicView();
    assertSameValue('blocked', $result['checks']['source_allowlist']['status']);
    assertContainsValue('source_scope_outside_allowlist', implode(',', $result['checks']['source_allowlist']['findings']));
});

test('RestoreIntegrationReadiness binds privilege evidence to the attested server identity', function (): void {
    $policy = new RestoreIntegrationPolicy();
    $runId = new TrustedRunId('0123456789abcdef0123456789abcdef');
    $targets = readinessTargetNames($policy, $runId);
    $identity = new ServerIdentity('mysql', '8.4.3', 'mysql:fixture-native-id', '127.0.0.1:3306');
    $otherIdentity = new ServerIdentity('mysql', '8.4.3', 'mysql:other-native-id', '127.0.0.1:3306');
    $reader = new PrivilegeEvidence('backup_reader', $otherIdentity, true, [], $policy->authorizedSources());
    $runner = new PrivilegeEvidence('restore_runner', $identity, true, [], $targets);
    $evidence = new ReadinessEvidence(
        $identity,
        $identity,
        true,
        true,
        true,
        $reader,
        $runner,
        array_fill_keys($policy->authorizedSources(), true),
        array_fill_keys($targets, true),
        true,
        true,
        true,
        true
    );

    $result = (new RestoreIntegrationReadinessChecker($policy))->assess($runId, $evidence)->publicView();
    assertSameValue('blocked', $result['checks']['exact_privilege_scope']['status']);
    assertContainsValue('privilege_identity_mismatch', implode(',', $result['checks']['exact_privilege_scope']['findings']));
});

test('RestoreIntegrationReadiness blocks missing credentials target collisions locks and open execution', function (): void {
    $policy = new RestoreIntegrationPolicy();
    $runId = new TrustedRunId('0123456789abcdef0123456789abcdef');
    $targets = readinessTargetNames($policy, $runId);
    $identity = new ServerIdentity('mysql', '8.4.3', 'mysql:fixture-native-id', '127.0.0.1:3306');
    $reader = new PrivilegeEvidence('backup_reader', $identity, true, [], $policy->authorizedSources());
    $runner = new PrivilegeEvidence('restore_runner', $identity, true, [], $targets);
    $evidence = new ReadinessEvidence(
        $identity,
        $identity,
        false,
        true,
        true,
        $reader,
        $runner,
        array_fill_keys($policy->authorizedSources(), true),
        array_fill_keys($targets, false),
        false,
        false,
        false,
        false
    );

    $result = (new RestoreIntegrationReadinessChecker($policy))->assess($runId, $evidence)->publicView();
    assertSameValue('blocked', $result['checks']['dedicated_credentials']['status']);
    assertSameValue('blocked', $result['checks']['target_isolation']['status']);
    assertSameValue('blocked', $result['checks']['shared_lock_readiness']['status']);
    assertSameValue('blocked', $result['checks']['restore_safety_gate']['status']);
    assertFalseValue($result['ready_for_live_authorization']);
});

test('RestoreIntegrationReadiness CLI is plan-only and emits no live evidence', function (): void {
    $tool = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'restore-integration-readiness.php';
    $pipes = [];
    $process = proc_open([PHP_BINARY, $tool], [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, dirname(__DIR__));
    assertTrueValue(is_resource($process), 'Unable to launch readiness CLI');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);

    assertSameValue(0, $exit, 'Readiness CLI failed: ' . $stderr);
    $result = json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);
    assertSameValue('plan_only', $result['mode']);
    assertSameValue('blocked', $result['overall_status']);
    assertSameValue('not_verified', $result['checks']['native_server_identity']['status']);
    assertSameValue('not_verified', $result['checks']['dedicated_credentials']['status']);
    assertSameValue(['autoprintdb', 'drugmap_db', 'drug_stock_check', 'prepack_labels'], $result['authorized_sources']);
    assertSameValue(4, count($result['proposed_targets']));
    assertSameValue(1, preg_match('/^[a-f0-9]{32}$/D', $result['run_id']));
});

test('RestoreIntegrationReadiness CLI rejects every live or credential argument', function (): void {
    $tool = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'restore-integration-readiness.php';
    $pipes = [];
    $process = proc_open([PHP_BINARY, $tool, '--connect'], [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, dirname(__DIR__));
    assertTrueValue(is_resource($process));
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);

    assertSameValue(2, $exit);
    assertSameValue('', $stdout);
    assertContainsValue('Usage:', (string) $stderr);
});
