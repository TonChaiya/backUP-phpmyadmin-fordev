<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Sql;

use DomainException;

final class CreateTableEvidenceParser
{
    /** @return array{table: string, definition: array{engine: string, columns: list<array{name: string, type: string, nullable: bool, attributes: string}>, constraints: list<string>, options: string}} */
    public function parse(string $sql): array
    {
        if (preg_match('/^CREATE\s+TABLE\s+`((?:``|[^`])+)`\s*\(/i', $sql, $match, PREG_OFFSET_CAPTURE) !== 1) {
            throw new DomainException('unsupported_create_table_shape');
        }
        $table = str_replace('``', '`', $match[1][0]);
        $open = strpos($sql, '(', $match[0][1]);
        if ($open === false) { throw new DomainException('create_table_columns_unproven'); }
        $close = $this->matchingParenthesis($sql, $open);
        if ($close === null) { throw new DomainException('create_table_columns_unproven'); }
        $options = substr($sql, $close + 1);
        if (preg_match('/\bENGINE(?:\s*=\s*|\s+)([A-Za-z0-9_]+)/i', $options, $engineMatch) !== 1) {
            throw new DomainException('create_table_engine_unproven');
        }
        $columns = []; $constraints = [];
        foreach ($this->splitTopLevel(substr($sql, $open + 1, $close - $open - 1)) as $definition) {
            $definition = trim($definition);
            if (!str_starts_with($definition, '`')) {
                $constraints[] = $this->normalize($definition);
                continue;
            }
            if (preg_match('/^`((?:``|[^`])+)`\s+([A-Za-z]+(?:\s*\((?:[^\'"()]|\'(?:\\.|[^\'])*\'|"(?:\\.|[^"])*")*\))?)([\s\S]*)$/i', $definition, $columnMatch) !== 1) {
                throw new DomainException('create_table_column_unproven');
            }
            $type = $this->normalize($columnMatch[2]);
            $tail = $columnMatch[3];
            if (preg_match('/^\s+(unsigned|zerofill)\b/i', $tail, $modifier) === 1) {
                $type .= ' ' . strtolower($modifier[1]);
            }
            $columns[] = [
                'name' => str_replace('``', '`', $columnMatch[1]),
                'type' => $type,
                'nullable' => preg_match('/\bNOT\s+NULL\b/i', $tail) !== 1,
                'attributes' => $this->normalize($tail),
            ];
        }
        if ($columns === []) { throw new DomainException('create_table_columns_unproven'); }
        sort($constraints, SORT_STRING);
        return ['table' => $table, 'definition' => [
            'engine' => strtoupper($engineMatch[1]), 'columns' => $columns,
            'constraints' => $constraints, 'options' => $this->normalize($options),
        ]];
    }

    private function matchingParenthesis(string $sql, int $open): ?int
    {
        $depth = 0; $quote = null; $escaped = false; $length = strlen($sql);
        for ($i = $open; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                if ($escaped) { $escaped = false; continue; }
                if ($char === '\\' && $quote !== '`') { $escaped = true; continue; }
                if ($char === $quote) { if (($sql[$i + 1] ?? '') === $quote) { $i++; } else { $quote = null; } }
                continue;
            }
            if (in_array($char, ["'", '"', '`'], true)) { $quote = $char; continue; }
            if ($char === '(') { $depth++; }
            elseif ($char === ')' && --$depth === 0) { return $i; }
        }
        return null;
    }

    /** @return list<string> */
    private function splitTopLevel(string $body): array
    {
        $parts = []; $start = 0; $depth = 0; $quote = null; $escaped = false; $length = strlen($body);
        for ($i = 0; $i < $length; $i++) {
            $char = $body[$i];
            if ($quote !== null) {
                if ($escaped) { $escaped = false; continue; }
                if ($char === '\\' && $quote !== '`') { $escaped = true; continue; }
                if ($char === $quote) { if (($body[$i + 1] ?? '') === $quote) { $i++; } else { $quote = null; } }
                continue;
            }
            if (in_array($char, ["'", '"', '`'], true)) { $quote = $char; continue; }
            if ($char === '(') { $depth++; continue; }
            if ($char === ')') { $depth--; continue; }
            if ($char === ',' && $depth === 0) { $parts[] = substr($body, $start, $i - $start); $start = $i + 1; }
        }
        $parts[] = substr($body, $start);
        return $parts;
    }

    private function normalize(string $sql): string
    {
        $normalized = ''; $pendingSpace = false; $quote = null; $escaped = false; $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                $normalized .= $char;
                if ($escaped) { $escaped = false; continue; }
                if ($char === '\\' && $quote !== '`') { $escaped = true; continue; }
                if ($char === $quote) {
                    if (($sql[$i + 1] ?? '') === $quote) { $normalized .= $sql[++$i]; }
                    else { $quote = null; }
                }
                continue;
            }
            if (in_array($char, ["'", '"', '`'], true)) {
                if ($pendingSpace && $normalized !== '') { $normalized .= ' '; }
                $pendingSpace = false; $quote = $char; $normalized .= $char; continue;
            }
            if (ctype_space($char)) { $pendingSpace = true; continue; }
            if ($pendingSpace && $normalized !== '') { $normalized .= ' '; }
            $pendingSpace = false; $normalized .= strtolower($char);
        }
        if ($quote !== null || $escaped) { throw new DomainException('schema_evidence_unterminated_literal'); }
        return trim($normalized);
    }

    public function canonicalizeFragment(string $sql): string { return $this->normalize($sql); }
}
