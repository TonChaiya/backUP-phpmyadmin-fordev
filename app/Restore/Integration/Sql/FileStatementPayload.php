<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Sql;

final class FileStatementPayload implements StatementPayload
{
    public function __construct(
        private readonly StatementSpoolLease $lease,
        private readonly int $offset,
        private readonly int $length,
        private readonly string $sha256
    ) {}
    public function read(): string { return $this->lease->read($this->offset, $this->length, $this->sha256); }
}
