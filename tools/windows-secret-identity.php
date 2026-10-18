<?php
declare(strict_types=1);

use BackupApp\Restore\Integration\Secrets\NativeDpapiHelper;
use BackupApp\Restore\Integration\Secrets\ProcOpenWorkerProcessRunner;
use BackupApp\Restore\Integration\Secrets\SecretProviderException;

spl_autoload_register(static function (string $class): void {
    $prefix = 'BackupApp\\';
    if (!str_starts_with($class, $prefix)) { return; }
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) { require $path; }
});

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$arguments = array_slice($argv, 1);
if ($arguments !== [] && $arguments !== ['--pretty']) {
    fwrite(STDERR, "identity_command_invalid\n");
    exit(2);
}

try {
    $identity = (new NativeDpapiHelper(new ProcOpenWorkerProcessRunner()))->currentIdentity();
    echo json_encode(
        ['status' => 'ok', 'account' => $identity->account(), 'sid' => $identity->sid(), 'contains_secret' => false],
        ($arguments === ['--pretty'] ? JSON_PRETTY_PRINT : 0) | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ), PHP_EOL;
} catch (SecretProviderException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
