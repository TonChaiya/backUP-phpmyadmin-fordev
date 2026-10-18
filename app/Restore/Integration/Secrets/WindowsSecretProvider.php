<?php
declare(strict_types=1);
namespace BackupApp\Restore\Integration\Secrets;

use Closure;
use DateTimeImmutable;
use SensitiveParameter;

final class WindowsSecretProvider
{
    /** @param Closure(): DateTimeImmutable $clock */
    public function __construct(
        private readonly SecretEnvelopeCodec $codec,
        private readonly SecretEnvelopeStore $store,
        private readonly RuntimeIdentityProvider $identityProvider,
        private readonly SecretProtector $protector,
        private readonly Closure $clock
    ) {}

    public function store(SecretRole $role, SecretServerBinding $binding, #[SensitiveParameter] string $secret): void
    {
        $value = new SecretValue($secret);
        $identity = $this->identityProvider->currentIdentity();
        $metadata = $this->codec->metadata($role, $binding, $identity, ($this->clock)());
        $inner = $this->codec->encodeInner($metadata, $value->reveal());
        $ciphertext = $this->protector->protect($inner, $this->codec->entropy($metadata), $identity);
        $this->store->create($role, $this->codec->encodeEnvelope($metadata, $ciphertext), $identity);
    }

    public function retrieve(SecretRole $role, SecretServerBinding $binding): SecretValue
    {
        $decoded = $this->codec->decodeEnvelope($this->store->read($role));
        $metadata = $decoded['metadata'];
        if (!hash_equals($role->value, (string) $metadata['role'])) { throw new SecretProviderException('secret_role_mismatch'); }
        $expectedServer = $binding->metadata();
        if (!$this->sameMetadata($expectedServer, $metadata['server'])) { throw new SecretProviderException('server_binding_mismatch'); }
        $runtimeIdentity = $this->identityProvider->currentIdentity();
        if (!$this->sameMetadata($runtimeIdentity->metadata(), $metadata['windows'])) { throw new SecretProviderException('windows_identity_mismatch'); }
        $plaintext = $this->protector->unprotect($decoded['ciphertext'], $this->codec->entropy($metadata), $runtimeIdentity);
        return new SecretValue($this->codec->decodeInner($plaintext, $metadata));
    }

    public function deleteVerified(SecretRole $role, SecretServerBinding $binding): void
    {
        $verified = $this->retrieve($role, $binding);
        unset($verified);
        $this->store->delete($role);
    }

    /** @param array<string, mixed> $expected @param mixed $actual */
    private function sameMetadata(array $expected, mixed $actual): bool
    {
        if (!is_array($actual)) { return false; }
        $encode = static fn (array $value): string => (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash_equals($encode($expected), $encode($actual));
    }
}
