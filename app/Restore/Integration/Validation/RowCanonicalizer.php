<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Validation;

final class RowCanonicalizer
{
    /** @param array<string, mixed> $row @param array<string, string> $types */
    public function canonicalize(array $row, array $types): string
    {
        $encoded = '';
        foreach ($types as $column => $type) {
            if (!array_key_exists($column, $row)) { throw new \DomainException('row_column_missing'); }
            $value = $row[$column];
            if ($value === null) { $part = TypedSqlValue::null()->canonical(); }
            elseif (!is_string($value) && !is_int($value) && !is_float($value)) { throw new \DomainException('unsupported_row_value'); }
            else { $part = TypedSqlValue::bytes((string) $value)->canonical(); }
            $encoded .= strlen($column) . ':' . $column . strlen($part) . ':' . $part;
        }
        return $encoded;
    }

    /** @param list<TypedSqlValue> $values @param list<string> $columns */
    public function canonicalizeTyped(array $values, array $columns): string
    {
        if (count($values) !== count($columns)) { throw new \DomainException('typed_row_column_count_mismatch'); }
        $encoded = '';
        foreach ($columns as $index => $column) {
            $part = $values[$index]->canonical();
            $encoded .= strlen($column) . ':' . $column . strlen($part) . ':' . $part;
        }
        return $encoded;
    }

    /** @param array<string, mixed> $row @param list<string> $columns @param array<string, string> $types */
    public function canonicalizeColumns(array $row, array $columns, array $types = []): string
    {
        $orderedTypes = [];
        foreach ($columns as $column) { $orderedTypes[$column] = $types[$column] ?? 'raw-bytes'; }
        return $this->canonicalize($row, $orderedTypes);
    }
}
