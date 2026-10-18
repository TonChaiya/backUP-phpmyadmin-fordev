<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Attestation;

use BackupApp\Restore\Integration\Database\MetadataConnection;
use DomainException;

final class ServerIdentityProbe
{
    public function probe(MetadataConnection $connection): ServerIdentity
    {
        $facts = $connection->serverIdentityFacts();
        $product = strtolower(trim((string) ($facts['product'] ?? '')));
        $version = trim((string) ($facts['version'] ?? ''));
        $stableId = trim((string) ($facts['stable_id'] ?? ''));
        $endpoint = trim((string) ($facts['endpoint'] ?? ''));
        if (!in_array($product, ['mysql', 'mariadb'], true) || $version === '' || $stableId === '' || $endpoint === ''
            || !str_starts_with($stableId, $product . ':')) {
            throw new DomainException('server_identity_unproven');
        }
        return new ServerIdentity($product, $version, $stableId, $endpoint);
    }
}
