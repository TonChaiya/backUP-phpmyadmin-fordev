<?php

declare(strict_types=1);

namespace BackupApp\Restore\Sql;

use RuntimeException;

/** @internal Bounded reader that fingerprints the exact bytes supplied to the lexer. */
final class SqlFileByteReader
{
    /** @var resource */ private $handle;
    private string $buffer = '';
    private int $position = 0;
    private int $bytes = 0;
    /** @var \HashContext */ private $hash;

    /** @param resource $handle */
    public function __construct($handle, private readonly int $chunkBytes)
    {
        if (!is_resource($handle) || $chunkBytes < 1 || $chunkBytes > 1048576) {
            throw new RuntimeException('invalid_sql_stream_reader');
        }
        $this->handle = $handle;
        $this->hash = hash_init('sha256');
    }

    public function read(): ?string
    {
        if ($this->position >= strlen($this->buffer)) {
            $chunk = fread($this->handle, $this->chunkBytes);
            if ($chunk === false) { throw new RuntimeException('artifact_read_failed'); }
            if ($chunk === '') {
                if (!feof($this->handle)) { throw new RuntimeException('artifact_read_failed'); }
                return null;
            }
            $this->buffer = $chunk;
            $this->position = 0;
            $this->bytes += strlen($chunk);
            hash_update($this->hash, $chunk);
        }
        return $this->buffer[$this->position++];
    }

    /** @return array{bytes: int, sha256: string} */
    public function finish(): array
    {
        while ($this->read() !== null) {}
        return ['bytes' => $this->bytes, 'sha256' => hash_final($this->hash)];
    }
}
