<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Sql;

use DomainException;

final class StatementSpoolLease
{
    private bool $deleted = false;
    public function __construct(private readonly string $path) {}

    public function read(int $offset, int $length, string $sha256): string
    {
        if ($this->deleted || $length < 0 || $length > \BackupApp\Restore\Sql\SqlStatementLexer::MAX_STATEMENT_BYTES) {
            throw new DomainException('statement_spool_unavailable');
        }
        $handle = @fopen($this->path, 'rb');
        if ($handle === false) { throw new DomainException('statement_spool_unavailable'); }
        try {
            if (fseek($handle, $offset) !== 0) { throw new DomainException('statement_spool_unavailable'); }
            $sql = '';
            while (strlen($sql) < $length) {
                $chunk = fread($handle, min(65536, $length - strlen($sql)));
                if ($chunk === false || $chunk === '') { throw new DomainException('statement_spool_truncated'); }
                $sql .= $chunk;
            }
        } finally { fclose($handle); }
        if (!hash_equals($sha256, hash('sha256', $sql))) { throw new DomainException('statement_spool_changed'); }
        return $sql;
    }

    public function delete(): void
    {
        if (!$this->deleted && is_file($this->path)) { @unlink($this->path); }
        $this->deleted = true;
    }
    public function __destruct() { $this->delete(); }
}
