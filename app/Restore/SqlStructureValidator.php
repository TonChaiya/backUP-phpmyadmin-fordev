<?php

declare(strict_types=1);

namespace BackupApp\Restore;

interface SqlStructureValidator
{
    /** @return array<string, mixed> */
    public function validateFile(string $path, string $expectedDatabase): array;
}
