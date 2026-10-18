<?php
declare(strict_types=1);

use BackupApp\Restore\Integration\Secrets\RuntimeIdentityProvider;
use BackupApp\Restore\Integration\Secrets\SecretEnvelopeCodec;
use BackupApp\Restore\Integration\Secrets\SecretEnvelopeStore;
use BackupApp\Restore\Integration\Secrets\SecretProtector;
use BackupApp\Restore\Integration\Secrets\SecretRole;
use BackupApp\Restore\Integration\Secrets\SecretServerBinding;
use BackupApp\Restore\Integration\Secrets\WindowsIdentity;
use BackupApp\Restore\Integration\Secrets\WindowsSecretProvider;

final class FakeRuntimeIdentity implements RuntimeIdentityProvider
{
    public function __construct(public WindowsIdentity $identity) {}
    public function currentIdentity(): WindowsIdentity { return $this->identity; }
}
final class FakeSecretProtector implements SecretProtector
{
    public bool $fail = false;
    public function protect(string $plaintext, string $entropy, WindowsIdentity $identity): string
    { if ($this->fail) { throw new \BackupApp\Restore\Integration\Secrets\SecretProviderException('worker_timeout'); } return hash_hmac('sha256', $plaintext, $entropy, true) . $plaintext; }
    public function unprotect(string $ciphertext, string $entropy, WindowsIdentity $identity): string
    { if ($this->fail || strlen($ciphertext) < 32) { throw new \BackupApp\Restore\Integration\Secrets\SecretProviderException('secret_decryption_failed'); } $plain=substr($ciphertext,32); if (!hash_equals(substr($ciphertext,0,32),hash_hmac('sha256',$plain,$entropy,true))) { throw new \BackupApp\Restore\Integration\Secrets\SecretProviderException('secret_decryption_failed'); } return $plain; }
}
final class MemorySecretStore implements SecretEnvelopeStore
{
    public array $values = [];
    public function create(SecretRole $role, string $envelope, WindowsIdentity $identity): void { if (isset($this->values[$role->value])) { throw new \BackupApp\Restore\Integration\Secrets\SecretProviderException('secret_already_exists'); } $this->values[$role->value]=$envelope; }
    public function read(SecretRole $role): string { return $this->values[$role->value] ?? throw new \BackupApp\Restore\Integration\Secrets\SecretProviderException('secret_missing'); }
    public function delete(SecretRole $role): void { unset($this->values[$role->value]); }
}

function providerFixture(): array
{
    $identity = new WindowsIdentity('DESKTOP-TEST\\svc', 'S-1-5-21-100-200-300-1001');
    $runtime = new FakeRuntimeIdentity($identity); $protector = new FakeSecretProtector(); $store = new MemorySecretStore();
    $provider = new WindowsSecretProvider(new SecretEnvelopeCodec(), $store, $runtime, $protector, fn () => new DateTimeImmutable('2026-10-18T01:02:03Z'));
    return [$provider,$runtime,$protector,$store,$identity];
}

test('provider stores and retrieves exact role and server bound secret', function (): void {
    [$provider,,,$store] = providerFixture();
    $provider->store(SecretRole::BackupReader, secretTestBinding(), 'secret-sentinel');
    assertNotContainsValue('secret-sentinel', $store->values['backup_reader']);
    assertSameValue('secret-sentinel', $provider->retrieve(SecretRole::BackupReader, secretTestBinding())->reveal());
    assertSecretCode('secret_missing', fn () => $provider->retrieve(SecretRole::RestoreRunner, secretTestBinding()));
});

test('provider rejects SID server role and metadata drift before release', function (): void {
    [$provider,$runtime,,$store] = providerFixture();
    $provider->store(SecretRole::BackupReader, secretTestBinding(), 'secret-sentinel');
    $runtime->identity = new WindowsIdentity('DESKTOP-TEST\\other', 'S-1-5-21-100-200-300-1002');
    assertSecretCode('windows_identity_mismatch', fn () => $provider->retrieve(SecretRole::BackupReader, secretTestBinding()));
    $runtime->identity = new WindowsIdentity('DESKTOP-TEST\\svc', 'S-1-5-21-100-200-300-1001');
    $runtime->identity = new WindowsIdentity('DESKTOP-TEST\\renamed', 'S-1-5-21-100-200-300-1001');
    assertSecretCode('windows_identity_mismatch', fn () => $provider->retrieve(SecretRole::BackupReader, secretTestBinding()));
    $runtime->identity = new WindowsIdentity('DESKTOP-TEST\\svc', 'S-1-5-21-100-200-300-1001');
    $wrong = new SecretServerBinding('127.0.0.1:3306','mysql','8.4.3','mysql:different');
    assertSecretCode('server_binding_mismatch', fn () => $provider->retrieve(SecretRole::BackupReader, $wrong));
    $decoded = json_decode($store->values['backup_reader'], true); $decoded['metadata']['role']='restore_runner';
    $store->values['backup_reader'] = json_encode($decoded);
    assertSecretCode('secret_role_mismatch', fn () => $provider->retrieve(SecretRole::BackupReader, secretTestBinding()));
});

test('provider exposes stable failures and no plaintext fallback', function (): void {
    [$provider,,$protector,$store] = providerFixture();
    $protector->fail = true;
    try { $provider->store(SecretRole::BackupReader, secretTestBinding(), 'secret-sentinel'); throw new RuntimeException('Expected failure'); }
    catch (\BackupApp\Restore\Integration\Secrets\SecretProviderException $e) { assertSameValue('worker_timeout',$e->getMessage()); assertNotContainsValue('secret-sentinel',$e->getMessage()); }
    assertSameValue([], $store->values);
});

test('provider rejects tampered ciphertext without leaking decrypted material', function (): void {
    [$provider,,, $store] = providerFixture();
    $provider->store(SecretRole::BackupReader, secretTestBinding(), 'secret-sentinel');
    $envelope = json_decode($store->values['backup_reader'], true);
    $cipher = base64_decode($envelope['ciphertext_b64'], true);
    $cipher[0] = chr(ord($cipher[0]) ^ 1);
    $envelope['ciphertext_b64'] = base64_encode($cipher);
    $store->values['backup_reader'] = json_encode($envelope);
    assertSecretCode('secret_decryption_failed', fn () => $provider->retrieve(SecretRole::BackupReader, secretTestBinding()));
});

test('provider verifies envelope before exact-role delete', function (): void {
    [$provider,,,$store] = providerFixture();
    $provider->store(SecretRole::RestoreRunner, secretTestBinding(), 'synthetic');
    $provider->deleteVerified(SecretRole::RestoreRunner, secretTestBinding());
    assertSameValue([], $store->values);
});
