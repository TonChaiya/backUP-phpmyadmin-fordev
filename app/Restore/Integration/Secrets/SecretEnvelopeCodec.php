<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Secrets;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use SensitiveParameter;
use Throwable;

final class SecretEnvelopeCodec
{
    public const MAX_ENVELOPE_BYTES = 65536;

    /** @return array<string, mixed> */
    public function metadata(
        SecretRole $role,
        SecretServerBinding $binding,
        WindowsIdentity $identity,
        DateTimeImmutable $createdAt
    ): array {
        return [
            'format' => 'backup-app.windows-secret',
            'version' => 1,
            'role' => $role->value,
            'server' => $binding->metadata(),
            'windows' => $identity->metadata(),
            'created_at' => $createdAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /** @param array<string, mixed> $metadata */
    public function entropy(array $metadata): string
    {
        return hash('sha256', "backup-app:windows-secret:v1\0" . $this->canonicalJson($metadata), true);
    }

    /** @param array<string, mixed> $metadata */
    public function encodeEnvelope(array $metadata, string $ciphertext): string
    {
        if ($ciphertext === '' || strlen($ciphertext) > self::MAX_ENVELOPE_BYTES) {
            throw new SecretProviderException('secret_envelope_invalid');
        }
        return $this->encodeBounded(['metadata' => $metadata, 'ciphertext_b64' => base64_encode($ciphertext)]);
    }

    /** @return array{metadata: array<string, mixed>, ciphertext: string} */
    public function decodeEnvelope(string $json): array
    {
        if ($json === '' || strlen($json) > self::MAX_ENVELOPE_BYTES) {
            throw new SecretProviderException('secret_envelope_invalid');
        }
        try { $decoded = json_decode($json, true, 16, JSON_THROW_ON_ERROR); }
        catch (JsonException) { throw new SecretProviderException('secret_envelope_invalid'); }
        if (!is_array($decoded) || array_keys($decoded) !== ['metadata', 'ciphertext_b64']
            || !is_array($decoded['metadata']) || !is_string($decoded['ciphertext_b64'])) {
            throw new SecretProviderException('secret_envelope_invalid');
        }
        $this->validateMetadata($decoded['metadata']);
        $ciphertext = base64_decode($decoded['ciphertext_b64'], true);
        if (!is_string($ciphertext) || $ciphertext === '') { throw new SecretProviderException('secret_envelope_invalid'); }
        return ['metadata' => $decoded['metadata'], 'ciphertext' => $ciphertext];
    }

    /** @param array<string, mixed> $metadata */
    public function encodeInner(array $metadata, #[SensitiveParameter] string $secret): string
    {
        $value = new SecretValue($secret);
        return $this->encodeBounded(['metadata' => $metadata, 'secret_b64' => base64_encode($value->reveal())]);
    }

    /** @param array<string, mixed> $expectedMetadata */
    public function decodeInner(string $json, array $expectedMetadata): string
    {
        if ($json === '' || strlen($json) > self::MAX_ENVELOPE_BYTES) {
            throw new SecretProviderException('secret_decryption_failed');
        }
        try { $decoded = json_decode($json, true, 16, JSON_THROW_ON_ERROR); }
        catch (JsonException) { throw new SecretProviderException('secret_decryption_failed'); }
        if (!is_array($decoded) || array_keys($decoded) !== ['metadata', 'secret_b64']
            || !is_array($decoded['metadata']) || !is_string($decoded['secret_b64'])) {
            throw new SecretProviderException('secret_decryption_failed');
        }
        if (!hash_equals($this->canonicalJson($decoded['metadata']), $this->canonicalJson($expectedMetadata))) {
            throw new SecretProviderException('secret_envelope_tampered');
        }
        $secret = base64_decode($decoded['secret_b64'], true);
        if (!is_string($secret) || $secret === '' || strlen($secret) > 4096) {
            throw new SecretProviderException('secret_decryption_failed');
        }
        return $secret;
    }

    /** @param array<string, mixed> $metadata */
    private function validateMetadata(array $metadata): void
    {
        try {
            if (array_keys($metadata) !== ['format', 'version', 'role', 'server', 'windows', 'created_at']
                || $metadata['format'] !== 'backup-app.windows-secret' || $metadata['version'] !== 1
                || !is_string($metadata['role']) || SecretRole::tryFrom($metadata['role']) === null
                || !is_array($metadata['server']) || !is_array($metadata['windows'])
                || array_keys($metadata['server']) !== ['endpoint', 'product', 'version', 'fingerprint']
                || array_keys($metadata['windows']) !== ['account', 'sid']
                || !is_string($metadata['created_at'])
                || preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/D', $metadata['created_at']) !== 1) {
                throw new SecretProviderException('secret_envelope_invalid');
            }
            new SecretServerBinding(...array_values($metadata['server']));
            new WindowsIdentity(...array_values($metadata['windows']));
        } catch (SecretProviderException) {
            throw new SecretProviderException('secret_envelope_invalid');
        } catch (Throwable) {
            throw new SecretProviderException('secret_envelope_invalid');
        }
    }

    /** @param array<string, mixed> $value */
    private function canonicalJson(array $value): string
    {
        try { return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }
        catch (JsonException) { throw new SecretProviderException('secret_envelope_invalid'); }
    }

    /** @param array<string, mixed> $value */
    private function encodeBounded(array $value): string
    {
        $json = $this->canonicalJson($value);
        if (strlen($json) > self::MAX_ENVELOPE_BYTES) { throw new SecretProviderException('secret_envelope_invalid'); }
        return $json;
    }
}
