<?php

declare(strict_types=1);

namespace BackupApp\Restore\Sql;

use DomainException;
use Generator;
use RuntimeException;

final class SqlStatementLexer
{
    public const MAX_STATEMENT_BYTES = 16777216;

    public function __construct(
        private readonly int $maxStatementBytes = self::MAX_STATEMENT_BYTES,
        private readonly int $fileChunkBytes = 65536
    ) {
        if ($maxStatementBytes < 1 || $fileChunkBytes < 1 || $fileChunkBytes > 1048576) {
            throw new DomainException('invalid_sql_lexer_limits');
        }
    }

    /** @return Generator<int, LexedStatement> */
    public function stream(string $sql): Generator
    {
        $delimiter = ';';
        $buffer = '';
        $quote = null;
        $escaped = false;
        $lineComment = false;
        $blockComment = false;
        $start = 0;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            if (strlen($buffer) > $this->maxStatementBytes) { throw new DomainException('statement_too_large'); }
            if ($quote === null && !$lineComment && !$blockComment && ($i === 0 || $sql[$i - 1] === "\n")) {
                $lineEnd = strpos($sql, "\n", $i);
                $lineEnd = $lineEnd === false ? $length : $lineEnd;
                $line = substr($sql, $i, $lineEnd - $i);
                if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $match) === 1) {
                    if (!$this->onlyCommentsOrWhitespace($buffer)) {
                        throw new DomainException('delimiter_changed_mid_statement');
                    }
                    $buffer = '';
                    $delimiter = $match[1];
                    $i = $lineEnd;
                    $start = $i + 1;
                    continue;
                }
            }

            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($lineComment) {
                $buffer .= $char;
                if ($char === "\n") {
                    $lineComment = false;
                    if ($this->onlyCommentsOrWhitespace($buffer)) {
                        $buffer = '';
                        $start = $i + 1;
                    }
                }
                continue;
            }
            if ($blockComment) {
                $buffer .= $char;
                if ($char === '*' && $next === '/') {
                    $buffer .= '/';
                    $i++;
                    $blockComment = false;
                    if ($this->onlyCommentsOrWhitespace($buffer)) {
                        $buffer = '';
                        $start = $i + 1;
                    }
                }
                continue;
            }
            if ($quote !== null) {
                $buffer .= $char;
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\' && $quote !== '`') {
                    $escaped = true;
                    continue;
                }
                if ($char === $quote) {
                    if ($next === $quote) {
                        $buffer .= $next;
                        $i++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($char === '#' || ($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2])))) {
                $buffer .= $char;
                if ($char === '-') {
                    $buffer .= $next;
                    $i++;
                }
                $lineComment = true;
                continue;
            }
            if ($char === '/' && $next === '*') {
                $buffer .= '/*';
                $i++;
                $blockComment = true;
                continue;
            }
            if (in_array($char, ["'", '"', '`'], true)) {
                $buffer .= $char;
                $quote = $char;
                continue;
            }
            if (substr($sql, $i, strlen($delimiter)) === $delimiter) {
                if (!$this->onlyCommentsOrWhitespace($buffer)) {
                    yield new LexedStatement(trim($buffer), $start, $i + strlen($delimiter), $delimiter);
                }
                $buffer = '';
                $i += strlen($delimiter) - 1;
                $start = $i + 1;
                continue;
            }
            $buffer .= $char;
            if (strlen($buffer) > $this->maxStatementBytes) {
                throw new DomainException('statement_too_large');
            }
        }

        if (strlen($buffer) > $this->maxStatementBytes) { throw new DomainException('statement_too_large'); }
        if ($quote !== null || $blockComment) {
            throw new DomainException('unterminated_sql_lexeme');
        }
        if (!$this->onlyCommentsOrWhitespace($buffer) || $delimiter !== ';') {
            throw new DomainException($delimiter !== ';' ? 'unterminated_delimiter' : 'unterminated_statement');
        }
    }

    /** @return Generator<int, LexedStatement, mixed, array{bytes: int, sha256: string}> */
    public function streamFile(string $path): Generator
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) { throw new RuntimeException('artifact_read_failed'); }
        $reader = new SqlFileByteReader($handle, $this->fileChunkBytes);
        $buffer = '';
        $quote = null;
        $escaped = false;
        $lineComment = false;
        $blockComment = false;
        $start = 0;
        $offset = 0;
        try {
            $current = $reader->read();
            $next = $reader->read();
            while ($current !== null) {
                $char = $current;
                $consumeNext = false;
                if ($lineComment) {
                    $buffer .= $char;
                    if ($char === "\n") {
                        $lineComment = false;
                        if ($this->onlyCommentsOrWhitespace($buffer)) { $buffer = ''; $start = $offset + 1; }
                    }
                } elseif ($blockComment) {
                    $buffer .= $char;
                    if ($char === '*' && $next === '/') {
                        $buffer .= '/';
                        $consumeNext = true;
                        $blockComment = false;
                        if ($this->onlyCommentsOrWhitespace($buffer)) { $buffer = ''; $start = $offset + 2; }
                    }
                } elseif ($quote !== null) {
                    $buffer .= $char;
                    if ($escaped) { $escaped = false; }
                    elseif ($char === '\\' && $quote !== '`') { $escaped = true; }
                    elseif ($char === $quote) {
                        if ($next === $quote) { $buffer .= $next; $consumeNext = true; }
                        else { $quote = null; }
                    }
                } elseif ($char === '#' || ($char === '-' && $next === '-')) {
                    $buffer .= $char;
                    if ($char === '-') { $buffer .= '-'; $consumeNext = true; }
                    $lineComment = true;
                } elseif ($char === '/' && $next === '*') {
                    $buffer .= '/*';
                    $consumeNext = true;
                    $blockComment = true;
                } elseif (in_array($char, ["'", '"', '`'], true)) {
                    $buffer .= $char;
                    $quote = $char;
                } elseif ($char === ';') {
                    if (!$this->onlyCommentsOrWhitespace($buffer)) {
                        yield new LexedStatement(trim($buffer), $start, $offset + 1, ';');
                    }
                    $buffer = '';
                    $start = $offset + 1;
                } else {
                    $buffer .= $char;
                }
                if (strlen($buffer) > $this->maxStatementBytes) { throw new DomainException('statement_too_large'); }
                if ($consumeNext) {
                    $offset += 2;
                    $current = $reader->read();
                    $next = $reader->read();
                } else {
                    $offset++;
                    $current = $next;
                    $next = $reader->read();
                }
            }
            if ($quote !== null || $blockComment) { throw new DomainException('unterminated_sql_lexeme'); }
            if (!$this->onlyCommentsOrWhitespace($buffer)) { throw new DomainException('unterminated_statement'); }
            return $reader->finish();
        } finally {
            fclose($handle);
        }
    }

    private function onlyCommentsOrWhitespace(string $sql): bool
    {
        $masked = preg_replace('/\/\*(?![!]|M!)[\s\S]*?\*\/|--[^\r\n]*|#[^\r\n]*/i', ' ', $sql);
        return trim(is_string($masked) ? $masked : $sql) === '';
    }
}
