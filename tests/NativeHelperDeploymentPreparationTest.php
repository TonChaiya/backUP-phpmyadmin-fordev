<?php

declare(strict_types=1);

use BackupApp\Restore\Integration\Secrets\ProcOpenWorkerProcessRunner;

function deploymentPwsh(): string
{
    $result = (new ProcOpenWorkerProcessRunner())->run(['where.exe', 'pwsh.exe'], '{}', 2000, 4096);
    if ($result->exitCode !== 0 || trim($result->stdout) === '') {
        skipTest('PowerShell 7 is unavailable for deployment preparation tests');
    }
    return trim((string) strtok(str_replace("\r", '', $result->stdout), "\n"));
}

/** @return array<string, mixed> */
function runDeploymentTool(string $script, array $arguments, string $stdin = '{}'): array
{
    $path = dirname(__DIR__) . '/tools/native-helper/' . $script;
    assertTrueValue(is_file($path), "Missing deployment tool {$script}");
    $result = (new ProcOpenWorkerProcessRunner())->run(array_merge([
        deploymentPwsh(), '-NoLogo', '-NoProfile', '-NonInteractive', '-File', $path,
    ], $arguments), $stdin, 5000, 65536);
    $decoded = json_decode(trim($result->stdout), true);
    assertTrueValue(is_array($decoded), "{$script} did not return JSON");
    $decoded['_exit'] = $result->exitCode;
    $decoded['_stderr'] = $result->hadStderr;
    return $decoded;
}

function validDeploymentEvidence(): array
{
    return [
        'schema' => 1,
        'path' => 'C:\\staging\\backup-secret-helper.exe',
        'signature_status' => 'Valid',
        'publisher' => 'CN=Backup Internal Code Signing, O=Example Org',
        'thumbprint' => str_repeat('A', 40),
        'eku_oids' => ['1.3.6.1.5.5.7.3.3'],
        'timestamp_present' => true,
        'timestamp_valid' => true,
        'hash_algorithm' => 'SHA256',
        'sha256' => str_repeat('B', 64),
        'reparse_point' => false,
    ];
}

test('deployment evidence accepts only exact signed certificate and artifact pins', function (): void {
    $expected = [
        '-ExpectedPublisher', 'CN=Backup Internal Code Signing, O=Example Org',
        '-ExpectedThumbprint', str_repeat('A', 40),
        '-ExpectedSha256', str_repeat('B', 64),
    ];
    $valid = runDeploymentTool('validate-deployment-evidence.ps1', $expected, json_encode(validDeploymentEvidence(), JSON_THROW_ON_ERROR));
    assertSameValue(0, $valid['_exit']);
    assertSameValue(true, $valid['ready'] ?? null);

    foreach ([
        ['signature_status', 'NotSigned', 'signature_invalid'],
        ['publisher', 'CN=Wrong', 'publisher_mismatch'],
        ['thumbprint', str_repeat('C', 40), 'thumbprint_mismatch'],
        ['eku_oids', [], 'code_signing_eku_missing'],
        ['timestamp_present', false, 'timestamp_missing'],
        ['timestamp_valid', false, 'timestamp_invalid'],
        ['sha256', str_repeat('D', 64), 'artifact_hash_mismatch'],
        ['reparse_point', true, 'artifact_reparse_point'],
    ] as [$field, $value, $code]) {
        $evidence = validDeploymentEvidence();
        $evidence[$field] = $value;
        $result = runDeploymentTool('validate-deployment-evidence.ps1', $expected, json_encode($evidence, JSON_THROW_ON_ERROR));
        assertSameValue(9, $result['_exit']);
        assertSameValue(false, $result['ready'] ?? null);
        assertSameValue($code, $result['code'] ?? null);
        assertFalseValue($result['_stderr']);
    }
});

test('real signature verifier rejects an unsigned synthetic artifact', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'native-signature-');
    if (!is_string($path)) { throw new RuntimeException('Cannot create fixture'); }
    try {
        file_put_contents($path, 'synthetic unsigned artifact');
        $result = runDeploymentTool('verify-signed-artifact.ps1', [
            '-ArtifactPath', $path,
            '-ExpectedPublisher', 'CN=Backup Internal Code Signing, O=Example Org',
            '-ExpectedThumbprint', str_repeat('A', 40),
            '-ExpectedSha256', hash_file('sha256', $path),
        ]);
        assertSameValue(9, $result['_exit']);
        assertSameValue(false, $result['ready'] ?? null);
        assertSameValue('signature_invalid', $result['code'] ?? null);
        assertFalseValue($result['_stderr']);
    } finally {
        @unlink($path);
    }
});

test('deployment tooling is dry-run and contains no trust or production mutations', function (): void {
    $root = dirname(__DIR__);
    $expected = [
        'build-release.ps1',
        'show-signing-plan.ps1',
        'verify-signed-artifact.ps1',
        'deployment-preflight.ps1',
        'new-deployment-plan.ps1',
        'verify-deployed-helper.ps1',
        'verify-runtime-identity.ps1',
    ];
    foreach ($expected as $file) {
        assertTrueValue(is_file($root . '/tools/native-helper/' . $file), "Missing {$file}");
    }
    $deploymentSources = '';
    foreach (array_slice($expected, 1) as $file) {
        $deploymentSources .= (string) file_get_contents($root . '/tools/native-helper/' . $file);
    }
    foreach (['Invoke-Expression', '-ExecutionPolicy Bypass', 'New-SelfSignedCertificate', 'Import-Certificate',
        'Import-PfxCertificate', 'Copy-Item', 'Move-Item', 'Set-Acl', 'icacls', 'signtool sign', 'New-Item'] as $forbidden) {
        assertNotContainsValue(strtolower($forbidden), strtolower($deploymentSources));
    }
    assertContainsValue('ContinuousIntegrationBuild=true', (string) file_get_contents($root . '/tools/native-helper/build-release.ps1'));
    assertContainsValue('--locked-mode', (string) file_get_contents($root . '/tools/native-helper/build-release.ps1'));
    $identitySource = (string) file_get_contents($root . '/tools/native-helper/verify-runtime-identity.ps1');
    assertContainsValue('Test-NativeHelperSignatureEvidence', $identitySource);
    assertContainsValue('Test-NativeHelperAclReadiness', $identitySource);
});

test('signing plan is explicit and cannot perform signing', function (): void {
    $result = runDeploymentTool('show-signing-plan.ps1', []);
    assertSameValue(0, $result['_exit']);
    assertSameValue('plan_only', $result['mode'] ?? null);
    assertSameValue(false, $result['action_performed'] ?? null);
    assertSameValue('1.3.6.1.5.5.7.3.3', $result['required_eku_oid'] ?? null);
    assertSameValue(false, $result['self_signed_production_allowed'] ?? null);
});

test('deployment plan stays fixed to ProgramData and reports dry run only', function (): void {
    $evidence = validDeploymentEvidence();
    $args = [
        '-ExpectedPublisher', $evidence['publisher'],
        '-ExpectedThumbprint', $evidence['thumbprint'],
        '-ExpectedSha256', $evidence['sha256'],
        '-RuntimeSid', 'S-1-5-21-1-2-3-1001',
    ];
    $result = runDeploymentTool('new-deployment-plan.ps1', $args, json_encode($evidence, JSON_THROW_ON_ERROR));
    assertSameValue(0, $result['_exit']);
    assertSameValue('dry_run_only', $result['mode'] ?? null);
    assertSameValue('C:\\ProgramData\\BackupPhpMyAdmin\\SecretProvider\\backup-secret-helper.exe', $result['executable_path'] ?? null);
    assertSameValue('C:\\ProgramData\\BackupPhpMyAdmin\\SecretProvider\\secrets', $result['secret_directory'] ?? null);
    assertSameValue(false, $result['production_mutation_performed'] ?? null);
});

test('deployment preparation rejects malformed evidence and invalid runtime SID', function (): void {
    $expected = [
        '-ExpectedPublisher', 'CN=Backup Internal Code Signing, O=Example Org',
        '-ExpectedThumbprint', str_repeat('A', 40),
        '-ExpectedSha256', str_repeat('B', 64),
    ];
    $malformed = runDeploymentTool('validate-deployment-evidence.ps1', $expected, '{');
    assertSameValue(9, $malformed['_exit']);
    assertSameValue('evidence_input_invalid', $malformed['code'] ?? null);

    $plan = runDeploymentTool('new-deployment-plan.ps1', array_merge($expected, ['-RuntimeSid', 'not-a-sid']), json_encode(validDeploymentEvidence(), JSON_THROW_ON_ERROR));
    assertSameValue(9, $plan['_exit']);
    assertSameValue('runtime_sid_invalid', $plan['code'] ?? null);
});

test('post deployment validators fail closed while production deployment is absent', function (): void {
    assertFalseValue(is_file('C:\\ProgramData\\BackupPhpMyAdmin\\SecretProvider\\backup-secret-helper.exe'));
    $signature = [
        '-ExpectedPublisher', 'CN=Backup Internal Code Signing, O=Example Org',
        '-ExpectedThumbprint', str_repeat('A', 40),
        '-ExpectedSha256', str_repeat('B', 64),
        '-RuntimeSid', 'S-1-5-21-1-2-3-1001',
    ];
    $deployed = runDeploymentTool('verify-deployed-helper.ps1', $signature);
    assertSameValue(9, $deployed['_exit']);
    assertSameValue('production_executable_missing', $deployed['code'] ?? null);
    $identity = runDeploymentTool('verify-runtime-identity.ps1', [
        '-ExpectedRuntimeSid', 'S-1-5-21-1-2-3-1001',
        '-ExpectedPublisher', 'CN=Backup Internal Code Signing, O=Example Org',
        '-ExpectedThumbprint', str_repeat('A', 40),
        '-ExpectedSha256', str_repeat('B', 64),
    ]);
    assertSameValue(9, $identity['_exit']);
    assertSameValue('production_executable_missing', $identity['code'] ?? null);
});
