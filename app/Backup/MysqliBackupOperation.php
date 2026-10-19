<?php

declare(strict_types=1);

namespace BackupApp\Backup;

use BackupApp\Database\ConnectionFactory;
use BackupApp\Database\DatabaseRepository;
use BackupApp\Database\AuthorizedSourcePolicy;
use mysqli;
use RuntimeException;

final class MysqliBackupOperation implements BackupOperation
{
    private ?mysqli $connection = null;
    private ?\Closure $connectionCheck;

    /** @var array<int, string> */
    private array $availableDatabases = [];

    /**
     * @param array{host: string, port: int, username: string, password: string} $connectionData
     */
    public function __construct(
        private ConnectionFactory $factory,
        private DatabaseRepository $repository,
        private BackupService $backupService,
        private array $connectionData,
        private array $requestedDatabases,
        ?callable $connectionCheck = null
    ) {
        $this->connectionCheck = $connectionCheck !== null ? \Closure::fromCallable($connectionCheck) : null;
        $policy = new AuthorizedSourcePolicy();

        if ($requestedDatabases === []) {
            throw new RuntimeException('Backup operation requires an authorized source');
        }

        foreach ($requestedDatabases as $database) {
            if (!is_string($database)) {
                throw new RuntimeException('Invalid backup source request');
            }

            $policy->assertAuthorized($database);
        }

        $this->requestedDatabases = array_values(array_unique($requestedDatabases));
        $this->connectionData['selected_databases']=$this->requestedDatabases;
    }

    public function start(): void
    {
        if ($this->connectionCheck !== null) { ($this->connectionCheck)(); }
        MysqldumpCommand::assertConnection($this->connectionData);
        $policy = new AuthorizedSourcePolicy();
        foreach ($this->requestedDatabases as $database) {
            $policy->assertAuthorized($database);
        }

        $this->connection = $this->factory->make(
            $this->connectionData['host'],
            $this->connectionData['port'],
            $this->connectionData['username'],
            $this->connectionData['password']
        );
        MysqldumpCommand::verifyConnection($this->connection, $this->connectionData);
        $this->availableDatabases = $this->repository->listDatabases($this->connection);
        foreach($this->requestedDatabases as $database){
            if(!in_array($database,$this->availableDatabases,true)){throw new RuntimeException('source_not_visible_to_account');}
        }
    }

    public function backup(string $database): array
    {
        (new AuthorizedSourcePolicy())->assertAuthorized($database);

        if ($this->connection === null
            || !in_array($database, $this->requestedDatabases, true)
            || !in_array($database, $this->availableDatabases, true)
        ) {
            throw new RuntimeException('Database is no longer available for backup');
        }

        return $this->backupService->backupDatabase($this->connection, $database, $this->connectionData);
    }

    public function finish(): void
    {
        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }
    }
}
