<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Validation;

final class RowMultisetComparator
{
    public function __construct(private readonly RowCanonicalizer $canonicalizer) {}
    /** @param iterable<array<string, mixed>> $expected @param iterable<array<string, mixed>> $actual @param array<string, string> $types @return array{equal: bool, expected_count: int, actual_count: int, digest: string} */
    public function compare(iterable $expected, iterable $actual, array $types): array
    {
        $left = $this->multiset($expected, $types); $right = $this->multiset($actual, $types);
        ksort($left); ksort($right);
        return [
            'equal' => $left === $right,
            'expected_count' => array_sum($left), 'actual_count' => array_sum($right),
            'digest' => hash('sha256', json_encode([$left, $right], JSON_THROW_ON_ERROR)),
        ];
    }
    /** @param iterable<array<string, mixed>> $rows @param array<string, string> $types @return array<string, int> */
    private function multiset(iterable $rows, array $types): array
    {
        $set = [];
        foreach ($rows as $row) { $hash = hash('sha256', $this->canonicalizer->canonicalize($row, $types)); $set[$hash] = ($set[$hash] ?? 0) + 1; }
        return $set;
    }
}
