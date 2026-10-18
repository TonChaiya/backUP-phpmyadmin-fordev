<?php

declare(strict_types=1);

use BackupApp\Restore\Integration\Policy\RestoreIntegrationPolicy;
use BackupApp\Restore\Integration\Policy\TrustedRunId;
use BackupApp\Restore\Integration\Readiness\RestoreIntegrationReadinessChecker;

spl_autoload_register(static function (string $class): void {
    $prefix = 'BackupApp\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$arguments = array_slice($argv, 1);
if ($arguments !== [] && $arguments !== ['--pretty']) {
    fwrite(STDERR, "Usage: php tools/restore-integration-readiness.php [--pretty]\n");
    exit(2);
}

$runId = new TrustedRunId(bin2hex(random_bytes(16)));
$assessment = (new RestoreIntegrationReadinessChecker(new RestoreIntegrationPolicy()))->planOnly($runId);
$flags = JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
if ($arguments === ['--pretty']) {
    $flags |= JSON_PRETTY_PRINT;
}

fwrite(STDOUT, json_encode($assessment->publicView(), $flags) . PHP_EOL);
