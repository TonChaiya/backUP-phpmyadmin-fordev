<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Policy;

use DomainException;

final class TrustedTargetName
{
    public function __construct(private readonly string $value)
    {
        if (preg_match('/^restore_test_(?:autoprintdb|drugmap_db|drug_stock_check|prepack_labels)_[a-f0-9]{32}$/D', $value) !== 1
            || strlen($value) > 64) {
            throw new DomainException('invalid_target_name');
        }
    }
    public function value(): string { return $this->value; }
    public function equals(self $other): bool { return hash_equals($this->value, $other->value); }
}
