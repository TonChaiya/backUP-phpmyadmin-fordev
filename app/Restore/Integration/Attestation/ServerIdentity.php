<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Attestation;

final class ServerIdentity
{
    public function __construct(
        private readonly string $product,
        private readonly string $version,
        private readonly string $stableId,
        private readonly string $endpoint
    ) {}
    public function product(): string { return $this->product; }
    public function version(): string { return $this->version; }
    public function stableId(): string { return $this->stableId; }
    public function endpoint(): string { return $this->endpoint; }
    public function supportedLocalEndpoint(): bool
    {
        if (!in_array($this->product, ['mysql', 'mariadb'], true)) { return false; }
        if ($this->version === '' || !str_starts_with($this->stableId, $this->product . ':')
            || preg_match('/^(?:127\.0\.0\.1|localhost|\[::1\]):([1-9][0-9]{0,4})$/D', $this->endpoint, $match) !== 1) {
            return false;
        }
        return (int) $match[1] <= 65535;
    }
    public function fingerprint(): string { return hash('sha256', implode("\0", [$this->product, $this->version, $this->stableId, $this->endpoint])); }
    public function sameServer(self $other): bool { return hash_equals($this->fingerprint(), $other->fingerprint()); }
}
