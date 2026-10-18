<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Ownership;

use BackupApp\Restore\Integration\Policy\TrustedRunId;
use DomainException;
use RuntimeException;

final class OwnershipJournal
{
    private readonly string $path;

    public function __construct(string $directory, private readonly TrustedRunId $runId)
    {
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('ownership_directory_unavailable');
        }
        $this->path = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $runId->value() . '.jsonl';
    }

    public function path(): string { return $this->path; }

    /** @param array<string, mixed> $proposal */
    public function create(array $proposal): OwnershipSnapshot
    {
        $handle = @fopen($this->path, 'x+b');
        if ($handle === false) { throw new DomainException('ownership_journal_exists'); }
        try {
            if (!flock($handle, LOCK_EX)) { throw new RuntimeException('ownership_journal_lock_failed'); }
            $this->writeRecord($handle, 1, str_repeat('0', 64), new OwnershipEvent('run_proposed', $proposal));
            $snapshot = $this->readHandle($handle);
            flock($handle, LOCK_UN);
            return $snapshot;
        } finally { fclose($handle); }
    }

    public function append(OwnershipEvent $event): OwnershipSnapshot
    {
        $handle = @fopen($this->path, 'r+b');
        if ($handle === false) { throw new DomainException('ownership_journal_missing'); }
        try {
            if (!flock($handle, LOCK_EX)) { throw new RuntimeException('ownership_journal_lock_failed'); }
            $snapshot = $this->readHandle($handle);
            if ($event->type() === 'run_proposed'
                || ($event->type() === 'target_created' && $snapshot->latestEvent('target_created') !== null)) {
                throw new DomainException('ownership_event_duplicate_or_invalid');
            }
            $records = $snapshot->records();
            $previous = (string) ($records[array_key_last($records)]['hash'] ?? '');
            $this->writeRecord($handle, $snapshot->revision() + 1, $previous, $event);
            $next = $this->readHandle($handle);
            flock($handle, LOCK_UN);
            return $next;
        } finally { fclose($handle); }
    }

    public function read(): OwnershipSnapshot
    {
        $handle = @fopen($this->path, 'rb');
        if ($handle === false) { throw new DomainException('ownership_journal_missing'); }
        try {
            if (!flock($handle, LOCK_SH)) { throw new RuntimeException('ownership_journal_lock_failed'); }
            $snapshot = $this->readHandle($handle);
            flock($handle, LOCK_UN);
            return $snapshot;
        } finally { fclose($handle); }
    }

    /** @param resource $handle */
    private function readHandle($handle): OwnershipSnapshot
    {
        rewind($handle); $lines = [];
        while (($line = fgets($handle)) !== false) { $lines[] = rtrim($line, "\r\n"); }
        if ($lines === []) { throw new DomainException('ownership_journal_missing'); }
        $records = [];
        $previous = str_repeat('0', 64);
        foreach ($lines as $index => $line) {
            try { $record = json_decode($line, true, 32, JSON_THROW_ON_ERROR); }
            catch (\JsonException) { throw new DomainException('ownership_journal_corrupt'); }
            if (!is_array($record) || ($record['revision'] ?? null) !== $index + 1 || ($record['previous_hash'] ?? null) !== $previous) {
                throw new DomainException('ownership_journal_chain_invalid');
            }
            $hash = (string) ($record['hash'] ?? '');
            $body = $record; unset($body['hash']);
            if (!hash_equals(hash('sha256', json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), $hash)) {
                throw new DomainException('ownership_journal_hash_invalid');
            }
            $records[] = $record;
            $previous = $hash;
        }
        return new OwnershipSnapshot($records);
    }

    /** @param resource $handle */
    private function writeRecord($handle, int $revision, string $previousHash, OwnershipEvent $event): void
    {
        $record = [
            'schema_version' => 1,
            'revision' => $revision,
            'previous_hash' => $previousHash,
            'run_id' => $this->runId->value(),
            'recorded_at' => gmdate('c'),
            'event' => ['type' => $event->type(), 'data' => $event->data()],
        ];
        $record['hash'] = hash('sha256', json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        fseek($handle, 0, SEEK_END);
        $bytes = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle)) { throw new RuntimeException('ownership_journal_write_failed'); }
        @chmod($this->path, 0600);
    }
}
