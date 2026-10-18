<?php

declare(strict_types=1);

use BackupApp\Restore\Integration\Secrets\SecretEnvelopeCodec;
use BackupApp\Restore\Integration\Secrets\SecretProviderException;
use BackupApp\Restore\Integration\Secrets\SecretRole;
use BackupApp\Restore\Integration\Secrets\SecretServerBinding;
use BackupApp\Restore\Integration\Secrets\SecretValue;
use BackupApp\Restore\Integration\Secrets\WindowsIdentity;

function secretTestBinding(): SecretServerBinding
{
    return new SecretServerBinding('127.0.0.1:3306', 'mysql', '8.4.3', 'mysql:fixture-uuid-1234');
}

function assertSecretCode(string $expected, Closure $callback): void
{
    try {
        $callback();
    } catch (SecretProviderException $exception) {
        assertSameValue($expected, $exception->getMessage());
        return;
    }
    throw new RuntimeException('Expected secret provider exception: ' . $expected);
}

test('Windows secret domain accepts only exact roles and server binding', function (): void {
    assertSameValue('backup_reader', SecretRole::BackupReader->value);
    assertSameValue('restore_runner', SecretRole::RestoreRunner->value);
    assertSameValue([
        'endpoint' => '127.0.0.1:3306',
        'product' => 'mysql',
        'version' => '8.4.3',
        'fingerprint' => 'mysql:fixture-uuid-1234',
    ], secretTestBinding()->metadata());

    foreach ([
        ['127.0.0.1:3333', 'mysql', '8.4.3', 'mysql:x'],
        ['localhost:3306', 'mysql', '8.4.3', 'mysql:x'],
        ['127.0.0.1:3306', 'mariadb', '8.4.3', 'mysql:x'],
        ['127.0.0.1:3306', 'mysql', '8.4.4', 'mysql:x'],
        ['127.0.0.1:3306', 'mysql', '8.4.3', ''],
        ['127.0.0.1:3306', 'mysql', '8.4.3', 'mariadb:x'],
    ] as $invalid) {
        assertSecretCode('server_binding_invalid', fn () => new SecretServerBinding(...$invalid));
    }
});

test('Windows identity validates SID and account label', function (): void {
    $identity = new WindowsIdentity('DESKTOP-TEST\\svc', 'S-1-5-21-100-200-300-1001');
    assertSameValue([
        'account' => 'DESKTOP-TEST\\svc',
        'sid' => 'S-1-5-21-100-200-300-1001',
    ], $identity->metadata());
    assertSecretCode('windows_identity_invalid', fn () => new WindowsIdentity('', 'S-1-5-21-1'));
    assertSecretCode('windows_identity_invalid', fn () => new WindowsIdentity('svc', 'not-a-sid'));
});

test('Windows secret envelope authenticates deterministic canonical metadata', function (): void {
    $codec = new SecretEnvelopeCodec();
    $identity = new WindowsIdentity('DESKTOP-TEST\\svc', 'S-1-5-21-100-200-300-1001');
    $metadata = $codec->metadata(
        SecretRole::BackupReader,
        secretTestBinding(),
        $identity,
        new DateTimeImmutable('2026-10-18T01:02:03Z')
    );
    assertSameValue([
        'format' => 'backup-app.windows-secret',
        'version' => 1,
        'role' => 'backup_reader',
        'server' => [
            'endpoint' => '127.0.0.1:3306',
            'product' => 'mysql',
            'version' => '8.4.3',
            'fingerprint' => 'mysql:fixture-uuid-1234',
        ],
        'windows' => [
            'account' => 'DESKTOP-TEST\\svc',
            'sid' => 'S-1-5-21-100-200-300-1001',
        ],
        'created_at' => '2026-10-18T01:02:03Z',
    ], $metadata);
    assertSameValue(32, strlen($codec->entropy($metadata)));
    assertSameValue($codec->entropy($metadata), $codec->entropy($metadata));

    $changed = $metadata;
    $changed['role'] = 'restore_runner';
    assertFalseValue(hash_equals($codec->entropy($metadata), $codec->entropy($changed)));
});

test('Windows secret envelope rejects malformed and tampered metadata', function (): void {
    $codec = new SecretEnvelopeCodec();
    $identity = new WindowsIdentity('DESKTOP-TEST\\svc', 'S-1-5-21-100-200-300-1001');
    $metadata = $codec->metadata(SecretRole::BackupReader, secretTestBinding(), $identity, new DateTimeImmutable('2026-10-18T01:02:03Z'));
    $inner = $codec->encodeInner($metadata, 'synthetic-secret');
    assertSameValue('synthetic-secret', $codec->decodeInner($inner, $metadata));

    foreach (['role', 'created_at'] as $field) {
        $tampered = $metadata;
        $tampered[$field] = $field === 'role' ? 'restore_runner' : '2026-10-18T01:02:04Z';
        assertSecretCode('secret_envelope_tampered', fn () => $codec->decodeInner($inner, $tampered));
    }
    $tampered = $metadata;
    $tampered['server']['endpoint'] = '127.0.0.1:3333';
    assertSecretCode('secret_envelope_tampered', fn () => $codec->decodeInner($inner, $tampered));

    $outer = $codec->encodeEnvelope($metadata, "\x01\x02ciphertext");
    $decoded = $codec->decodeEnvelope($outer);
    assertSameValue($metadata, $decoded['metadata']);
    assertSameValue("\x01\x02ciphertext", $decoded['ciphertext']);
    assertSecretCode('secret_envelope_invalid', fn () => $codec->decodeEnvelope('{}'));
    assertSecretCode('secret_envelope_invalid', fn () => $codec->decodeEnvelope(str_repeat('x', 65537)));
});

test('SecretValue cannot serialize stringify or reveal through debug output', function (): void {
    $value = new SecretValue('synthetic-secret');
    assertSameValue('synthetic-secret', $value->reveal());
    assertNotContainsValue('synthetic-secret', var_export($value, true));
    assertSecretCode('secret_serialization_forbidden', fn () => serialize($value));
    assertSecretCode('secret_input_invalid', fn () => new SecretValue(''));
    assertSecretCode('secret_input_too_large', fn () => new SecretValue(str_repeat('x', 4097)));
});
