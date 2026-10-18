<?php

declare(strict_types=1);

namespace BackupApp\Backup;

interface BackupOperation
{
    public function start(): void;

    /** @return array{database: string, filename: string, relative_path: string, bytes: int, sha256: string, manifest_path: string} */
    public function backup(string $database): array;

    public function finish(): void;
}
