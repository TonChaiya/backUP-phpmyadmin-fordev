<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Validation;

use RuntimeException;

final class RowEvidenceAccumulator
{
    private const MAX_OPEN_RUNS = 32;
    /** @var list<string> */ private array $hashes = [];
    /** @var list<string> */ private array $runs = [];
    private int $count = 0;

    public function __construct(
        private readonly string $table,
        private readonly array $columns,
        private readonly int $chunkHashes = 2048,
        private readonly ?string $temporaryDirectory = null
    ) {
        if ($chunkHashes < 1) { throw new RuntimeException('invalid_evidence_chunk_limit'); }
    }

    public function addCanonicalRow(string $canonical): void
    {
        $this->hashes[] = hash('sha256', $canonical);
        $this->count++;
        if (count($this->hashes) >= $this->chunkHashes) { $this->flushRun(); }
    }

    public function finish(): TableDataEvidence
    {
        if ($this->runs === []) {
            sort($this->hashes, SORT_STRING);
            $context = hash_init('sha256');
            foreach ($this->hashes as $hash) { hash_update($context, $hash . "\n"); }
            $this->hashes = [];
            return new TableDataEvidence($this->table, $this->columns, $this->count, hash_final($context));
        }
        if ($this->hashes !== []) { $this->flushRun(); }
        $this->compactRuns();
        $handles = [];
        $heads = [];
        try {
            foreach ($this->runs as $index => $path) {
                $handle = @fopen($path, 'rb');
                if ($handle === false) { throw new RuntimeException('row_evidence_run_read_failed'); }
                $handles[$index] = $handle;
                $line = fgets($handle);
                if (is_string($line)) { $heads[$index] = rtrim($line, "\r\n"); }
            }
            $context = hash_init('sha256');
            while ($heads !== []) {
                $minimum = min($heads);
                $index = array_search($minimum, $heads, true);
                hash_update($context, $minimum . "\n");
                $line = fgets($handles[$index]);
                if (is_string($line)) { $heads[$index] = rtrim($line, "\r\n"); } else { unset($heads[$index]); }
            }
            return new TableDataEvidence($this->table, $this->columns, $this->count, hash_final($context));
        } finally {
            foreach ($handles as $handle) { fclose($handle); }
            $this->cleanupRuns();
        }
    }

    public function __destruct() { $this->cleanupRuns(); }

    private function flushRun(): void
    {
        sort($this->hashes, SORT_STRING);
        $directory = $this->temporaryDirectory ?? sys_get_temp_dir();
        $path = tempnam($directory, 'restore-row-evidence-');
        if ($path === false) { throw new RuntimeException('row_evidence_temp_failed'); }
        $handle = @fopen($path, 'wb');
        if ($handle === false) { @unlink($path); throw new RuntimeException('row_evidence_temp_open_failed'); }
        try {
            foreach ($this->hashes as $hash) {
                if (fwrite($handle, $hash . "\n") === false) { throw new RuntimeException('row_evidence_temp_write_failed'); }
            }
        } finally { fclose($handle); }
        @chmod($path, 0600);
        $this->runs[] = $path;
        $this->hashes = [];
    }

    private function cleanupRuns(): void
    {
        foreach ($this->runs as $path) { if (is_file($path)) { @unlink($path); } }
        $this->runs = [];
    }

    private function compactRuns(): void
    {
        while (count($this->runs) > self::MAX_OPEN_RUNS) {
            $next = [];
            foreach (array_chunk($this->runs, self::MAX_OPEN_RUNS) as $group) {
                $next[] = $this->mergeRuns($group);
            }
            $this->runs = $next;
        }
    }

    /** @param list<string> $paths */
    private function mergeRuns(array $paths): string
    {
        $directory = $this->temporaryDirectory ?? sys_get_temp_dir();
        $output = tempnam($directory, 'restore-row-merge-');
        if ($output === false) { throw new RuntimeException('row_evidence_temp_failed'); }
        $writer = @fopen($output, 'wb');
        if ($writer === false) { @unlink($output); throw new RuntimeException('row_evidence_temp_open_failed'); }
        $handles = []; $heads = [];
        try {
            foreach ($paths as $index => $path) {
                $handle = @fopen($path, 'rb');
                if ($handle === false) { throw new RuntimeException('row_evidence_run_read_failed'); }
                $handles[$index] = $handle;
                $line = fgets($handle);
                if (is_string($line)) { $heads[$index] = rtrim($line, "\r\n"); }
            }
            while ($heads !== []) {
                $minimum = min($heads); $index = array_search($minimum, $heads, true);
                if (fwrite($writer, $minimum . "\n") === false) { throw new RuntimeException('row_evidence_temp_write_failed'); }
                $line = fgets($handles[$index]);
                if (is_string($line)) { $heads[$index] = rtrim($line, "\r\n"); } else { unset($heads[$index]); }
            }
        } catch (\Throwable $exception) {
            @unlink($output);
            throw $exception;
        } finally {
            fclose($writer);
            foreach ($handles as $handle) { fclose($handle); }
        }
        @chmod($output, 0600);
        foreach ($paths as $path) { @unlink($path); }
        return $output;
    }
}
