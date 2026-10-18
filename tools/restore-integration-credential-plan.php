<?php

declare(strict_types=1);

use BackupApp\Restore\Integration\Policy\RestoreIntegrationPolicy;
use BackupApp\Restore\Integration\Policy\TrustedRunId;
use BackupApp\Restore\Integration\Provisioning\CredentialProvisioningPlan;

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

$usage = "Usage: php tools/restore-integration-credential-plan.php --run-id=<32-lower-hex> [--pretty]\n";
$runIdValue = null;
$pretty = false;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--pretty' && !$pretty) {
        $pretty = true;
        continue;
    }
    if (str_starts_with($argument, '--run-id=') && $runIdValue === null) {
        $runIdValue = substr($argument, strlen('--run-id='));
        continue;
    }
    fwrite(STDERR, $usage);
    exit(2);
}

if (!is_string($runIdValue)) {
    fwrite(STDERR, $usage);
    exit(2);
}

try {
    $plan = new CredentialProvisioningPlan(
        new RestoreIntegrationPolicy(),
        new TrustedRunId($runIdValue)
    );
} catch (Throwable) {
    fwrite(STDERR, $usage);
    exit(2);
}

$flags = JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
if ($pretty) {
    $flags |= JSON_PRETTY_PRINT;
}
fwrite(STDOUT, json_encode($plan->publicView(), $flags) . PHP_EOL);
