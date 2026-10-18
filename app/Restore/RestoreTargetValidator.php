<?php

declare(strict_types=1);

namespace BackupApp\Restore;

final class RestoreTargetValidator
{
    /** @param array<int, string> $excluded */
    public function __construct(private array $excluded)
    {
    }

    /** @return array{valid: bool, code: string} */
    public function validate(string $target, string $source): array
    {
        if ($target === '' || strlen($target) > 64 || preg_match('/^[A-Za-z0-9_]+$/D', $target) !== 1) {
            return ['valid' => false, 'code' => 'invalid_target_name'];
        }

        $blocked = array_map('strtolower', array_merge($this->excluded, [$source, 'jhcisdb']));

        if (in_array(strtolower($target), $blocked, true)) {
            return ['valid' => false, 'code' => 'protected_target'];
        }

        return ['valid' => true, 'code' => 'target_name_valid'];
    }
}
