<?php

declare(strict_types=1);

use BackupApp\Restore\Integration\Database\OfflineRestoreConnection;
use BackupApp\Restore\Integration\Database\RestoreConnectionFactory;
use BackupApp\Restore\Integration\Database\MysqliRestoreConnection;
use BackupApp\Restore\Integration\Execution\CleanupCoordinator;
use BackupApp\Restore\Integration\Execution\TargetDatabaseProvisioner;
use BackupApp\Restore\Integration\Execution\TargetProvisioningException;
use BackupApp\Restore\Integration\Execution\TypedRestoreExecutor;
use BackupApp\Restore\Integration\Execution\RestoreIntegrationRunner;
use BackupApp\Restore\Integration\Attestation\PrivilegeEvidence;
use BackupApp\Restore\Integration\Attestation\ServerIdentity;
use BackupApp\Restore\Integration\Authorization\LiveAuthorizationReceipt;
use BackupApp\Database\FileDatabaseOperationLock;
use BackupApp\Restore\Integration\Ownership\CleanupDecisionEngine;
use BackupApp\Restore\Integration\Ownership\OwnershipEvent;
use BackupApp\Restore\Integration\Ownership\OwnershipJournal;
use BackupApp\Restore\Integration\Ownership\OwnershipMarker;
use BackupApp\Restore\Integration\Policy\RestoreIntegrationPolicy;
use BackupApp\Restore\Integration\Policy\TrustedRunId;
use BackupApp\Restore\Integration\Policy\TrustedTargetName;
use BackupApp\Restore\Integration\Sql\ExecutableRestoreStatement;
use BackupApp\Restore\Integration\Sql\ValidatedExecutableStatement;
use BackupApp\Restore\Integration\Sql\StatementPayload;
use BackupApp\Restore\Integration\Sql\RestorePlanCompiler;
use BackupApp\Restore\Integration\Sql\RestorePlan;
use BackupApp\Restore\Integration\Sql\RestorePlanItem;
use BackupApp\Restore\Integration\Sql\RestoreStatementKind;
use BackupApp\Restore\Integration\Sql\Statement\DropObject;

final class UnrecognizedExecutableStatement implements ExecutableRestoreStatement, RestorePlanItem
{
    public function renderForTarget(): string { return 'DROP DATABASE `jhcisdb`'; }
    public function kind(): RestoreStatementKind { return RestoreStatementKind::DROP_OBJECT; }
    public function category(): string { return 'malicious'; }
}

final class StatefulStatementPayload implements StatementPayload
{
    private int $reads = 0;
    public function read(): string
    {
        return ++$this->reads === 1 ? 'DROP TABLE IF EXISTS `safe_table`' : 'DROP DATABASE `jhcisdb`';
    }
}

final class FakeRestoreConnection implements OfflineRestoreConnection
{
    public array $events = [];
    public bool $exists = false;
    public ?string $selected = null;
    public ?string $markerHash = null;
    public string $fingerprint;
    public array $schema = [];
    public array $rowsByTable = [];
    public bool $failMarkerCreation = false;
    public ?int $failTargetExistsOnCall = null;
    private int $targetExistsCalls = 0;
    public function __construct() { $this->fingerprint = str_repeat('a', 64); }
    public function serverIdentityFingerprint(): string { return $this->fingerprint; }
    public function targetExists(TrustedTargetName $target): bool { $this->targetExistsCalls++; if ($this->failTargetExistsOnCall === $this->targetExistsCalls) { throw new RuntimeException('fixture_target_exists_failed'); } $this->events[] = ['exists', $target->value()]; return $this->exists; }
    public function createTarget(TrustedTargetName $target, string $charset, string $collation): void { $this->events[] = ['create', $target->value(), $charset, $collation]; $this->exists = true; }
    public function selectTarget(TrustedTargetName $target): void { $this->events[] = ['select', $target->value()]; $this->selected = $target->value(); }
    public function currentDatabase(): ?string { return $this->selected; }
    public function execute(ValidatedExecutableStatement $statement): void { $this->events[] = ['execute', $statement->kind()->value, $statement->sql()]; }
    public function createOwnershipMarker(OwnershipMarker $marker): void { if ($this->failMarkerCreation) { throw new RuntimeException('fixture_marker_failed'); } $this->markerHash = $marker->nonceHash(); $this->events[] = ['marker', $marker->table()]; }
    public function readOwnershipMarkerHash(OwnershipMarker $marker): ?string { return $this->markerHash; }
    public function dropExactTarget(TrustedTargetName $target): void { $this->events[] = ['drop', $target->value()]; $this->exists = false; $this->selected = null; }
    public function schemaSnapshot(): array { return $this->schema; }
    public function rowBatches(string $table, array $columns, int $batchSize): iterable
    {
        $rows = $this->rowsByTable[$table] ?? [];
        foreach (array_chunk($rows, $batchSize) as $batch) { yield $batch; }
    }
    public function close(): void {}
}

function executorPlan(): array
{
    $sql = integrationSql("CREATE TABLE `items` (`id` int NOT NULL) ENGINE=InnoDB;\nINSERT INTO `items` (`id`) VALUES ('1');\nUNLOCK TABLES;");
    return [(new RestorePlanCompiler())->compile($sql, 'autoprintdb'), $sql];
}

test('RestoreIntegrationExecutor executes only typed executable items in order', function (): void {
    [$plan] = executorPlan();
    $connection = new FakeRestoreConnection();
    $target = (new RestoreIntegrationPolicy())->targetFor('autoprintdb', new TrustedRunId('0123456789abcdef0123456789abcdef'));
    $connection->selectTarget($target);
    $result = (new TypedRestoreExecutor())->execute($plan, $target, $connection);
    assertTrueValue($result->succeeded());
    assertSameValue(['session_directive', 'create_table', 'insert_rows', 'unlock_tables'], array_column(array_filter($connection->events, static fn ($e): bool => $e[0] === 'execute'), 1));
    foreach ($connection->events as $event) {
        if ($event[0] === 'execute') {
            assertNotContainsValue('CREATE DATABASE', $event[2]);
            assertFalseValue(str_starts_with(trim($event[2]), 'USE '));
        }
    }
});

test('RestoreIntegrationExecutor rejects interface-compatible raw statement implementations', function (): void {
    $connection = new FakeRestoreConnection();
    $target = (new RestoreIntegrationPolicy())->targetFor('autoprintdb', new TrustedRunId('0123456789abcdef0123456789abcdef'));
    $connection->selectTarget($target);
    foreach ([new UnrecognizedExecutableStatement(), new DropObject('DROP DATABASE `jhcisdb`')] as $payload) {
        $plan = new RestorePlan('autoprintdb', [$payload], 0, hash('sha256', ''), ['tables' => [], 'views' => []]);
        $thrown = false;
        try { (new TypedRestoreExecutor())->execute($plan, $target, $connection); }
        catch (DomainException) { $thrown = true; }
        assertTrueValue($thrown);
    }
    assertSameValue([], array_values(array_filter($connection->events, static fn ($event): bool => $event[0] === 'execute')));
});

test('RestoreIntegrationExecutor freezes a stateful payload before validation and execution', function (): void {
    $connection = new FakeRestoreConnection();
    $target = (new RestoreIntegrationPolicy())->targetFor('autoprintdb', new TrustedRunId('0123456789abcdef0123456789abcdef'));
    $connection->selectTarget($target);
    $statement = new DropObject(new StatefulStatementPayload());
    $plan = new RestorePlan('autoprintdb', [$statement], 0, hash('sha256', ''), ['tables' => [], 'views' => []]);
    (new TypedRestoreExecutor())->execute($plan, $target, $connection);
    $events = array_values(array_filter($connection->events, static fn ($event): bool => $event[0] === 'execute'));
    assertSameValue('DROP TABLE IF EXISTS `safe_table`', $events[0][2]);
    assertNotContainsValue('jhcisdb', $events[0][2]);
});

test('RestoreIntegrationExecutor provisioner requires a new exact target and durable ownership', function (): void {
    $connection = new FakeRestoreConnection();
    $run = new TrustedRunId('0123456789abcdef0123456789abcdef');
    $target = (new RestoreIntegrationPolicy())->targetFor('autoprintdb', $run);
    $journal = new OwnershipJournal(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'provision-' . bin2hex(random_bytes(5)), $run);
    $journal->create(['server_fingerprint' => str_repeat('a', 64), 'source' => 'autoprintdb', 'target' => $target->value(), 'job_id' => str_repeat('b', 32)]);
    $evidence = (new TargetDatabaseProvisioner())->createNew($target, $run, $connection, $journal, ['tables' => ['items']]);
    assertTrueValue($evidence['created']);
    assertSameValue($target->value(), $connection->currentDatabase());
    assertSameValue($evidence['marker_hash'], $connection->markerHash);

    $thrown = false;
    try { (new TargetDatabaseProvisioner())->createNew($target, $run, $connection, $journal, []); } catch (DomainException) { $thrown = true; }
    assertTrueValue($thrown, 'Existing target must block provisioning');

    $failedConnection = new FakeRestoreConnection(); $failedConnection->failMarkerCreation = true;
    $failedRun = new TrustedRunId('fedcba9876543210fedcba9876543210');
    $failedTarget = (new RestoreIntegrationPolicy())->targetFor('autoprintdb', $failedRun);
    $failedJournal = new OwnershipJournal(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'provision-failed-' . bin2hex(random_bytes(5)), $failedRun);
    $failedJournal->create(['server_fingerprint' => str_repeat('a', 64), 'source' => 'autoprintdb', 'target' => $failedTarget->value(), 'job_id' => str_repeat('c', 32)]);
    $reportedRetained = false;
    try { (new TargetDatabaseProvisioner())->createNew($failedTarget, $failedRun, $failedConnection, $failedJournal, []); }
    catch (TargetProvisioningException $exception) { $reportedRetained = $exception->targetMayExist(); }
    assertTrueValue($reportedRetained);
    assertTrueValue($failedConnection->exists, 'Unverified ownership must retain the exact target instead of dropping it');
    assertSameValue(true, $failedJournal->read()->latestEvent('provision_failed')['target_may_exist'] ?? null);
});

test('RestoreIntegrationExecutor cleanup uses exact ownership decision and verifies absence', function (): void {
    $connection = new FakeRestoreConnection(); $connection->exists = true;
    $run = new TrustedRunId('0123456789abcdef0123456789abcdef');
    $target = (new RestoreIntegrationPolicy())->targetFor('autoprintdb', $run);
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cleanup-' . bin2hex(random_bytes(5));
    $journal = new OwnershipJournal($directory, $run);
    $snapshot = $journal->create(['server_fingerprint' => str_repeat('a', 64), 'source' => 'autoprintdb', 'target' => $target->value(), 'job_id' => str_repeat('b', 32)]);
    $marker = OwnershipMarker::generate($run);
    $snapshot = $journal->append(new OwnershipEvent('target_created', ['created' => true, 'preexisting' => false, 'marker_hash' => $marker->nonceHash()]));
    $connection->markerHash = $marker->nonceHash();
    $runtime = ['run_id' => $run->value(), 'server_fingerprint' => str_repeat('a', 64), 'target' => $target->value(), 'job_id' => str_repeat('b', 32), 'marker_hash' => $marker->nonceHash(), 'shared_lock_held' => true, 'session_owned' => true, 'target_exists' => true];
    $result = (new CleanupCoordinator(new CleanupDecisionEngine()))->cleanup($snapshot, $runtime, $target, $marker, $connection);
    assertTrueValue($result['verified_absent']);
    assertSameValue(['drop', $target->value()], $connection->events[array_key_last($connection->events) - 1]);

    $connection->exists = true;
    $connection->markerHash = str_repeat('f', 64);
    $blocked = (new CleanupCoordinator(new CleanupDecisionEngine()))->cleanup($snapshot, $runtime, $target, $marker, $connection);
    assertFalseValue($blocked['allowed']);
    assertTrueValue($connection->exists);

    $connection->markerHash = $marker->nonceHash();
    $connection->fingerprint = str_repeat('e', 64);
    $blocked = (new CleanupCoordinator(new CleanupDecisionEngine()))->cleanup($snapshot, $runtime, $target, $marker, $connection);
    assertSameValue('cleanup_blocked_server_identity_mismatch', $blocked['code']);
    assertTrueValue($connection->exists);

    $connection->fingerprint = str_repeat('a', 64);
    $connection->selected = 'wrong_context';
    $connection->events = [];
    $result = (new CleanupCoordinator(new CleanupDecisionEngine()))->cleanup($snapshot, $runtime, $target, $marker, $connection);
    assertTrueValue($result['verified_absent']);
    assertSameValue(['select', $target->value()], $connection->events[0]);
});

test('RestoreIntegrationExecutor live mysqli factory is hard disabled without a flag bypass', function (): void {
    $factory = new RestoreConnectionFactory();
    $thrown = false;
    try { $factory->makeRunner(['host' => '127.0.0.1', 'username' => 'root', 'password' => 'x']); }
    catch (DomainException $exception) { $thrown = $exception->getMessage() === 'root_account_forbidden'; }
    assertTrueValue($thrown);
    $thrown = false;
    try { $factory->makeRunner(['host' => '127.0.0.1', 'username' => 'restore_runner', 'password' => 'x']); }
    catch (DomainException $exception) { $thrown = $exception->getMessage() === 'live_restore_execution_disabled'; }
    assertTrueValue($thrown);

    $factory->assertSeparatedRoles(
        ['username' => 'backup_reader', 'password' => 'reader-secret'],
        ['username' => 'restore_runner', 'password' => 'runner-secret']
    );
    $thrown = false;
    try { $factory->assertSeparatedRoles(['username' => 'same', 'password' => 'x'], ['username' => 'same', 'password' => 'y']); }
    catch (DomainException $exception) { $thrown = $exception->getMessage() === 'credential_roles_not_separated'; }
    assertTrueValue($thrown);

    $thrown = false;
    try { new MysqliRestoreConnection(); }
    catch (DomainException $exception) { $thrown = $exception->getMessage() === 'live_restore_execution_disabled'; }
    assertTrueValue($thrown, 'Production adapter constructor must be compile-time sealed');
});

test('RestoreIntegrationExecutor runner evaluates every offline safety gate but remains hard disabled', function (): void {
    $jobId = str_repeat('b', 32);
    $runId = new TrustedRunId(str_repeat('a', 32));
    $identity = new ServerIdentity('mariadb', '11.4-fixture', 'mariadb:laragon-fixture', '127.0.0.1:3306');
    $reader = new PrivilegeEvidence('backup_reader', $identity, true, [], ['autoprintdb', 'drugmap_db', 'drug_stock_check', 'prepack_labels']);
    $target = (new RestoreIntegrationPolicy())->targetFor('autoprintdb', $runId);
    $runner = new PrivilegeEvidence('restore_runner', $identity, true, [], [$target->value()]);
    $artifacts = ['autoprintdb' => str_repeat('c', 64)];
    $authorization = new LiveAuthorizationReceipt($jobId, $runId->value(), $identity->fingerprint(), [$target->value()], time() + 300, 'fixture-context', $artifacts);
    $lockPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'restore-runner-lock-' . bin2hex(random_bytes(5)) . '.lock';
    $assessment = (new RestoreIntegrationRunner(operationLock: new FileDatabaseOperationLock($lockPath)))->assessOfflineReadiness(
        $jobId, $runId, $identity, $identity, $reader, $runner, [$target], $authorization, $artifacts, 'fixture-context'
    );
    assertFalseValue($assessment['ready']);
    assertSameValue('live_restore_execution_disabled', $assessment['code']);
    assertFalseValue(in_array(false, $assessment['checks'], true));

    $thrown = false;
    try {
        (new RestoreIntegrationRunner())->run(
            $jobId,
            ['username' => 'backup_reader', 'password' => 'reader-secret'],
            ['username' => 'restore_runner', 'password' => 'runner-secret'],
            $authorization
        );
    } catch (DomainException $exception) { $thrown = $exception->getMessage() === 'live_restore_execution_disabled'; }
    assertTrueValue($thrown);
});

test('RestoreIntegrationExecutor offline runner holds gates lock ownership comparison and cleanup end to end', function (): void {
    [$plan, $sql] = executorPlan();
    $jobId = str_repeat('b', 32); $runId = new TrustedRunId(str_repeat('a', 32));
    $policy = new RestoreIntegrationPolicy(); $target = $policy->targetFor('autoprintdb', $runId);
    $identity = new ServerIdentity('mariadb', '11.4-fixture', 'mariadb:uid-fixture', '127.0.0.1:3306');
    $reader = new PrivilegeEvidence('backup_reader', $identity, true, [], $policy->authorizedSources());
    $runnerEvidence = new PrivilegeEvidence('restore_runner', $identity, true, [], [$target->value()]);
    $authorization = new LiveAuthorizationReceipt(
        $jobId, $runId->value(), $identity->fingerprint(), [$target->value()], time() + 300,
        'fixture-context', ['autoprintdb' => $plan->contentSha256()]
    );
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'offline-runner-' . bin2hex(random_bytes(5)); mkdir($directory, 0700, true);
    $artifact = $directory . DIRECTORY_SEPARATOR . 'artifact.sql'; file_put_contents($artifact, $sql);
    $connection = new FakeRestoreConnection(); $connection->fingerprint = $identity->fingerprint();
    $connection->schema = ['objects' => $plan->schemaEvidence(), 'types' => ['items' => ['id' => 'int']]];
    $connection->rowsByTable = ['items' => [['id' => '1']]];
    $result = (new RestoreIntegrationRunner(operationLock: new FileDatabaseOperationLock($directory . DIRECTORY_SEPARATOR . 'global.lock')))
        ->runOfflineFixture(
            $jobId, $runId, $artifact, $plan, $target, $identity, $identity, $reader, $runnerEvidence,
            $authorization, 'fixture-context', $connection, new OwnershipJournal($directory . DIRECTORY_SEPARATOR . 'ownership', $runId)
        );
    assertSameValue('succeeded', $result['status']);
    assertTrueValue($result['comparison']['mandatory_passed']);
    assertTrueValue($result['cleanup']['verified_absent']);
    assertFalseValue($connection->exists);
});

test('RestoreIntegrationExecutor releases shared lock when cleanup fails closed', function (): void {
    [$plan, $sql] = executorPlan();
    $jobId = str_repeat('d', 32); $runId = new TrustedRunId(str_repeat('e', 32));
    $policy = new RestoreIntegrationPolicy(); $target = $policy->targetFor('autoprintdb', $runId);
    $identity = new ServerIdentity('mariadb', '11.4-fixture', 'mariadb:cleanup-fixture', '127.0.0.1:3306');
    $reader = new PrivilegeEvidence('backup_reader', $identity, true, [], $policy->authorizedSources());
    $runnerEvidence = new PrivilegeEvidence('restore_runner', $identity, true, [], [$target->value()]);
    $authorization = new LiveAuthorizationReceipt($jobId, $runId->value(), $identity->fingerprint(), [$target->value()],
        time() + 300, 'cleanup-context', ['autoprintdb' => $plan->contentSha256()]);
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'offline-cleanup-failure-' . bin2hex(random_bytes(5)); mkdir($directory, 0700, true);
    $artifact = $directory . DIRECTORY_SEPARATOR . 'artifact.sql'; file_put_contents($artifact, $sql);
    $lockPath = $directory . DIRECTORY_SEPARATOR . 'global.lock'; $lock = new FileDatabaseOperationLock($lockPath);
    $connection = new FakeRestoreConnection(); $connection->fingerprint = $identity->fingerprint();
    $connection->schema = ['objects' => $plan->schemaEvidence()]; $connection->rowsByTable = ['items' => [['id' => '1']]];
    $connection->failTargetExistsOnCall = 2;
    $result = (new RestoreIntegrationRunner(operationLock: $lock))->runOfflineFixture(
        $jobId, $runId, $artifact, $plan, $target, $identity, $identity, $reader, $runnerEvidence,
        $authorization, 'cleanup-context', $connection, new OwnershipJournal($directory . DIRECTORY_SEPARATOR . 'ownership', $runId)
    );
    assertSameValue('failed', $result['status']);
    assertSameValue('cleanup_failed_closed', $result['code']);
    $reacquired = (new FileDatabaseOperationLock($lockPath))->acquire('post-cleanup-test', false);
    assertTrueValue(is_resource($reacquired), 'Cleanup failure must not retain the shared operation lock');
    (new FileDatabaseOperationLock($lockPath))->release($reacquired);
});
