<?php

declare(strict_types=1);

namespace BackupApp\Backup;

use BackupApp\Database\AuthorizedSourcePolicy;
use RuntimeException;

final class BackupJobStore
{
    private const STATES = ['queued', 'running', 'succeeded', 'failed', 'interrupted'];

    private AuthorizedSourcePolicy $sourcePolicy;

    public function __construct(private string $directory)
    {
        $this->sourcePolicy = new AuthorizedSourcePolicy();
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create job storage');
        }
    }

    /** @param array<int, string> $databases */
    public function create(array $databases): array
    {
        $databases = array_values(array_unique(array_filter(
            $databases,
            static fn (mixed $database): bool => is_string($database) && $database !== ''
        )));

        if ($databases === []) {
            throw new RuntimeException('A backup job requires at least one database');
        }

        foreach ($databases as $database) {
            if (!$this->sourcePolicy->isAuthorized($database)) {
                throw new RuntimeException('Backup source is not authorized');
            }
        }

        do {
            $id = bin2hex(random_bytes(16));
            $path = $this->pathFor($id);
            $handle = @fopen($path, 'xb');
        } while ($handle === false && is_file($path));

        if ($handle === false) {
            throw new RuntimeException('Cannot create backup job');
        }

        $now = date('c');
        $job = $this->sanitize([
            'id' => $id,
            'status' => 'queued',
            'created_at' => $now,
            'updated_at' => $now,
            'execution_claimed_at' => null,
            'revision' => 1,
            'databases' => array_map(static fn (string $database): array => [
                'name' => $database,
                'status' => 'queued',
                'started_at' => null,
                'finished_at' => null,
                'artifact' => null,
                'error' => null,
            ], $databases),
        ]);

        try {
            if (!flock($handle, LOCK_EX) || !$this->appendSnapshot($handle, $job)) {
                throw new RuntimeException('Cannot persist backup job');
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
        $path = $this->pathFor($id);
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Backup job not found');
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                throw new RuntimeException('Cannot lock backup job');
            }

            $job = $this->lastValidSnapshot($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        if ($job === null || ($job['id'] ?? null) !== $id) {
            throw new RuntimeException('Backup job is invalid');
        }

        return $job;
    }

    /** @param callable(array): array $mutator */
    public function update(string $id, callable $mutator): array
    {
        $path = $this->pathFor($id);
        $handle = @fopen($path, 'r+b');

        if ($handle === false) {
            throw new RuntimeException('Backup job not found');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Cannot lock backup job');
            }

            $current = $this->lastValidSnapshot($handle);

            if ($current === null || ($current['id'] ?? null) !== $id) {
                throw new RuntimeException('Backup job is invalid');
            }

            $next = $mutator($current);
            $next['id'] = $id;
            $next['created_at'] = $current['created_at'];
            $next['updated_at'] = date('c');
            $next['revision'] = ((int) ($current['revision'] ?? 0)) + 1;
            $next = $this->sanitize($next);

            if (!$this->appendSnapshot($handle, $next)) {
                throw new RuntimeException('Cannot persist backup job');
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
            throw new RuntimeException('Invalid backup job identifier');
        }

        return $this->directory . DIRECTORY_SEPARATOR . $id . '.jsonl';
    }

    public function exists(string $id): bool
    {
        return is_file($this->pathFor($id));
    }

    /** @param resource $handle */
    private function appendSnapshot($handle, array $job): bool
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
    private function lastValidSnapshot($handle): ?array
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
        $databases = [];

        foreach (is_array($job['databases'] ?? null) ? $job['databases'] : [] as $database) {
            if (!is_array($database) || !is_string($database['name'] ?? null)) {
                continue;
            }

            if (!$this->sourcePolicy->isAuthorized($database['name'])) {
                throw new RuntimeException('Backup job contains an unauthorized source');
            }

            $state = in_array($database['status'] ?? null, self::STATES, true)
                ? $database['status']
                : 'queued';
            $artifact = is_array($database['artifact'] ?? null) ? [
                'filename' => (string) ($database['artifact']['filename'] ?? ''),
                'relative_path' => (string) ($database['artifact']['relative_path'] ?? ''),
                'bytes' => max(0, (int) ($database['artifact']['bytes'] ?? 0)),
                'sha256' => (string) ($database['artifact']['sha256'] ?? ''),
                'manifest_path' => (string) ($database['artifact']['manifest_path'] ?? ''),
            ] : null;
            $error = is_array($database['error'] ?? null) ? [
                'code' => (string) ($database['error']['code'] ?? 'backup_failed'),
                'message' => (string) ($database['error']['message'] ?? 'Backup failed'),
            ] : null;
            $databases[] = [
                'name' => $database['name'],
                'status' => $state,
                'started_at' => is_string($database['started_at'] ?? null) ? $database['started_at'] : null,
                'finished_at' => is_string($database['finished_at'] ?? null) ? $database['finished_at'] : null,
                'artifact' => $artifact,
                'error' => $error,
            ];
        }

        $status = in_array($job['status'] ?? null, self::STATES, true) ? $job['status'] : 'queued';
        $summary = array_fill_keys(self::STATES, 0);

        foreach ($databases as $database) {
            $summary[$database['status']]++;
        }

        $summary['total'] = count($databases);

        return [
            'id' => (string) ($job['id'] ?? ''),
            'status' => $status,
            'created_at' => (string) ($job['created_at'] ?? ''),
            'updated_at' => (string) ($job['updated_at'] ?? ''),
            'execution_claimed_at' => is_string($job['execution_claimed_at'] ?? null) ? $job['execution_claimed_at'] : null,
            'revision' => max(1, (int) ($job['revision'] ?? 1)),
            'databases' => $databases,
            'summary' => $summary,
        ];
    }
}
