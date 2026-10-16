<?php

return [
    'app_name' => 'PHP MySQL Backup Manager',
    'backup_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups',
    'max_backup_files' => 200,
    'connect_timeout_seconds' => 5,
    'excluded_databases' => [
        'information_schema',
        'performance_schema',
        'mysql',
        'sys',
    ],
];
