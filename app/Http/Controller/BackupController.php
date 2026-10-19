<?php

declare(strict_types=1);

namespace BackupApp\Http\Controller;

use BackupApp\Backup\BackupJobAccess;
use BackupApp\Backup\MysqldumpCommand;
use BackupApp\Database\ConnectionFactory;
use BackupApp\Database\DatabaseRepository;
use BackupApp\Support\Flash;

final class BackupController
{
    public function __construct(
        private ConnectionFactory $factory,
        private DatabaseRepository $repository
    ) {
    }

    public function connect(string $host, int $port, string $username, string $password): void
    {
        MysqldumpCommand::assertConnection(compact('host', 'port', 'username'));
        $connection = $this->factory->make($host, $port, $username, $password);

        try {
            MysqldumpCommand::verifyConnection($connection);
            $databases = $this->repository->listDatabases($connection);
        } finally {
            $connection->close();
        }

        $_SESSION['connection'] = compact('host', 'port', 'username', 'password');
        $_SESSION['databases'] = $databases;
        (new BackupJobAccess())->rotateConnectionContext();
        Flash::add('success', 'เชื่อมต่อสำเร็จ พบฐานข้อมูลที่สำรองได้ ' . count($databases) . ' ฐาน');
    }

    /** @return array{host: string, port: int, username: string, password: string} */
    public function connectionData(): array
    {
        $data = $_SESSION['connection'] ?? [];

        return [
            'host' => (string) ($data['host'] ?? '127.0.0.1'),
            'port' => (int) ($data['port'] ?? 3306),
            'username' => (string) ($data['username'] ?? 'backup_reader'),
            'password' => (string) ($data['password'] ?? ''),
        ];
    }

    /** @return array<int, string> */
    public function databases(): array
    {
        return is_array($_SESSION['databases'] ?? null) ? array_values($_SESSION['databases']) : [];
    }
}

