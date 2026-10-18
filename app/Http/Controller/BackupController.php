<?php

declare(strict_types=1);

namespace BackupApp\Http\Controller;

use BackupApp\Backup\BackupService;
use BackupApp\Backup\BackupJobAccess;
use BackupApp\Database\ConnectionFactory;
use BackupApp\Database\DatabaseRepository;
use BackupApp\Support\Flash;
use RuntimeException;

final class BackupController
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private ConnectionFactory $factory,
        private DatabaseRepository $repository,
        private BackupService $backupService,
        private array $config
    ) {
    }

    public function connect(string $host, int $port, string $username, string $password): void
    {
        $connection = $this->factory->make($host, $port, $username, $password);

        try {
            $databases = $this->repository->listDatabases($connection, $this->config['excluded_databases']);
        } finally {
            $connection->close();
        }

        $_SESSION['connection'] = compact('host', 'port', 'username', 'password');
        $_SESSION['databases'] = $databases;
        (new BackupJobAccess())->rotateConnectionContext();
        Flash::add('success', 'เชื่อมต่อสำเร็จ พบฐานข้อมูลที่สำรองได้ ' . count($databases) . ' ฐาน');
    }

    /**
     * @param array<int, string> $selected
     */
    public function backup(array $selected, bool $all): void
    {
        $connectionData = $_SESSION['connection'] ?? null;

        if (!is_array($connectionData)) {
            throw new RuntimeException('Database connection is not available');
        }

        $connection = $this->factory->make(
            (string) ($connectionData['host'] ?? ''),
            (int) ($connectionData['port'] ?? 3306),
            (string) ($connectionData['username'] ?? ''),
            (string) ($connectionData['password'] ?? '')
        );

        try {
            $knownDatabases = $this->repository->listDatabases($connection, $this->config['excluded_databases']);
            $_SESSION['databases'] = $knownDatabases;
            $selected = $all ? $knownDatabases : array_values(array_intersect($selected, $knownDatabases));

            if ($selected === []) {
                Flash::add('warning', 'กรุณาเลือกฐานข้อมูลอย่างน้อย 1 ฐาน');
                return;
            }

            foreach ($selected as $database) {
                $result = $this->backupService->backupDatabase($connection, $database);
                Flash::add('success', 'สำรอง `' . $result['database'] . '` สำเร็จ: ' . $result['relative_path']);
            }
        } finally {
            $connection->close();
        }
    }

    /** @return array{host: string, port: int, username: string, password: string} */
    public function connectionData(): array
    {
        $data = $_SESSION['connection'] ?? [];

        return [
            'host' => (string) ($data['host'] ?? '127.0.0.1'),
            'port' => (int) ($data['port'] ?? 3306),
            'username' => (string) ($data['username'] ?? 'root'),
            'password' => (string) ($data['password'] ?? ''),
        ];
    }

    /** @return array<int, string> */
    public function databases(): array
    {
        return is_array($_SESSION['databases'] ?? null) ? array_values($_SESSION['databases']) : [];
    }
}

