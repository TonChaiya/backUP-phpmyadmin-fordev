<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

$filter = $argv[1] ?? '';

foreach (glob(__DIR__ . DIRECTORY_SEPARATOR . '*Test.php') ?: [] as $testFile) {
    require $testFile;
}

$passed = 0;
$failed = 0;
$skipped = 0;
$selected = 0;

foreach ($tests as $case) {
    if ($filter !== '' && !str_contains($case['file'] . ' ' . $case['name'], $filter)) {
        continue;
    }

    $selected++;
    $_SESSION = [];

    try {
        ($case['callback'])();
        $passed++;
        echo "PASS {$case['name']}\n";
    } catch (SkippedTest $exception) {
        $skipped++;
        echo "SKIP {$case['name']}\n  {$exception->getMessage()}\n";
    } catch (Throwable $exception) {
        $failed++;
        echo "FAIL {$case['name']}\n  {$exception->getMessage()}\n";
    }
}

echo "\nTests: {$selected}, Passed: {$passed}, Failed: {$failed}, Skipped: {$skipped}\n";
exit($failed === 0 && $selected > 0 ? 0 : 1);

