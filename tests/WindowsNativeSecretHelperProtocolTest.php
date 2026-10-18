<?php

declare(strict_types=1);

use BackupApp\Restore\Integration\Secrets\ProcOpenWorkerProcessRunner;
use BackupApp\Restore\Integration\Secrets\WorkerProcessResult;

function nativeOfflineHelperPath(): string
{
    $path = dirname(__DIR__) . '/native/artifacts/offline/win-x64/backup-secret-helper.exe';
    if (!is_file($path)) {
        skipTest('Offline native helper artifact has not been published');
    }
    return $path;
}

function runNativeHelperProtocol(string $operation, string $stdin): WorkerProcessResult
{
    return (new ProcOpenWorkerProcessRunner())->run(
        [nativeOfflineHelperPath(), $operation],
        $stdin,
        5000,
        65536,
    );
}

/** @return array<string, mixed> */
function decodeNativeHelperResponse(WorkerProcessResult $result): array
{
    $decoded = json_decode(trim($result->stdout), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Native helper did not return a JSON object');
    }
    return $decoded;
}

test('native helper identity accepts exact empty object', function (): void {
    $result = runNativeHelperProtocol('identity', '{}');
    assertSameValue(0, $result->exitCode);
    assertFalseValue($result->hadStderr);
    $response = decodeNativeHelperResponse($result);
    assertSameValue(true, $response['ok'] ?? null);
    assertTrueValue(is_string($response['account'] ?? null) && $response['account'] !== '');
    assertTrueValue(is_string($response['sid'] ?? null) && preg_match('/^S-[0-9]+(?:-[0-9]+){2,15}$/D', $response['sid']) === 1);
});

test('native helper performs synthetic DPAPI CurrentUser round trip', function (): void {
    $identity = decodeNativeHelperResponse(runNativeHelperProtocol('identity', '{}'));
    $sid = $identity['sid'];
    $sentinel = 'synthetic-native-dpapi-' . bin2hex(random_bytes(16));
    $entropy = random_bytes(32);
    $protect = decodeNativeHelperResponse(runNativeHelperProtocol('protect', json_encode([
        'expected_sid' => $sid,
        'data_b64' => base64_encode($sentinel),
        'entropy_b64' => base64_encode($entropy),
    ], JSON_THROW_ON_ERROR)));
    assertSameValue(true, $protect['ok'] ?? null);
    assertTrueValue(is_string($protect['ciphertext_b64'] ?? null));
    assertNotContainsValue($sentinel, (string) $protect['ciphertext_b64']);

    $unprotect = decodeNativeHelperResponse(runNativeHelperProtocol('unprotect', json_encode([
        'expected_sid' => $sid,
        'data_b64' => $protect['ciphertext_b64'],
        'entropy_b64' => base64_encode($entropy),
    ], JSON_THROW_ON_ERROR)));
    assertSameValue($sentinel, base64_decode((string) ($unprotect['plaintext_b64'] ?? ''), true));
});

test('native helper rejects wrong SID before secret access', function (): void {
    $result = runNativeHelperProtocol('protect', json_encode([
        'expected_sid' => 'S-1-5-21-1-2-3-9999',
        'data_b64' => base64_encode('synthetic-only'),
        'entropy_b64' => base64_encode(str_repeat('e', 32)),
    ], JSON_THROW_ON_ERROR));
    assertSameValue(9, $result->exitCode);
    assertFalseValue($result->hadStderr);
    assertSameValue(['ok' => false, 'code' => 'windows_identity_mismatch'], decodeNativeHelperResponse($result));
});

test('native helper rejects malformed missing unexpected and invalid operations', function (): void {
    foreach ([
        ['protect', '{}'],
        ['identity', '{"unexpected":true}'],
        ['identity', '{'],
        ['invalid-operation', '{}'],
    ] as [$operation, $input]) {
        $result = runNativeHelperProtocol($operation, $input);
        assertSameValue(9, $result->exitCode);
        assertFalseValue($result->hadStderr);
        assertSameValue(['ok' => false, 'code' => 'worker_input_invalid'], decodeNativeHelperResponse($result));
    }
});
