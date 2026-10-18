<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Secrets;

final class SecretServerBinding
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $product,
        private readonly string $version,
        private readonly string $fingerprint
    ) {
        if ($endpoint !== '127.0.0.1:3306'
            || $product !== 'mysql'
            || $version !== '8.4.3'
            || preg_match('/^mysql:[A-Za-z0-9._:-]{1,200}$/D', $fingerprint) !== 1) {
            throw new SecretProviderException('server_binding_invalid');
        }
    }

    /** @return array{endpoint: string, product: string, version: string, fingerprint: string} */
    public function metadata(): array
    {
        return [
            'endpoint' => $this->endpoint,
            'product' => $this->product,
            'version' => $this->version,
            'fingerprint' => $this->fingerprint,
        ];
    }
}
