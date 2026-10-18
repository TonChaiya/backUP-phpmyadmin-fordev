<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Readiness;

use BackupApp\Restore\Integration\Attestation\PrivilegeEvidence;
use BackupApp\Restore\Integration\Attestation\ServerIdentity;

final class ReadinessEvidence
{
    /**
     * @param array<string, bool> $sourceCapabilities Exact source name => capability proven.
     * @param array<string, bool> $targetAbsence Exact target name => absence proven.
     */
    public function __construct(
        private readonly ?ServerIdentity $readerIdentity = null,
        private readonly ?ServerIdentity $runnerIdentity = null,
        private readonly ?bool $backupReaderCredentialAvailable = null,
        private readonly ?bool $restoreRunnerCredentialAvailable = null,
        private readonly ?bool $credentialsSeparated = null,
        private readonly ?PrivilegeEvidence $readerPrivileges = null,
        private readonly ?PrivilegeEvidence $runnerPrivileges = null,
        private readonly array $sourceCapabilities = [],
        private readonly array $targetAbsence = [],
        private readonly ?bool $sharedLockConfigured = null,
        private readonly ?bool $legacyLockQuiescent = null,
        private readonly ?bool $sharedLockQuiescent = null,
        private readonly bool $restoreExecutionHardDisabled = true
    ) {}

    public static function planOnly(): self
    {
        return new self();
    }

    public function withServerIdentities(?ServerIdentity $reader, ?ServerIdentity $runner): self
    {
        return new self(
            $reader,
            $runner,
            $this->backupReaderCredentialAvailable,
            $this->restoreRunnerCredentialAvailable,
            $this->credentialsSeparated,
            $this->readerPrivileges,
            $this->runnerPrivileges,
            $this->sourceCapabilities,
            $this->targetAbsence,
            $this->sharedLockConfigured,
            $this->legacyLockQuiescent,
            $this->sharedLockQuiescent,
            $this->restoreExecutionHardDisabled
        );
    }

    /** @param array<string, bool> $capabilities */
    public function withSourceCapabilities(array $capabilities): self
    {
        return new self(
            $this->readerIdentity,
            $this->runnerIdentity,
            $this->backupReaderCredentialAvailable,
            $this->restoreRunnerCredentialAvailable,
            $this->credentialsSeparated,
            $this->readerPrivileges,
            $this->runnerPrivileges,
            $capabilities,
            $this->targetAbsence,
            $this->sharedLockConfigured,
            $this->legacyLockQuiescent,
            $this->sharedLockQuiescent,
            $this->restoreExecutionHardDisabled
        );
    }

    public function readerIdentity(): ?ServerIdentity { return $this->readerIdentity; }
    public function runnerIdentity(): ?ServerIdentity { return $this->runnerIdentity; }
    public function backupReaderCredentialAvailable(): ?bool { return $this->backupReaderCredentialAvailable; }
    public function restoreRunnerCredentialAvailable(): ?bool { return $this->restoreRunnerCredentialAvailable; }
    public function credentialsSeparated(): ?bool { return $this->credentialsSeparated; }
    public function readerPrivileges(): ?PrivilegeEvidence { return $this->readerPrivileges; }
    public function runnerPrivileges(): ?PrivilegeEvidence { return $this->runnerPrivileges; }
    /** @return array<string, bool> */ public function sourceCapabilities(): array { return $this->sourceCapabilities; }
    /** @return array<string, bool> */ public function targetAbsence(): array { return $this->targetAbsence; }
    public function sharedLockConfigured(): ?bool { return $this->sharedLockConfigured; }
    public function legacyLockQuiescent(): ?bool { return $this->legacyLockQuiescent; }
    public function sharedLockQuiescent(): ?bool { return $this->sharedLockQuiescent; }
    public function restoreExecutionHardDisabled(): bool { return $this->restoreExecutionHardDisabled; }
}
