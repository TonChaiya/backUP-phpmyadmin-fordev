<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Secrets;

use SensitiveParameter;
use WeakMap;

final class SecretValue
{
    /** @var WeakMap<object, string>|null */
    private static ?WeakMap $values = null;

    public function __construct(#[SensitiveParameter] string $value)
    {
        $length = strlen($value);
        if ($length === 0) { throw new SecretProviderException('secret_input_invalid'); }
        if ($length > 4096) { throw new SecretProviderException('secret_input_too_large'); }
        self::$values ??= new WeakMap();
        self::$values[$this] = $value;
    }

    public function reveal(): string
    {
        $value = self::$values[$this] ?? null;
        if (!is_string($value)) { throw new SecretProviderException('secret_unavailable'); }
        return $value;
    }

    /** @return array{secret: string} */
    public function __debugInfo(): array { return ['secret' => '[REDACTED]']; }

    /** @return never */
    public function __serialize(): array { throw new SecretProviderException('secret_serialization_forbidden'); }

    public function __destruct()
    {
        if (self::$values !== null && isset(self::$values[$this])) {
            $value = self::$values[$this];
            if (function_exists('sodium_memzero')) { sodium_memzero($value); }
            unset(self::$values[$this]);
        }
    }
}
