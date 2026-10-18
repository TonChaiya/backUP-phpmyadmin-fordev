<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Validation;

final class ValidationReport
{
    /** @param array<string, array<string, mixed>> $areas */
    public function __construct(private readonly array $areas) {}
    /** @return array<string, array<string, mixed>> */ public function areas(): array { return $this->areas; }
    public function mandatoryPassed(): bool
    {
        foreach ($this->areas as $area) { if (($area['mandatory'] ?? true) && ($area['status'] ?? 'not_verified') !== 'pass') { return false; } }
        return true;
    }
    /** @return array{mandatory_passed: bool, areas: array<string, array<string, mixed>>} */
    public function publicView(): array { return ['mandatory_passed' => $this->mandatoryPassed(), 'areas' => $this->areas]; }
}
