<?php

declare(strict_types=1);

use BackupApp\Restore\Integration\Policy\RestoreIntegrationPolicy;
use BackupApp\Restore\Integration\Policy\TrustedRunId;
use BackupApp\Restore\Integration\Provisioning\CredentialProvisioningPlan;

test('Credential provisioning plan fixes exact local identities and database scopes', function (): void {
    $policy = new RestoreIntegrationPolicy();
    $runId = new TrustedRunId('b0108a9219ca1f50f8b5490f73d28f4c');
    $view = (new CredentialProvisioningPlan($policy, $runId))->publicView();

    assertSameValue('provisioning_preparation_only', $view['mode']);
    assertSameValue('not_authorized', $view['authorization_status']);
    assertSameValue('hard_disabled', $view['live_operations']);
    assertSameValue('127.0.0.1:3306', $view['server']['endpoint']);
    assertSameValue(['127.0.0.1:3333'], $view['server']['forbidden_endpoints']);

    $reader = $view['accounts']['backup_reader'];
    assertSameValue('backup_reader', $reader['username']);
    assertSameValue('localhost', $reader['host']);
    assertSameValue('backup_reader@localhost', $reader['expected_current_user']);
    assertSameValue($policy->authorizedSources(), $reader['exact_database_scopes']);
    assertSameValue(
        ['autoprintdb', 'drugmap\\_db', 'drug\\_stock\\_check', 'prepack\\_labels'],
        $reader['database_grant_scopes']
    );

    $runner = $view['accounts']['restore_runner'];
    assertSameValue('restore_runner@localhost', $runner['expected_current_user']);
    assertSameValue([
        'restore_test_autoprintdb_b0108a9219ca1f50f8b5490f73d28f4c',
        'restore_test_drugmap_db_b0108a9219ca1f50f8b5490f73d28f4c',
        'restore_test_drug_stock_check_b0108a9219ca1f50f8b5490f73d28f4c',
        'restore_test_prepack_labels_b0108a9219ca1f50f8b5490f73d28f4c',
    ], $runner['exact_database_scopes']);
    foreach ($runner['database_grant_scopes'] as $scope) {
        assertSameValue(0, preg_match('/(?<!\\\\)[_%]/', $scope), 'Grant scope contains an unescaped wildcard');
    }
});

test('Credential provisioning plan exposes only reviewed least privilege profiles', function (): void {
    $view = (new CredentialProvisioningPlan(
        new RestoreIntegrationPolicy(),
        new TrustedRunId('b0108a9219ca1f50f8b5490f73d28f4c')
    ))->publicView();

    assertSameValue(['SELECT', 'SHOW VIEW', 'TRIGGER', 'LOCK TABLES'], $view['accounts']['backup_reader']['privileges']);
    assertSameValue([
        'TRIGGER' => 'dual_use_metadata_privilege_requires_live_risk_acceptance',
    ], $view['accounts']['backup_reader']['privilege_risks']);
    assertSameValue(['events', 'routines'], $view['accounts']['backup_reader']['unsupported_object_families']);
    assertSameValue(
        ['CREATE', 'DROP', 'ALTER', 'INDEX', 'INSERT', 'SELECT', 'REFERENCES', 'LOCK TABLES', 'CREATE VIEW'],
        $view['accounts']['restore_runner']['privileges']
    );
    assertSameValue([], array_intersect(
        ['INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP', 'ALTER', 'INDEX', 'EVENT', 'EXECUTE'],
        $view['accounts']['backup_reader']['privileges']
    ));
    assertSameValue([], array_intersect(
        $view['accounts']['restore_runner']['exact_database_scopes'],
        $view['accounts']['backup_reader']['exact_database_scopes']
    ));
    assertSameValue([
        'global_scope',
        'grant_option',
        'roles',
        'wildcard_or_prefix_scope',
        'source_scope_for_restore_runner',
        'runtime_root_account',
    ], $view['forbidden_capabilities']);
});

test('Credential provisioning plan is redacted non executable and fail closed', function (): void {
    $view = (new CredentialProvisioningPlan(
        new RestoreIntegrationPolicy(),
        new TrustedRunId('b0108a9219ca1f50f8b5490f73d28f4c')
    ))->publicView();
    $json = json_encode($view, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    assertFalseValue($view['database_connection']);
    assertFalseValue($view['sql_commands_emitted']);
    assertSameValue('not_verified', $view['verification']['native_server_identity']);
    assertSameValue('not_verified', $view['verification']['actual_grants']);
    assertSameValue('not_verified', $view['verification']['target_absence']);
    assertNotContainsValue('CREATE USER', $json);
    assertNotContainsValue('GRANT ', $json);
    assertNotContainsValue('REVOKE ', $json);
    assertNotContainsValue('DROP DATABASE', $json);
    assertNotContainsValue('password', strtolower($json));
});

test('Credential provisioning CLI requires an exact run id and rejects live arguments', function (): void {
    $tool = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR
        . 'restore-integration-credential-plan.php';

    foreach ([[], ['--connect'], ['--password=value'], ['--run-id=bad']] as $arguments) {
        $pipes = [];
        $process = proc_open(array_merge([PHP_BINARY, $tool], $arguments), [
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
    }

    $pipes = [];
    $process = proc_open([
        PHP_BINARY,
        $tool,
        '--run-id=b0108a9219ca1f50f8b5490f73d28f4c',
        '--pretty',
    ], [
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

    assertSameValue(0, $exit, 'Credential plan CLI failed: ' . $stderr);
    $result = json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);
    assertSameValue('b0108a9219ca1f50f8b5490f73d28f4c', $result['run_id']);
    assertSameValue('not_authorized', $result['authorization_status']);
    assertSameValue('hard_disabled', $result['live_operations']);
});
