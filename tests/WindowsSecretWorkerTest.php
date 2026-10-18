<?php
declare(strict_types=1);

use BackupApp\Restore\Integration\Secrets\ProcOpenWorkerProcessRunner;

test('historical PowerShell worker source remains static and is not a production adapter', function (): void {
    $path = dirname(__DIR__) . '/tools/windows-dpapi-worker.ps1';
    $source = file_get_contents($path);
    assertTrueValue(is_string($source));
    assertNotContainsValue('Invoke-Expression', $source);
    assertNotContainsValue('iex ', strtolower($source));
    assertContainsValue('CurrentUser', $source);
    assertContainsValue('restore-integration-secrets', $source);
    assertContainsValue('ReparsePoint', $source);
    assertFalseValue(is_file(dirname(__DIR__) . '/app/Restore/Integration/Secrets/PowerShellDpapiWorker.php'));
    $identityCli = file_get_contents(dirname(__DIR__) . '/tools/windows-secret-identity.php');
    assertTrueValue(is_string($identityCli));
    assertContainsValue('NativeDpapiHelper', $identityCli);
    assertNotContainsValue('PowerShellDpapiWorker', $identityCli);
});

test('process runner enforces timeout and output bounds', function (): void {
    $runner = new ProcOpenWorkerProcessRunner();
    assertSecretCode('worker_timeout', fn () => $runner->run([PHP_BINARY, '-r', 'usleep(300000);'], '{}', 50, 1024));
    assertSecretCode('worker_output_invalid', fn () => $runner->run([PHP_BINARY, '-r', 'echo str_repeat("x", 2048);'], '{}', 1000, 1024));
});
