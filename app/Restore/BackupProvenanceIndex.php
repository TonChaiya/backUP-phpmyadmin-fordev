<?php

declare(strict_types=1);

namespace BackupApp\Restore;

final class BackupProvenanceIndex
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $artifacts = null;

    public function __construct(private string $jobDirectory)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(string $relativePath): ?array
    {
        $this->load();
        $key = str_replace('\\', '/', $relativePath);

        return $this->artifacts[$key] ?? null;
    }

    private function load(): void
    {
        if ($this->artifacts !== null) {
            return;
        }

        $this->artifacts = [];

        foreach (glob(rtrim($this->jobDirectory, '\\/') . DIRECTORY_SEPARATOR . '*.jsonl') ?: [] as $path) {
            $snapshot = $this->lastSnapshot($path);

            if (!is_array($snapshot)) {
                continue;
            }

            foreach (is_array($snapshot['databases'] ?? null) ? $snapshot['databases'] : [] as $database) {
                if (!is_array($database) || ($database['status'] ?? null) !== 'succeeded') {
                    continue;
                }

                $artifact = is_array($database['artifact'] ?? null) ? $database['artifact'] : null;
                $relative = is_array($artifact) ? ($artifact['relative_path'] ?? null) : null;

                if (!is_string($relative) || $relative === '') {
                    continue;
                }

                $relative = str_replace('\\', '/', $relative);
                $this->artifacts[$relative] = [
                    'database' => (string) ($database['name'] ?? ''),
                    'relative_path' => $relative,
                    'bytes' => max(0, (int) ($artifact['bytes'] ?? 0)),
                    'sha256' => strtolower((string) ($artifact['sha256'] ?? '')),
                    'job_id' => (string) ($snapshot['id'] ?? ''),
                ];
            }
        }
    }

    /** @return array<string, mixed>|null */
    private function lastSnapshot(string $path): ?array
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        $last = null;

        try {
            if (!flock($handle, LOCK_SH)) {
                return null;
            }

            while (($line = fgets($handle)) !== false) {
                $decoded = json_decode(trim($line), true);

                if (is_array($decoded)) {
                    $last = $decoded;
                }
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        return $last;
    }
}
