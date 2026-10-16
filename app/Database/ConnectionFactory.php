<?php

declare(strict_types=1);

namespace BackupApp\Database;

use mysqli;
use RuntimeException;

final class ConnectionFactory
{
    /**
     * @param array{connect_timeout_seconds: int} $config
     */
    public function __construct(private array $config)
    {
    }

    public function make(string $host, int $port, string $username, string $password): mysqli
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $connection = mysqli_init();
        $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, (int) $this->config['connect_timeout_seconds']);
        $connection->real_connect($host, $username, $password, null, $port);
        $connection->set_charset('utf8mb4');

        return $connection;
    }
}
