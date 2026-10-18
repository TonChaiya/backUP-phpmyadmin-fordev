<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Authorization;

use DomainException;
use RuntimeException;

final class LiveAuthorizationReceiptStore
{
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) { throw new RuntimeException('receipt_directory_unavailable'); }
    }
    public function save(string $id, LiveAuthorizationReceipt $receipt): void
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1) { throw new DomainException('invalid_receipt_id'); }
        $path = rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . $id . '.json';
        $handle = @fopen($path, 'xb'); if ($handle === false) { throw new DomainException('receipt_already_exists'); }
        try {
            $encoded = json_encode($receipt->privateRecord(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            if (fwrite($handle, $encoded) !== strlen($encoded) || !fflush($handle)) { throw new RuntimeException('receipt_write_failed'); }
            @chmod($path, 0600);
        }
        finally { fclose($handle); }
    }

    /** @param list<string> $targets @param array<string, string> $artifactSha256 */
    public function consume(
        string $id,
        string $jobId,
        string $runId,
        string $serverFingerprint,
        array $targets,
        array $artifactSha256,
        string $contextId
    ): void {
        if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1) { throw new DomainException('invalid_receipt_id'); }
        $path = rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . $id . '.json';
        $handle = @fopen($path, 'r+b');
        if ($handle === false) { throw new DomainException('authorization_receipt_unusable'); }
        try {
            if (!flock($handle, LOCK_EX)) { throw new RuntimeException('receipt_lock_failed'); }
            rewind($handle); $record = null;
            while (($line = fgets($handle)) !== false) {
                if (!str_ends_with($line, "\n")) { throw new DomainException('authorization_receipt_corrupt'); }
                try { $decoded = json_decode(trim($line), true, 32, JSON_THROW_ON_ERROR); }
                catch (\JsonException) { throw new DomainException('authorization_receipt_corrupt'); }
                if (!is_array($decoded)) { throw new DomainException('authorization_receipt_corrupt'); }
                $record = $decoded;
            }
            $expectedTargets = (array) ($record['targets'] ?? []); sort($expectedTargets, SORT_STRING); sort($targets, SORT_STRING);
            $expectedArtifacts = (array) ($record['artifact_sha256'] ?? []); ksort($expectedArtifacts, SORT_STRING); ksort($artifactSha256, SORT_STRING);
            $valid = is_array($record) && ($record['consumed'] ?? true) === false && time() <= (int) ($record['expires_at'] ?? 0)
                && hash_equals((string) ($record['job_id'] ?? ''), $jobId)
                && hash_equals((string) ($record['run_id'] ?? ''), $runId)
                && hash_equals((string) ($record['server_fingerprint'] ?? ''), $serverFingerprint)
                && $expectedTargets === $targets && $expectedArtifacts !== [] && $expectedArtifacts === $artifactSha256
                && hash_equals((string) ($record['context_id_hash'] ?? ''), hash('sha256', $contextId));
            if (!$valid) { throw new DomainException('authorization_receipt_unusable'); }
            $record['consumed'] = true; $record['consumed_at'] = gmdate('c');
            fseek($handle, 0, SEEK_END);
            $encoded = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            if (fwrite($handle, $encoded) !== strlen($encoded) || !fflush($handle)) { throw new RuntimeException('receipt_consume_failed'); }
            flock($handle, LOCK_UN);
        } finally { fclose($handle); }
    }
}
