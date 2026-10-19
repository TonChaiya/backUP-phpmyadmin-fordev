<?php

declare(strict_types=1);

namespace BackupApp\Backup;

interface DumpEngine
{
    /** The caller reserves/owns an empty temporarySql using exclusive creation.
     * @param array<string,mixed> $connection @return array<string,mixed> */
    public function dump(string $database, array $connection, string $temporarySql): array;
}
