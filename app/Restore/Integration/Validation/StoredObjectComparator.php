<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Validation;

final class StoredObjectComparator
{
    /** @param array<string, string> $expected @param array<string, string> $actual @return array{status: string, findings: list<string>} */
    public function compare(array $expected, array $actual): array
    {
        if ($expected === [] && $actual === []) { return ['status' => 'pass', 'findings' => []]; }
        return ['status' => 'not_verified', 'findings' => ['stored_object_normalization_unsupported']];
    }
}
