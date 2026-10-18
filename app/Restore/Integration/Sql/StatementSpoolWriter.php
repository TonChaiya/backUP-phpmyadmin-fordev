<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Sql;

use RuntimeException;

final class StatementSpoolWriter
{
    /** @var resource|null */ private $handle;
    private readonly StatementSpoolLease $lease;
    private int $offset = 0;

    public function __construct(?string $directory = null)
    {
        $path = tempnam($directory ?? sys_get_temp_dir(), 'restore-statement-spool-');
        if ($path === false) { throw new RuntimeException('statement_spool_create_failed'); }
        $this->handle = @fopen($path, 'wb');
        if ($this->handle === false) { @unlink($path); throw new RuntimeException('statement_spool_open_failed'); }
        @chmod($path, 0600);
        $this->lease = new StatementSpoolLease($path);
    }

    public function append(string $sql): FileStatementPayload
    {
        if (!is_resource($this->handle)) { throw new RuntimeException('statement_spool_closed'); }
        $length = strlen($sql);
        if ($length > \BackupApp\Restore\Sql\SqlStatementLexer::MAX_STATEMENT_BYTES) { throw new RuntimeException('statement_too_large'); }
        $written = 0;
        while ($written < $length) {
            $count = fwrite($this->handle, substr($sql, $written, min(65536, $length - $written)));
            if (!is_int($count) || $count < 1) { throw new RuntimeException('statement_spool_write_failed'); }
            $written += $count;
        }
        $payload = new FileStatementPayload($this->lease, $this->offset, $length, hash('sha256', $sql));
        $this->offset += $length;
        return $payload;
    }

    public function finish(): void
    {
        if (is_resource($this->handle)) { fflush($this->handle); fclose($this->handle); $this->handle = null; }
    }
    public function abort(): void { $this->finish(); $this->lease->delete(); }
    public function __destruct() { $this->finish(); }
}
