<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Sql;

use BackupApp\Restore\Integration\Validation\TypedSqlValue;
use DomainException;

final class InsertRowsParser
{
    public const MAX_ROWS_PER_STATEMENT = 10000;

    public function parse(string $sql): ParsedInsertRows
    {
        $identifier = '`(?:``|[^`])+`';
        if (preg_match('/\AINSERT\s+INTO\s+(' . $identifier . ')\s*\(([^)]*)\)\s+VALUES\s+/i', $sql, $match) !== 1) {
            throw new DomainException('unsupported_insert_shape');
        }
        $table = $this->identifier($match[1]);
        $columnText = $match[2];
        if (preg_match('/^\s*' . $identifier . '(?:\s*,\s*' . $identifier . ')*\s*$/D', $columnText) !== 1) {
            throw new DomainException('unsupported_insert_columns');
        }
        preg_match_all('/`((?:``|[^`])+)`/', $columnText, $columnMatches);
        $columns = array_map(static fn (string $name): string => str_replace('``', '`', $name), $columnMatches[1]);
        if ($columns === [] || count(array_unique($columns)) !== count($columns)) {
            throw new DomainException('invalid_insert_columns');
        }

        $offset = strlen($match[0]);
        $length = strlen($sql);
        $rows = [];
        while (true) {
            $this->skipWhitespace($sql, $offset);
            if ($offset >= $length || $sql[$offset] !== '(') { throw new DomainException('invalid_insert_tuple'); }
            $offset++;
            $row = [];
            while (true) {
                $this->skipWhitespace($sql, $offset);
                $row[] = $this->literal($sql, $offset);
                $this->skipWhitespace($sql, $offset);
                if ($offset >= $length) { throw new DomainException('truncated_insert_tuple'); }
                if ($sql[$offset] === ',') { $offset++; continue; }
                if ($sql[$offset] === ')') { $offset++; break; }
                throw new DomainException('invalid_insert_value_separator');
            }
            if (count($row) !== count($columns)) { throw new DomainException('insert_column_count_mismatch'); }
            $rows[] = $row;
            if (count($rows) > self::MAX_ROWS_PER_STATEMENT) { throw new DomainException('insert_row_limit_exceeded'); }
            $this->skipWhitespace($sql, $offset);
            if ($offset === $length) { break; }
            if ($sql[$offset] !== ',') { throw new DomainException('unsupported_insert_suffix'); }
            $offset++;
        }
        return new ParsedInsertRows($table, $columns, $rows);
    }

    private function literal(string $sql, int &$offset): TypedSqlValue
    {
        $remaining = substr($sql, $offset);
        if (preg_match('/^NULL\b/i', $remaining, $match) === 1) {
            $offset += strlen($match[0]);
            return TypedSqlValue::null();
        }
        if (($sql[$offset] ?? '') === "'") {
            $offset++;
            $decoded = '';
            $length = strlen($sql);
            while ($offset < $length) {
                $char = $sql[$offset++];
                if ($char === "'") {
                    if (($sql[$offset] ?? '') === "'") { $decoded .= "'"; $offset++; continue; }
                    return TypedSqlValue::bytes($decoded);
                }
                if ($char !== '\\') { $decoded .= $char; continue; }
                if ($offset >= $length) { throw new DomainException('truncated_string_escape'); }
                $escape = $sql[$offset++];
                $decoded .= match ($escape) {
                    '0' => "\0", 'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'Z' => "\x1a",
                    '\\' => '\\', "'" => "'", '"' => '"', '%' => '\\%', '_' => '\\_',
                    default => $escape,
                };
            }
            throw new DomainException('unterminated_insert_string');
        }
        if (preg_match('/^[+-]?(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)/D', $remaining, $match) === 1) {
            $offset += strlen($match[0]);
            return TypedSqlValue::number($match[0]);
        }
        throw new DomainException('unsupported_insert_literal');
    }

    private function skipWhitespace(string $sql, int &$offset): void
    {
        $length = strlen($sql);
        while ($offset < $length && ctype_space($sql[$offset])) { $offset++; }
    }

    private function identifier(string $quoted): string
    {
        return str_replace('``', '`', substr($quoted, 1, -1));
    }
}
