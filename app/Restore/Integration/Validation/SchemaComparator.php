<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Validation;

final class SchemaComparator
{
    /** @param array<string, mixed> $expected @param array<string, mixed> $actual @return array{equal: bool, expected_digest: string, actual_digest: string, findings: list<string>} */
    public function compare(array $expected, array $actual): array
    {
        $left = $this->canonical($expected); $right = $this->canonical($actual);
        $equal = $left === $right;
        return [
            'equal' => $equal, 'expected_digest' => hash('sha256', $left), 'actual_digest' => hash('sha256', $right),
            'findings' => $equal ? [] : ['schema_mismatch'],
        ];
    }
    private function canonical(array $value): string
    {
        $normalize = function (mixed $item) use (&$normalize): mixed {
            if (!is_array($item)) { return $item; }
            if (!array_is_list($item)) { ksort($item); }
            foreach ($item as $key => $child) { $item[$key] = $normalize($child); }
            return $item;
        };
        return json_encode($normalize($value), JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }
}
