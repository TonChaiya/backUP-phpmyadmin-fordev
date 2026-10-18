<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Job;

use BackupApp\Restore\Integration\Policy\TrustedRunId;
use DomainException;
use RuntimeException;

final class RestoreIntegrationJobStore
{
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) { throw new RuntimeException('integration_job_directory_unavailable'); }
    }

    /** @param list<array<string, mixed>> $items @return array<string, mixed> */
    public function create(TrustedRunId $runId, array $items): array
    {
        if (count($items) !== 4) { throw new DomainException('integration_job_requires_four_items'); }
        do { $id = bin2hex(random_bytes(16)); $path = $this->pathFor($id); $handle = @fopen($path, 'xb'); }
        while ($handle === false && is_file($path));
        if ($handle === false) { throw new RuntimeException('integration_job_create_failed'); }
        $now = gmdate('c');
        $job = $this->sanitize([
            'id' => $id, 'run_id' => $runId->value(), 'status' => 'queued', 'revision' => 1,
            'created_at' => $now, 'updated_at' => $now, 'execution_claimed_at' => null, 'finished_at' => null,
            'items' => array_map(static fn (array $item): array => array_merge($item, [
                'status' => 'queued', 'started_at' => null, 'finished_at' => null,
                'result_code' => null, 'comparison' => null, 'cleanup' => null,
            ]), $items),
        ]);
        try { flock($handle, LOCK_EX); $this->append($handle, $job); @chmod($path, 0600); flock($handle, LOCK_UN); }
        finally { fclose($handle); }
        return $job;
    }

    /** @return array<string, mixed> */
    public function read(string $id): array
    {
        $handle = @fopen($this->pathFor($id), 'rb');
        if ($handle === false) { throw new RuntimeException('integration_job_not_found'); }
        try { flock($handle, LOCK_SH); $job = $this->last($handle); flock($handle, LOCK_UN); }
        finally { fclose($handle); }
        if ($job === null) { throw new RuntimeException('integration_job_invalid'); }
        return $job;
    }

    /** @param callable(array<string, mixed>): array<string, mixed> $mutator @return array<string, mixed> */
    public function update(string $id, callable $mutator): array
    {
        $handle = @fopen($this->pathFor($id), 'r+b');
        if ($handle === false) { throw new RuntimeException('integration_job_not_found'); }
        try {
            flock($handle, LOCK_EX);
            $current = $this->last($handle);
            if ($current === null) { throw new RuntimeException('integration_job_invalid'); }
            $next = $mutator($current);
            RestoreIntegrationState::assertTransition((string) $current['status'], (string) ($next['status'] ?? ''));
            if (($next['status'] ?? null) === 'succeeded') {
                foreach ((array) ($next['items'] ?? []) as $item) {
                    if (($item['status'] ?? null) !== 'succeeded'
                        || ($item['comparison']['mandatory_passed'] ?? false) !== true
                        || ($item['cleanup']['verified_absent'] ?? false) !== true) {
                        throw new DomainException('premature_integration_success');
                    }
                }
            }
            $next['id'] = $id; $next['run_id'] = $current['run_id']; $next['created_at'] = $current['created_at'];
            $next['revision'] = ((int) $current['revision']) + 1; $next['updated_at'] = gmdate('c');
            $next = $this->sanitize($next); $this->repairIncompleteTail($handle); $this->append($handle, $next); flock($handle, LOCK_UN);
        } finally { fclose($handle); }
        return $next;
    }

    public function pathFor(string $id): string
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1) { throw new DomainException('invalid_integration_job_id'); }
        return rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . $id . '.jsonl';
    }

    /** @param resource $handle @param array<string, mixed> $job */
    private function append($handle, array $job): void
    {
        fseek($handle, 0, SEEK_END); $encoded = json_encode($job, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (fwrite($handle, $encoded) !== strlen($encoded) || !fflush($handle)) { throw new RuntimeException('integration_job_write_failed'); }
    }

    /** @param resource $handle @return array<string, mixed>|null */
    private function last($handle): ?array
    {
        rewind($handle); $last = null;
        while (($line = fgets($handle)) !== false) { $decoded = json_decode(trim($line), true); if (is_array($decoded)) { $last = $this->sanitize($decoded); } }
        return $last;
    }

    /** @param resource $handle */
    private function repairIncompleteTail($handle): void
    {
        rewind($handle); $contents = stream_get_contents($handle);
        if (!is_string($contents) || $contents === '' || str_ends_with($contents, "\n")) { return; }
        $lastNewline = strrpos($contents, "\n");
        if ($lastNewline === false || !ftruncate($handle, $lastNewline + 1)) { throw new RuntimeException('integration_job_tail_repair_failed'); }
        fflush($handle);
    }

    /** @param array<string, mixed> $job @return array<string, mixed> */
    private function sanitize(array $job): array
    {
        $items = [];
        foreach ((array) ($job['items'] ?? []) as $item) {
            $items[] = [
                'source' => (string) ($item['source'] ?? ''), 'artifact' => str_replace('\\', '/', (string) ($item['artifact'] ?? '')),
                'artifact_sha256' => (string) ($item['artifact_sha256'] ?? ''), 'target' => (string) ($item['target'] ?? ''),
                'status' => in_array($item['status'] ?? null, RestoreIntegrationState::STATES, true) ? $item['status'] : 'queued',
                'started_at' => is_string($item['started_at'] ?? null) ? $item['started_at'] : null,
                'finished_at' => is_string($item['finished_at'] ?? null) ? $item['finished_at'] : null,
                'result_code' => is_string($item['result_code'] ?? null) ? $item['result_code'] : null,
                'comparison' => is_array($item['comparison'] ?? null) ? $item['comparison'] : null,
                'cleanup' => is_array($item['cleanup'] ?? null) ? $item['cleanup'] : null,
            ];
        }
        return [
            'id' => (string) ($job['id'] ?? ''), 'run_id' => (string) ($job['run_id'] ?? ''),
            'status' => in_array($job['status'] ?? null, RestoreIntegrationState::STATES, true) ? $job['status'] : 'queued',
            'revision' => max(1, (int) ($job['revision'] ?? 1)), 'created_at' => (string) ($job['created_at'] ?? ''),
            'updated_at' => (string) ($job['updated_at'] ?? ''),
            'execution_claimed_at' => is_string($job['execution_claimed_at'] ?? null) ? $job['execution_claimed_at'] : null,
            'finished_at' => is_string($job['finished_at'] ?? null) ? $job['finished_at'] : null, 'items' => $items,
        ];
    }
}
