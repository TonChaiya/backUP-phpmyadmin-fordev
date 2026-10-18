<?php

declare(strict_types=1);

use BackupApp\Deployment\SourcePackageBuilder;

$projectRoot = dirname(__DIR__, 2);
$appRoot = $projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR;
spl_autoload_register(static function (string $class) use ($appRoot): void {
    $prefix = 'BackupApp\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = $appRoot . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

$builder = new SourcePackageBuilder();

try {
    $result = $builder->build(
        $projectRoot,
        $projectRoot . DIRECTORY_SEPARATOR . 'deployment' . DIRECTORY_SEPARATOR . 'dedicated-apache'
            . DIRECTORY_SEPARATOR . 'source-allowlist.txt',
        $projectRoot . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'dedicated-apache'
            . DIRECTORY_SEPARATOR . 'packages',
        $projectRoot . DIRECTORY_SEPARATOR . 'deployment' . DIRECTORY_SEPARATOR . 'dedicated-apache'
            . DIRECTORY_SEPARATOR . 'source-manifest.json',
        '2026.10.18.1',
    );
    echo json_encode(['ok' => true] + $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    exit(0);
} catch (Throwable $exception) {
    echo json_encode(
        ['ok' => false, 'error' => preg_match('/^[a-z0-9_]+$/D', $exception->getMessage()) === 1
            ? $exception->getMessage() : 'package_build_failed'],
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ), "\n";
    exit(9);
}
