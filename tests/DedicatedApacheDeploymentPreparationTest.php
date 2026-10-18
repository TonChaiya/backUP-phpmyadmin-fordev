<?php

declare(strict_types=1);

use BackupApp\Deployment\DedicatedApacheConfigValidator;
use BackupApp\Restore\Integration\Secrets\ProcOpenWorkerProcessRunner;

function dedicatedApachePowerShell(): string
{
    $result = (new ProcOpenWorkerProcessRunner())->run(['where.exe', 'pwsh.exe'], '{}', 2000, 4096);
    if ($result->exitCode !== 0 || trim($result->stdout) === '') {
        skipTest('PowerShell 7 is unavailable for dedicated deployment tests');
    }

    return trim((string) strtok(str_replace("\r", '', $result->stdout), "\n"));
}

/** @return array<string, mixed> */
function dedicatedDeploymentContract(): array
{
    $path = dirname(__DIR__) . '/deployment/dedicated-apache/deployment-contract.json';
    $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    assertTrueValue(is_array($decoded), 'Dedicated deployment contract is missing or invalid');
    return $decoded;
}

/** @param array<string, mixed> $evidence @return array<string, mixed> */
function validateDedicatedEvidence(array $evidence): array
{
    $script = dirname(__DIR__) . '/tools/dedicated-apache/validate-preflight-evidence.ps1';
    assertTrueValue(is_file($script), 'Dedicated evidence validator is missing');
    $result = (new ProcOpenWorkerProcessRunner())->run([
        dedicatedApachePowerShell(), '-NoLogo', '-NoProfile', '-NonInteractive', '-File', $script,
    ], json_encode($evidence, JSON_THROW_ON_ERROR), 5000, 65536);
    $decoded = json_decode(trim($result->stdout), true);
    assertTrueValue(is_array($decoded), 'Dedicated evidence validator did not return JSON');
    $decoded['_exit'] = $result->exitCode;
    $decoded['_stderr'] = $result->hadStderr;
    return $decoded;
}

/** @return array<string, mixed> */
function validDedicatedEvidence(): array
{
    return [
        'schema' => 1,
        'listener' => '127.0.0.1:8788',
        'service_name' => 'BackupPhpMyAdminApache',
        'runtime_account' => '.\\BackupPhpRuntime',
        'runtime_sid' => 'S-1-5-21-1-2-3-1001',
        'server_identity' => 'DESKTOP-TEST',
        'paths' => [
            'application_root' => 'C:\\Program Files\\BackupPhpMyAdmin\\Application',
            'apache_root' => 'C:\\Program Files\\BackupPhpMyAdmin\\Runtime\\Apache',
            'php_root' => 'C:\\Program Files\\BackupPhpMyAdmin\\Runtime\\PHP',
            'runtime_data_root' => 'C:\\ProgramData\\BackupPhpMyAdmin\\RuntimeData',
        ],
        'path_collisions' => false,
        'path_chain_ntfs' => true,
        'path_chain_reparse_free' => true,
        'port_available' => true,
        'source_manifest_trusted' => true,
        'source_integrity_verified' => true,
        'runtime_data_tree_ready' => true,
        'runtime_path_policy_verified' => true,
        'account_exists' => true,
        'account_sid_matches' => true,
        'account_non_admin' => true,
        'service_logon_granted' => true,
        'apache_config_valid' => true,
        'php_config_valid' => true,
        'shared_laragon_unchanged' => true,
    ];
}

test('dedicated Apache and PHP templates satisfy the fixed isolation contract', function (): void {
    $root = dirname(__DIR__);
    $contract = dedicatedDeploymentContract();
    $apache = (string) file_get_contents($root . '/deployment/dedicated-apache/conf/httpd.conf');
    $php = (string) file_get_contents($root . '/deployment/dedicated-apache/php/php.ini');

    $errors = (new DedicatedApacheConfigValidator())->validate($apache, $php, $contract);
    assertSameValue([], $errors);

    $mutations = [
        str_replace('Listen 127.0.0.1:8788', 'Listen 8788', $apache),
        $apache . "\nIncludeOptional C:/laragon/etc/apache2/sites-enabled/*.conf\n",
        str_replace('Require local', 'Require all granted', $apache),
        str_replace('Options None', 'Options Indexes FollowSymLinks', $apache),
    ];
    foreach ($mutations as $mutated) {
        assertTrueValue((new DedicatedApacheConfigValidator())->validate($mutated, $php, $contract) !== []);
    }

    $sharedPhp = str_replace(
        'C:\\ProgramData\\BackupPhpMyAdmin\\RuntimeData\\sessions',
        'C:\\laragon\\tmp',
        $php
    );
    assertTrueValue((new DedicatedApacheConfigValidator())->validate($apache, $sharedPhp, $contract) !== []);
});

test('dedicated deployment evidence fails closed for every readiness blocker', function (): void {
    $ready = validateDedicatedEvidence(validDedicatedEvidence());
    assertSameValue(0, $ready['_exit']);
    assertSameValue(true, $ready['ready'] ?? null);
    assertFalseValue($ready['_stderr']);

    foreach ([
        ['listener', '0.0.0.0:8788', 'listener_mismatch'],
        ['path_collisions', true, 'path_collision'],
        ['path_chain_ntfs', false, 'path_filesystem_invalid'],
        ['path_chain_reparse_free', false, 'path_reparse_point'],
        ['port_available', false, 'port_unavailable'],
        ['source_integrity_verified', false, 'source_integrity_unverified'],
        ['runtime_data_tree_ready', false, 'runtime_data_tree_missing'],
        ['account_exists', false, 'runtime_account_missing'],
        ['account_sid_matches', false, 'runtime_sid_mismatch'],
        ['account_non_admin', false, 'runtime_account_privileged'],
        ['service_logon_granted', false, 'service_logon_missing'],
        ['shared_laragon_unchanged', false, 'shared_laragon_change_detected'],
    ] as [$field, $value, $code]) {
        $evidence = validDedicatedEvidence();
        $evidence[$field] = $value;
        $result = validateDedicatedEvidence($evidence);
        assertSameValue(9, $result['_exit']);
        assertSameValue(false, $result['ready'] ?? null);
        assertSameValue($code, $result['code'] ?? null);
        assertFalseValue($result['_stderr']);
    }
});

test('dedicated deployment tools are read only and expose no database or mutation command', function (): void {
    $root = dirname(__DIR__);
    $files = [
        $root . '/tools/dedicated-apache/validate-preflight-evidence.ps1',
        $root . '/tools/dedicated-apache/deployment-preflight.ps1',
    ];
    $source = '';
    foreach ($files as $file) {
        assertTrueValue(is_file($file), 'Missing dedicated deployment tool');
        $source .= (string) file_get_contents($file);
    }

    foreach ([
        'New-LocalUser', 'New-Service', 'sc.exe create', 'Set-Acl', 'icacls', 'New-Item',
        'Copy-Item', 'Move-Item', 'Remove-Item', 'Restart-Service', 'Start-Service',
        'mysqli', 'mysql.exe', 'CREATE DATABASE', 'DROP DATABASE', 'jhcisdb',
    ] as $forbidden) {
        assertNotContainsValue(strtolower($forbidden), strtolower($source));
    }
});

