<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'BackupApp\\';
    $baseDir = __DIR__ . DIRECTORY_SEPARATOR;

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

use BackupApp\Support\Config;
use BackupApp\Support\RuntimeDataBootstrap;
use BackupApp\Support\Session;

$projectRoot = dirname(__DIR__);
$configDirectory = $projectRoot . DIRECTORY_SEPARATOR . 'config';
$config = Config::load(
    $configDirectory . DIRECTORY_SEPARATOR . 'app.php',
    $configDirectory . DIRECTORY_SEPARATOR . 'local.php'
);
$runtimeDataBootstrap = new RuntimeDataBootstrap();
$config = $runtimeDataBootstrap->prepare($config, $projectRoot);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
$runtimeDataBootstrap->configureSession((string) $config['session_path']);
Session::start(Session::requestIsHttps($_SERVER));

return $config;
