<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Validation;

use DomainException;

final class TypedSqlValue
{
    private function __construct(private readonly string $kind, private readonly string $bytes)
    {
        if (!in_array($kind, ['null', 'bytes', 'number'], true)) {
            throw new DomainException('unsupported_typed_sql_value');
        }
    }

    public static function null(): self { return new self('null', ''); }
    public static function bytes(string $bytes): self { return new self('bytes', $bytes); }
    public static function number(string $lexeme): self
    {
        if (preg_match('/^[+-]?(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)$/D', $lexeme) !== 1) {
            throw new DomainException('unsupported_numeric_literal');
        }
        return new self('number', $lexeme);
    }

    public function canonical(): string
    {
        if ($this->kind === 'null') { return 'N'; }
        return 'V' . strlen($this->bytes) . ':' . $this->bytes;
    }
}
