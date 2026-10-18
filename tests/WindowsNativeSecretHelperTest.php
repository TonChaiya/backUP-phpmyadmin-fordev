<?php

declare(strict_types=1);

use BackupApp\Restore\Integration\Secrets\NativeDpapiHelper;
use BackupApp\Restore\Integration\Secrets\NativeHelperDeploymentVerifier;
use BackupApp\Restore\Integration\Secrets\SecretProviderException;
use BackupApp\Restore\Integration\Secrets\WorkerProcessResult;
use BackupApp\Restore\Integration\Secrets\WorkerProcessRunner;
use BackupApp\Restore\Integration\Secrets\WindowsIdentity;

final class FakeNativeHelperVerifier implements NativeHelperDeploymentVerifier
{
    /** @var list<string> */
    public array $paths = [];
    public ?string $failure = null;

    public function verify(string $path): void
    {
        $this->paths[] = $path;
        if ($this->failure !== null) {
            throw new SecretProviderException($this->failure);
        }
    }
}

final class FakeNativeHelperRunner implements WorkerProcessRunner
{
    /** @var list<array{command: list<string>, stdin: string, timeoutMs: int, maxOutputBytes: int}> */
    public array $calls = [];
    public WorkerProcessResult $result;

    public function __construct()
    {
        $this->result = new WorkerProcessResult(0, '{"ok":true,"ciphertext_b64":"Y2lwaGVy"}', false);
    }

    public function run(array $command, string $stdin, int $timeoutMs, int $maxOutputBytes): WorkerProcessResult
    {
        $this->calls[] = compact('command', 'stdin', 'timeoutMs', 'maxOutputBytes');
        return $this->result;
    }
}

test('native adapter uses only fixed signed deployment path and bounded stdin protocol', function (): void {
    $runner = new FakeNativeHelperRunner();
    $verifier = new FakeNativeHelperVerifier();
    $helper = new NativeDpapiHelper($runner, $verifier);
    $identity = new WindowsIdentity('DESKTOP-TEST\\svc', 'S-1-5-21-100-200-300-1001');

    assertSameValue('cipher', $helper->protect('native-secret-sentinel', str_repeat('e', 32), $identity));
    $call = $runner->calls[0];
    assertSameValue(['C:\\ProgramData\\BackupPhpMyAdmin\\SecretProvider\\backup-secret-helper.exe', 'protect'], $call['command']);
    assertSameValue([$call['command'][0]], $verifier->paths);
    assertNotContainsValue('native-secret-sentinel', implode(' ', $call['command']));
    assertContainsValue(base64_encode('native-secret-sentinel'), $call['stdin']);
    assertSameValue(5000, $call['timeoutMs']);
    assertSameValue(65536, $call['maxOutputBytes']);
});

test('native adapter blocks before process invocation when deployment is unverified', function (): void {
    $runner = new FakeNativeHelperRunner();
    $verifier = new FakeNativeHelperVerifier();
    $verifier->failure = 'native_helper_deployment_unverified';
    $helper = new NativeDpapiHelper($runner, $verifier);

    try {
        $helper->currentIdentity();
        throw new RuntimeException('Expected deployment block');
    } catch (SecretProviderException $exception) {
        assertSameValue('native_helper_deployment_unverified', $exception->getMessage());
    }
    assertSameValue([], $runner->calls);
});

test('native adapter rejects malformed or leaking worker output with stable code', function (): void {
    $runner = new FakeNativeHelperRunner();
    $verifier = new FakeNativeHelperVerifier();
    $helper = new NativeDpapiHelper($runner, $verifier);
    $runner->result = new WorkerProcessResult(9, 'native-secret-sentinel', true);

    try {
        $helper->currentIdentity();
        throw new RuntimeException('Expected worker failure');
    } catch (SecretProviderException $exception) {
        assertSameValue('worker_failed', $exception->getMessage());
        assertNotContainsValue('native-secret-sentinel', $exception->getMessage());
    }
});

test('production native deployment verifier remains blocked without reviewed signing pins', function (): void {
    $source = file_get_contents(dirname(__DIR__) . '/app/Restore/Integration/Secrets/ProductionNativeHelperDeploymentVerifier.php');
    assertTrueValue(is_string($source));
    assertContainsValue("EXPECTED_SHA256 = ''", $source);
    assertContainsValue("EXPECTED_PUBLISHER = ''", $source);
    assertContainsValue("EXPECTED_THUMBPRINT = ''", $source);
    assertNotContainsValue('PowerShellDpapiWorker', $source);
});
