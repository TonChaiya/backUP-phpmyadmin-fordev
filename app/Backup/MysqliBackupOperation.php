<?php

declare(strict_types=1);

namespace BackupApp\Backup;

use BackupApp\Database\ConnectionFactory;
use BackupApp\Database\DatabaseRepository;
use mysqli;
use RuntimeException;

final class MysqliBackupOperation implements BackupOperation
{
    private ?mysqli $connection = null;

    /** @var array<int, string> */
    private array $availableDatabases = [];

    /**
     * @param array{host: string, port: int, username: string, password: string} $connectionData
     * @param array<int, string> $excludedDatabases
     */
    public function __construct(
        private ConnectionFactory $factory,
        private DatabaseRepository $repository,
        private BackupService $backupService,
        private array $connectionData,
        private array $excludedDatabases
    ) {
    }

    public function start(): void
    {
        $this->connection = $this->factory->make(
            $this->connectionData['host'],
            $this->connectionData['port'],
            $this->connectionData['username'],
            $this->connectionData['password']
        );
        $this->availableDatabases = $this->repository->listDatabases($this->connection, $this->excludedDatabases);
    }

    public function backup(string $database): array
    {
        if ($this->connection === null || !in_array($database, $this->availableDatabases, true)) {
            throw new RuntimeException('Database is no longer available for backup');
        }

        return $this->backupService->backupDatabase($this->connection, $database);
    }

    public function finish(): void
    {
        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }
    }
}
