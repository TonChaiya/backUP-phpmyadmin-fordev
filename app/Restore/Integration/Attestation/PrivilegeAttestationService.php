<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Attestation;

use BackupApp\Restore\Integration\Database\MetadataConnection;
use BackupApp\Restore\Integration\Policy\RestoreIntegrationPolicy;
use BackupApp\Restore\Integration\Policy\TrustedTargetName;

final class PrivilegeAttestationService
{
    public function __construct(
        private readonly RestoreIntegrationPolicy $policy = new RestoreIntegrationPolicy(),
        private readonly ServerIdentityProbe $identityProbe = new ServerIdentityProbe()
    ) {}

    /** @return array{identity: ServerIdentity, privileges: PrivilegeEvidence} */
    public function backupReader(MetadataConnection $connection): array
    {
        $identity = $this->identityProbe->probe($connection);
        $attestor = new PrivilegeAttestor($this->policy, $this->dialect($identity));
        return ['identity' => $identity, 'privileges' => $attestor->backupReaderEvidence(
            $identity, $connection->currentAccount(), $connection->grantLines(), $connection->privilegeContextFacts()
        )];
    }

    /** @param list<TrustedTargetName> $targets @return array{identity: ServerIdentity, privileges: PrivilegeEvidence} */
    public function restoreRunner(MetadataConnection $connection, array $targets): array
    {
        $identity = $this->identityProbe->probe($connection);
        $attestor = new PrivilegeAttestor($this->policy, $this->dialect($identity));
        return ['identity' => $identity, 'privileges' => $attestor->restoreRunnerEvidence(
            $identity, $connection->currentAccount(), $connection->grantLines(), $targets, $connection->privilegeContextFacts()
        )];
    }

    private function dialect(ServerIdentity $identity): PrivilegeDialect
    {
        return $identity->product() === 'mariadb' ? new MariaDbPrivilegeDialect() : new MySqlPrivilegeDialect();
    }
}
