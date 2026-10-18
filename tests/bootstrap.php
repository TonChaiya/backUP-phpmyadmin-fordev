<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'BackupApp\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

/** @var array<int, array{name: string, callback: Closure, file: string}> $tests */
$tests = [];

final class SkippedTest extends RuntimeException
{
}

function test(string $name, Closure $callback): void
{
    global $tests;

    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
    $tests[] = [
        'name' => $name,
        'callback' => $callback,
        'file' => basename((string) ($trace[0]['file'] ?? 'unknown')),
    ];
}

function assertTrueValue(bool $condition, string $message = 'Expected true'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertFalseValue(bool $condition, string $message = 'Expected false'): void
{
    assertTrueValue(!$condition, $message);
}

function assertSameValue(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $detail = $message !== '' ? $message : 'Values are not identical';
        throw new RuntimeException($detail . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

function assertContainsValue(string $needle, string $haystack, string $message = ''): void
{
    assertTrueValue(str_contains($haystack, $needle), $message !== '' ? $message : "Missing {$needle}");
}

function assertNotContainsValue(string $needle, string $haystack, string $message = ''): void
{
    assertFalseValue(str_contains($haystack, $needle), $message !== '' ? $message : "Unexpected {$needle}");
}

function skipTest(string $reason): never
{
    throw new SkippedTest($reason);
}

