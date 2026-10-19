<?php

declare(strict_types=1);

use BackupApp\Backup\BackupJobAccess;
use BackupApp\Backup\BackupJobStore;
use BackupApp\Backup\BackupService;
use BackupApp\Backup\MysqliBackupOperation;
use BackupApp\Database\AuthorizedSourcePolicy;
use BackupApp\Database\ConnectionFactory;
use BackupApp\Database\DatabaseRepository;

final class ManualBackupCatalogResult extends mysqli_result
{
    private int $index = 0;

    /** @param list<array<int, string>> $rows */
    public function __construct(private array $rows) {}

    public function fetch_row(): ?array
    {
        return $this->rows[$this->index++] ?? null;
    }
}

final class ManualBackupCatalogConnection extends mysqli
{
    /** @var list<string> */
    public array $queries = [];
    public int $selected = 0;

    /** @param list<array<int, string>> $rows */
    public function __construct(private array $rows = []) {}

    public function query(string $query, int $result_mode = MYSQLI_STORE_RESULT): mysqli_result|bool
    {
        $this->queries[] = $query;
        return new ManualBackupCatalogResult($this->rows);
    }

    public function select_db(string $database): bool
    {
        $this->selected++;
        return true;
    }

    public function real_escape_string(string $string): string
    {
        return addslashes($string);
    }
}

test('Manual backup source policy exposes only the four approved exact names', static function (): void {
    $policy = new AuthorizedSourcePolicy();

    assertSameValue(
        ['autoprintdb', 'drugmap_db', 'drug_stock_check', 'prepack_labels'],
        $policy->authorizedSources()
    );
    assertTrueValue($policy->isAuthorized('autoprintdb'));
    assertFalseValue($policy->isAuthorized('jhcisdb'));
    assertFalseValue($policy->isAuthorized('AUTOPRINTDB'));

    foreach (['jhcisdb', 'mysql', 'unknown', 'autoprintdb '] as $name) {
        $failed = false;
        try {
            $policy->assertAuthorized($name);
        } catch (DomainException) {
            $failed = true;
        }
        assertTrueValue($failed, 'Policy accepted unauthorized source: ' . $name);
    }
});

test('Database listing queries only explicit approved schema names', static function (): void {
    $connection = new ManualBackupCatalogConnection([
        ['prepack_labels'],
        ['jhcisdb'],
        ['autoprintdb'],
    ]);
    $failed = false;

    try {
        (new DatabaseRepository())->listDatabases($connection);
    } catch (DomainException) {
        $failed = true;
    }

    assertTrueValue($failed, 'Unexpected catalog row must fail closed');
    assertSameValue(1, count($connection->queries));
    assertNotContainsValue('SHOW DATABASES', strtoupper($connection->queries[0]));
    foreach ((new AuthorizedSourcePolicy())->authorizedSources() as $name) {
        assertContainsValue("'{$name}'", $connection->queries[0]);
    }
    assertNotContainsValue("'jhcisdb'", strtolower($connection->queries[0]));
});

test('Manual backup selection rejects a forged known database list', static function (): void {
    $access = new BackupJobAccess();
    $failed = false;

    try {
        $access->select(['jhcisdb'], false, ['autoprintdb', 'jhcisdb']);
    } catch (RuntimeException) {
        $failed = true;
    }

    assertTrueValue($failed);
    $failed = false;
    try {
        $access->select(['autoprintdb', 'jhcisdb', 'drugmap_db'], false, ['autoprintdb', 'jhcisdb', 'drugmap_db']);
    } catch (RuntimeException) {
        $failed = true;
    }
    assertTrueValue($failed, 'A mixed authorized/protected request must fail as a whole');
});

test('Backup job storage rejects unauthorized creation and forged persisted names', static function (): void {
    $path = phase2TempDirectory('source-policy-job');

    try {
        $store = new BackupJobStore($path);
        $failed = false;
        try {
            $store->create(['jhcisdb']);
        } catch (RuntimeException) {
            $failed = true;
        }
        assertTrueValue($failed);

        $job = $store->create(['autoprintdb']);
        $forged = $job;
        $forged['revision'] = 2;
        $forged['databases'][0]['name'] = 'jhcisdb';
        file_put_contents(
            $store->pathFor($job['id']),
            json_encode($forged, JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND
        );

        $failed = false;
        try {
            $store->read($job['id']);
        } catch (RuntimeException) {
            $failed = true;
        }
        assertTrueValue($failed, 'Forged persisted database must be rejected');
    } finally {
        phase2RemoveDirectory($path);
    }
});

test('BackupService rejects a protected source before select_db', static function (): void {
    $path = phase2TempDirectory('source-policy-service');

    try {
        $connection = new ManualBackupCatalogConnection();
        $failed = false;
        try {
            (new BackupService($path))->backupDatabase($connection, 'jhcisdb');
        } catch (RuntimeException|DomainException) {
            $failed = true;
        }

        assertTrueValue($failed);
        assertSameValue(0, $connection->selected);
        assertSameValue([], $connection->queries);
    } finally {
        phase2RemoveDirectory($path);
    }
});

test('Mysqli backup operation rejects an unauthorized requested source before start', static function (): void {
    $path = phase2TempDirectory('source-policy-operation');

    try {
        $failed = false;
        try {
            new MysqliBackupOperation(
                new ConnectionFactory(['connect_timeout_seconds' => 1]),
                new DatabaseRepository(),
                new BackupService($path),
                ['host' => '127.0.0.1', 'port' => 3306, 'username' => 'never-used', 'password' => 'never-used'],
                ['jhcisdb']
            );
        } catch (DomainException) {
            $failed = true;
        }

        assertTrueValue($failed);
    } finally {
        phase2RemoveDirectory($path);
    }
});
