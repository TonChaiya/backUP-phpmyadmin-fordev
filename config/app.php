<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$defaultRuntimeDataRoot = $projectRoot . DIRECTORY_SEPARATOR . 'storage';

return [
    'app_name' => 'PHP MySQL Backup Manager',
    'runtime_data_mode' => 'development',
    'runtime_data_root' => $defaultRuntimeDataRoot,
    'max_backup_files' => 200,
    'connect_timeout_seconds' => 5,
    'auth' => [
        'username' => 'admin',
        'password_hash' => '',
    ],
    'excluded_databases' => [
        'information_schema',
        'performance_schema',
        'mysql',
        'sys',
    ],
];
