<?php

declare(strict_types=1);

namespace BackupApp\Restore;

use RuntimeException;

final class RestoreJobStore
{
    private const STATES = ['queued', 'running', 'blocked', 'failed', 'interrupted'];

    public function __construct(private string $directory)
    {
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create restore job storage');
        }
    }

    public function create(string $relativePath, string $target): array
    {
        if ($relativePath === '' || $target === '') {
            throw new RuntimeException('Restore preflight requires artifact and target');
        }

        do {
            $id = bin2hex(random_bytes(16));
            $path = $this->pathFor($id);
            $handle = @fopen($path, 'xb');
        } while ($handle === false && is_file($path));

        if ($handle === false) {
            throw new RuntimeException('Cannot create restore job');
        }

        $now = date('c');
        $job = $this->sanitize([
            'id' => $id,
            'status' => 'queued',
            'created_at' => $now,
            'updated_at' => $now,
            'preflight_claimed_at' => null,
            'finished_at' => null,
            'revision' => 1,
            'artifact' => str_replace('\\', '/', $relativePath),
            'target' => $target,
            'result' => null,
        ]);

        try {
            if (!flock($handle, LOCK_EX) || !$this->append($handle, $job)) {
                throw new RuntimeException('Cannot persist restore job');
            }
            @chmod($path, 0640);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        return $job;
    }

    public function read(string $id): array
    {
        $handle = @fopen($this->pathFor($id), 'rb');
        if ($handle === false) {
            throw new RuntimeException('Restore job not found');
        }
        try {
            if (!flock($handle, LOCK_SH)) {
                throw new RuntimeException('Cannot lock restore job');
            }
            $job = $this->last($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
        if ($job === null || ($job['id'] ?? null) !== $id) {
            throw new RuntimeException('Restore job is invalid');
        }
        return $job;
    }

    /** @param callable(array): array $mutator */
    public function update(string $id, callable $mutator): array
    {
        $handle = @fopen($this->pathFor($id), 'r+b');
        if ($handle === false) {
            throw new RuntimeException('Restore job not found');
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Cannot lock restore job');
            }
            $current = $this->last($handle);
            if ($current === null) {
                throw new RuntimeException('Restore job is invalid');
            }
            $next = $mutator($current);
            if (!in_array($next['status'] ?? null, self::STATES, true)) {
                throw new RuntimeException('Invalid restore job state');
            }
            $allowed = [
                'queued' => ['queued', 'running', 'interrupted'],
                'running' => ['running', 'blocked', 'failed', 'interrupted'],
                'blocked' => ['blocked'],
                'failed' => ['failed'],
                'interrupted' => ['interrupted'],
            ];
            if (!in_array($next['status'], $allowed[$current['status']] ?? [], true)) {
                throw new RuntimeException('Invalid restore job transition');
            }
            $next['id'] = $id;
            $next['created_at'] = $current['created_at'];
            $next['updated_at'] = date('c');
            $next['revision'] = ((int) $current['revision']) + 1;
            $next = $this->sanitize($next);
            if (!$this->append($handle, $next)) {
                throw new RuntimeException('Cannot persist restore job');
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
        return $next;
    }

    public function pathFor(string $id): string
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1) {
            throw new RuntimeException('Invalid restore job identifier');
        }
        return $this->directory . DIRECTORY_SEPARATOR . $id . '.jsonl';
    }

    public function exists(string $id): bool
    {
        return is_file($this->pathFor($id));
    }

    /** @param resource $handle */
    private function append($handle, array $job): bool
    {
        $encoded = json_encode($job, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false || fseek($handle, 0, SEEK_END) !== 0) {
            return false;
        }
        $size = ftell($handle);
        if ($size === false) {
            return false;
        }
        if ($size > 0) {
            if (fseek($handle, -1, SEEK_END) !== 0) {
                return false;
            }
            $lastByte = fread($handle, 1);
            if ($lastByte !== "\n" && fwrite($handle, "\n") !== 1) {
                return false;
            }
        }
        $line = $encoded . "\n";
        $written = fwrite($handle, $line);
        return $written === strlen($line) && fflush($handle);
    }

    /** @param resource $handle */
    private function last($handle): ?array
    {
        rewind($handle);
        $last = null;
        while (($line = fgets($handle)) !== false) {
            $decoded = json_decode(trim($line), true);
            if (is_array($decoded)) {
                $last = $this->sanitize($decoded);
            }
        }
        return $last;
    }

    private function sanitize(array $job): array
    {
        $status = in_array($job['status'] ?? null, self::STATES, true) ? $job['status'] : 'queued';
        $result = is_array($job['result'] ?? null) ? [
            'code' => (string) ($job['result']['code'] ?? ''),
            'message' => (string) ($job['result']['message'] ?? ''),
            'source_database' => is_string($job['result']['source_database'] ?? null) ? $job['result']['source_database'] : null,
            'integrity_verified' => ($job['result']['integrity_verified'] ?? false) === true,
            'structure_supported' => ($job['result']['structure_supported'] ?? false) === true,
            'checks' => is_array($job['result']['checks'] ?? null) ? $job['result']['checks'] : [],
            'findings' => array_values(array_filter(
                is_array($job['result']['findings'] ?? null) ? $job['result']['findings'] : [],
                'is_string'
            )),
        ] : null;

        return [
            'id' => (string) ($job['id'] ?? ''),
            'status' => $status,
            'created_at' => (string) ($job['created_at'] ?? ''),
            'updated_at' => (string) ($job['updated_at'] ?? ''),
            'preflight_claimed_at' => is_string($job['preflight_claimed_at'] ?? null) ? $job['preflight_claimed_at'] : null,
            'finished_at' => is_string($job['finished_at'] ?? null) ? $job['finished_at'] : null,
            'revision' => max(1, (int) ($job['revision'] ?? 1)),
            'artifact' => str_replace('\\', '/', (string) ($job['artifact'] ?? '')),
            'target' => (string) ($job['target'] ?? ''),
            'result' => $result,
        ];
    }
}
