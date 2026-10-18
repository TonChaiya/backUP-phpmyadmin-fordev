<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Secrets;

use JsonException;
use SensitiveParameter;

final class NativeDpapiHelper implements RuntimeIdentityProvider, SecretProtector, WindowsAclHardener
{
    private const EXECUTABLE_PATH = 'C:\\ProgramData\\BackupPhpMyAdmin\\SecretProvider\\backup-secret-helper.exe';
    private const ALLOWED_ERRORS = [
        'windows_identity_mismatch',
        'windows_identity_unavailable',
        'secret_decryption_failed',
        'acl_hardening_failed',
        'worker_input_invalid',
        'worker_failed',
    ];

    public function __construct(
        private readonly WorkerProcessRunner $runner,
        private readonly NativeHelperDeploymentVerifier $deploymentVerifier = new ProductionNativeHelperDeploymentVerifier(),
    ) {
    }

    public function currentIdentity(): WindowsIdentity
    {
        $response = $this->call('identity', []);
        if (!$this->hasExactKeys($response, ['ok', 'account', 'sid']) || $response['ok'] !== true
            || !is_string($response['account']) || !is_string($response['sid'])) {
            throw new SecretProviderException('worker_failed');
        }
        return new WindowsIdentity($response['account'], $response['sid']);
    }

    public function protect(#[SensitiveParameter] string $plaintext, string $entropy, WindowsIdentity $identity): string
    {
        if ($plaintext === '' || strlen($plaintext) > 8192 || strlen($entropy) !== 32) {
            throw new SecretProviderException('worker_input_invalid');
        }
        return $this->binaryResponse($this->call('protect', [
            'expected_sid' => $identity->sid(),
            'data_b64' => base64_encode($plaintext),
            'entropy_b64' => base64_encode($entropy),
        ]), 'ciphertext_b64', 'worker_failed');
    }

    public function unprotect(string $ciphertext, string $entropy, WindowsIdentity $identity): string
    {
        if ($ciphertext === '' || strlen($ciphertext) > 8192 || strlen($entropy) !== 32) {
            throw new SecretProviderException('worker_input_invalid');
        }
        return $this->binaryResponse($this->call('unprotect', [
            'expected_sid' => $identity->sid(),
            'data_b64' => base64_encode($ciphertext),
            'entropy_b64' => base64_encode($entropy),
        ]), 'plaintext_b64', 'secret_decryption_failed');
    }

    public function hardenDirectory(string $path, WindowsIdentity $identity): void
    {
        $this->acl('harden-directory', $path, $identity);
    }

    public function hardenFile(string $path, WindowsIdentity $identity): void
    {
        $this->acl('harden-file', $path, $identity);
    }

    private function acl(string $operation, string $path, WindowsIdentity $identity): void
    {
        $response = $this->call($operation, ['expected_sid' => $identity->sid(), 'path' => $path]);
        if ($response !== ['ok' => true]) {
            throw new SecretProviderException('acl_hardening_failed');
        }
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function call(string $operation, array $payload): array
    {
        if (!in_array($operation, ['identity', 'protect', 'unprotect', 'harden-directory', 'harden-file'], true)) {
            throw new SecretProviderException('worker_input_invalid');
        }
        $this->deploymentVerifier->verify(self::EXECUTABLE_PATH);
        try {
            $stdin = $payload === [] ? '{}' : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new SecretProviderException('worker_input_invalid');
        }
        $result = $this->runner->run([self::EXECUTABLE_PATH, $operation], $stdin, 5000, 65536);
        try {
            $decoded = json_decode(trim($result->stdout), true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new SecretProviderException('worker_failed');
        }
        if (!is_array($decoded) || $result->hadStderr) {
            throw new SecretProviderException('worker_failed');
        }
        if ($result->exitCode !== 0 || ($decoded['ok'] ?? null) !== true) {
            $code = $decoded['code'] ?? null;
            throw new SecretProviderException(is_string($code) && in_array($code, self::ALLOWED_ERRORS, true) ? $code : 'worker_failed');
        }
        return $decoded;
    }

    /** @param array<string, mixed> $response */
    private function binaryResponse(array $response, string $key, string $error): string
    {
        if (!$this->hasExactKeys($response, ['ok', $key]) || $response['ok'] !== true || !is_string($response[$key])) {
            throw new SecretProviderException($error);
        }
        $value = base64_decode($response[$key], true);
        if (!is_string($value) || $value === '') {
            throw new SecretProviderException($error);
        }
        return $value;
    }

    /** @param array<string, mixed> $response @param list<string> $expected */
    private function hasExactKeys(array $response, array $expected): bool
    {
        $actual = array_keys($response);
        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);
        return $actual === $expected;
    }
}
