<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Database;

use BackupApp\Restore\Integration\Policy\RestoreIntegrationPolicy;
use DomainException;

final class AuthorizedSourceCatalog
{
    public function __construct(private readonly RestoreIntegrationPolicy $policy) {}

    /** @return list<string> */
    public function available(MetadataConnection $connection): array
    {
        $allowed = $this->policy->authorizedSources();
        $found = $connection->authorizedSchemas($allowed);
        foreach ($found as $name) {
            if (!is_string($name) || !in_array($name, $allowed, true)) {
                throw new DomainException('catalog_scope_violation');
            }
        }
        return array_values(array_intersect($allowed, $found));
    }
}
