<?php

declare(strict_types=1);

use BackupApp\Restore\Integration\Secrets\ProcOpenWorkerProcessRunner;
use BackupApp\Restore\Integration\Secrets\WorkerProcessResult;

function protocolPowerShellPath(): string
{
    $lookup = (new ProcOpenWorkerProcessRunner())->run(['where.exe', 'pwsh.exe'], '{}', 2000, 4096);
    if ($lookup->exitCode !== 0 || trim($lookup->stdout) === '') {
        skipTest('PowerShell 7 host is unavailable for real worker protocol verification');
    }
    $path = trim((string) strtok(str_replace("\r", '', $lookup->stdout), "\n"));
    if (!is_file($path)) { skipTest('Resolved PowerShell 7 host does not exist'); }
    return $path;
}

function runSecretWorkerProtocol(string $operation, string $stdin): WorkerProcessResult
{
    return (new ProcOpenWorkerProcessRunner())->run([
        protocolPowerShellPath(),
        '-NoLogo',
        '-NoProfile',
        '-NonInteractive',
        '-File',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'windows-dpapi-worker.ps1',
        $operation,
    ], $stdin, 5000, 65536);
}

/** @return array<string, mixed> */
function decodeWorkerProtocol(WorkerProcessResult $result): array
{
    $decoded = json_decode(trim($result->stdout), true);
    if (!is_array($decoded)) { throw new RuntimeException('Worker did not return a JSON object'); }
    return $decoded;
}

function assertWorkerInputRejected(string $operation, string $stdin): void
{
    $result = runSecretWorkerProtocol($operation, $stdin);
    assertSameValue(9, $result->exitCode);
    assertFalseValue($result->hadStderr);
    $response = decodeWorkerProtocol($result);
    assertSameValue(false, $response['ok'] ?? null);
    assertSameValue('worker_input_invalid', $response['code'] ?? null);
    assertSameValue(2, count($response));
}

test('real worker identity accepts a valid empty JSON object', function (): void {
    $result = runSecretWorkerProtocol('identity', '{}');
    assertSameValue(0, $result->exitCode);
    assertFalseValue($result->hadStderr);
    $response = decodeWorkerProtocol($result);
    assertSameValue(true, $response['ok'] ?? null);
    assertTrueValue(is_string($response['account'] ?? null) && $response['account'] !== '');
    assertTrueValue(is_string($response['sid'] ?? null) && preg_match('/^S-[0-9]+(?:-[0-9]+){2,15}$/D', $response['sid']) === 1);
});

test('real worker rejects missing required fields', function (): void {
    assertWorkerInputRejected('protect', '{}');
});

test('real worker rejects unexpected fields', function (): void {
    assertWorkerInputRejected('identity', '{"unexpected":true}');
});

test('real worker rejects malformed JSON', function (): void {
    assertWorkerInputRejected('identity', '{');
});

test('real worker rejects an invalid operation', function (): void {
    assertWorkerInputRejected('not-an-operation', '{}');
});
