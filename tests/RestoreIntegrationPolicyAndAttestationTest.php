<?php

declare(strict_types=1);

use BackupApp\Restore\Integration\Attestation\LiveRestoreAttestationGate;
use BackupApp\Restore\Integration\Attestation\MySqlPrivilegeDialect;
use BackupApp\Restore\Integration\Attestation\PrivilegeAttestor;
use BackupApp\Restore\Integration\Attestation\ServerIdentityProbe;
use BackupApp\Restore\Integration\Attestation\PrivilegeAttestationService;
use BackupApp\Restore\Integration\Database\AuthorizedSourceCatalog;
use BackupApp\Restore\Integration\Database\MetadataConnection;
use BackupApp\Restore\Integration\Database\IntegrationBackupOperation;
use BackupApp\Backup\BackupOperation;
use BackupApp\Restore\Integration\Policy\RestoreIntegrationPolicy;
use BackupApp\Restore\Integration\Policy\TrustedRunId;

final class RecordingMetadataConnection implements MetadataConnection
{
    public array $requested = [];
    public function __construct(
        private array $schemas,
        private array $facts,
        private string $account,
        private array $grants
    ) {}
    public function authorizedSchemas(array $exactNames): array { $this->requested = $exactNames; return $this->schemas; }
    public function serverIdentityFacts(): array { return $this->facts; }
    public function currentAccount(): string { return $this->account; }
    public function grantLines(): array { return $this->grants; }
    public function privilegeContextFacts(): array { return ['partial_revokes' => false, 'roles_resolved' => true, 'role_grants_resolved' => true, 'active_roles' => []]; }
    public function close(): void {}
}

final class RecordingBackupOperation implements BackupOperation
{
    public array $events = [];
    public function start(): void { $this->events[] = 'start'; }
    public function backup(string $database): array
    {
        $this->events[] = $database;
        return ['database' => $database, 'filename' => $database . '.sql', 'relative_path' => $database . '/' . $database . '.sql', 'bytes' => 1, 'sha256' => str_repeat('a', 64), 'manifest_path' => $database . '.manifest.json'];
    }
    public function finish(): void { $this->events[] = 'finish'; }
}

test('RestoreIntegrationPolicy fixes sources and derives exact targets', function (): void {
    $policy = new RestoreIntegrationPolicy();
    assertSameValue(['autoprintdb', 'drugmap_db', 'drug_stock_check', 'prepack_labels'], $policy->authorizedSources());
    $run = new TrustedRunId('0123456789abcdef0123456789abcdef');
    assertSameValue('restore_test_autoprintdb_0123456789abcdef0123456789abcdef', $policy->targetFor('autoprintdb', $run)->value());
    foreach (['jhcisdb', 'mysql', 'unknown', 'AUTOPRINTDB'] as $name) {
        $thrown = false;
        try { $policy->assertSource($name); } catch (DomainException) { $thrown = true; }
        assertTrueValue($thrown, "{$name} must be denied");
    }
});

test('RestoreIntegrationPolicy catalog requests only the exact four sources', function (): void {
    $connection = new RecordingMetadataConnection(
        ['autoprintdb', 'drugmap_db'],
        [],
        'backup_reader@localhost',
        []
    );
    $result = (new AuthorizedSourceCatalog(new RestoreIntegrationPolicy()))->available($connection);
    assertSameValue(['autoprintdb', 'drugmap_db'], $result);
    assertSameValue(['autoprintdb', 'drugmap_db', 'drug_stock_check', 'prepack_labels'], $connection->requested);
});

test('RestoreIntegrationPolicy backup adapter refuses sources outside exact catalog', function (): void {
    $delegate = new RecordingBackupOperation();
    $operation = new IntegrationBackupOperation($delegate, new RestoreIntegrationPolicy(), ['autoprintdb']);
    $operation->start();
    assertSameValue('autoprintdb', $operation->backup('autoprintdb')['database']);
    $thrown = false;
    try { $operation->backup('jhcisdb'); } catch (DomainException) { $thrown = true; }
    assertTrueValue($thrown);
    $operation->finish();
    assertSameValue(['start', 'autoprintdb', 'finish'], $delegate->events);
});

test('RestoreIntegrationPolicy server identity requires stable same-server evidence', function (): void {
    $facts = ['product' => 'mysql', 'version' => '8.4.0', 'stable_id' => 'mysql:uuid-1', 'endpoint' => '127.0.0.1:3306'];
    $connection = new RecordingMetadataConnection([], $facts, 'backup_reader@localhost', []);
    $identity = (new ServerIdentityProbe())->probe($connection);
    assertSameValue('mysql', $identity->product());
    assertTrueValue($identity->sameServer($identity));

    $bad = new RecordingMetadataConnection([], array_merge($facts, ['stable_id' => '']), 'reader@localhost', []);
    $thrown = false;
    try { (new ServerIdentityProbe())->probe($bad); } catch (DomainException) { $thrown = true; }
    assertTrueValue($thrown);
});

test('RestoreIntegrationPolicy privilege attestation rejects root broad wildcard and crossover grants', function (): void {
    $policy = new RestoreIntegrationPolicy();
    $attestor = new PrivilegeAttestor($policy, new MySqlPrivilegeDialect());
    $identity = (new ServerIdentityProbe())->probe(new RecordingMetadataConnection([], [
        'product' => 'mysql', 'version' => '8.4.0', 'stable_id' => 'mysql:uuid-1', 'endpoint' => '127.0.0.1:3306',
    ], 'reader@localhost', []));

    $readerContext = ['partial_revokes' => false, 'roles_resolved' => true, 'role_grants_resolved' => true, 'active_roles' => []];
    $reader = $attestor->backupReaderEvidence($identity, 'backup_reader@localhost', [
        "GRANT USAGE ON *.* TO `backup_reader`@`localhost`",
        "GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON `autoprintdb`.* TO `backup_reader`@`localhost`",
        "GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON `drugmap\\_db`.* TO `backup_reader`@`localhost`",
        "GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON `drug\\_stock\\_check`.* TO `backup_reader`@`localhost`",
        "GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON `prepack\\_labels`.* TO `backup_reader`@`localhost`",
    ], $readerContext);
    assertTrueValue($reader->accepted());
    assertFalseValue($attestor->backupReaderEvidence($identity, 'backup_reader@localhost', [
        "GRANT SELECT ON `autoprintdb`.* TO `backup_reader`@`localhost`",
    ], $readerContext)->accepted(), 'Missing required reader privileges/scopes must fail');
    assertFalseValue($attestor->backupReaderEvidence($identity, 'backup_reader@localhost', [
        "GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON `autoprintdb`.* TO `backup_reader`@`localhost`",
    ])->accepted(), 'Missing role and partial_revokes evidence must fail closed');

    foreach ([
        ['root@localhost', ["GRANT ALL PRIVILEGES ON *.* TO `root`@`localhost`"]],
        ['backup_reader@localhost', ["GRANT SELECT ON `restore_test_%`.* TO `backup_reader`@`localhost`"]],
        ['backup_reader@localhost', ["GRANT SELECT, INSERT ON `autoprintdb`.* TO `backup_reader`@`localhost`"]],
        ['backup_reader@localhost', ["GRANT SELECT ON `jhcisdb`.* TO `backup_reader`@`localhost`"]],
        ['backup_reader@localhost', ["GRANT USAGE ON *.* TO `backup_reader`@`localhost` WITH GRANT OPTION"]],
    ] as [$account, $grants]) {
        assertFalseValue($attestor->backupReaderEvidence($identity, $account, $grants, $readerContext)->accepted());
    }
});

test('RestoreIntegrationPolicy gate opens only for matching accepted evidence', function (): void {
    $policy = new RestoreIntegrationPolicy();
    $run = new TrustedRunId('0123456789abcdef0123456789abcdef');
    $target = $policy->targetFor('autoprintdb', $run);
    $connection = new RecordingMetadataConnection([], [
        'product' => 'mysql', 'version' => '8.4.0', 'stable_id' => 'mysql:uuid-1', 'endpoint' => '127.0.0.1:3306',
    ], 'runner@localhost', []);
    $identity = (new ServerIdentityProbe())->probe($connection);
    $attestor = new PrivilegeAttestor($policy, new MySqlPrivilegeDialect());
    $context = ['partial_revokes' => false, 'roles_resolved' => true, 'role_grants_resolved' => true, 'active_roles' => []];
    $reader = $attestor->backupReaderEvidence($identity, 'backup_reader@localhost', [
        "GRANT USAGE ON *.* TO `backup_reader`@`localhost`",
        "GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON `autoprintdb`.* TO `backup_reader`@`localhost`",
        "GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON `drugmap\\_db`.* TO `backup_reader`@`localhost`",
        "GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON `drug\\_stock\\_check`.* TO `backup_reader`@`localhost`",
        "GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON `prepack\\_labels`.* TO `backup_reader`@`localhost`",
    ], $context);
    $runner = $attestor->restoreRunnerEvidence($identity, 'restore_runner@localhost', [
        'GRANT CREATE, DROP, ALTER, INDEX, INSERT, SELECT, REFERENCES, LOCK TABLES ON `restore\_test\_autoprintdb\_0123456789abcdef0123456789abcdef`.* TO `restore_runner`@`localhost`',
    ], [$target], $context);
    assertTrueValue($runner->accepted());
    $gate = (new LiveRestoreAttestationGate())->evaluate($identity, $identity, $reader, $runner, $run, [$target]);
    assertTrueValue($gate['open']);
    $badReader = $attestor->backupReaderEvidence($identity, 'root@localhost', ['GRANT ALL PRIVILEGES ON *.* TO `root`@`localhost`'], $context);
    assertFalseValue((new LiveRestoreAttestationGate())->evaluate($identity, $identity, $badReader, $runner, $run, [$target])['open']);

    $otherRun = new TrustedRunId('fedcba9876543210fedcba9876543210');
    $otherTarget = $policy->targetFor('autoprintdb', $otherRun);
    assertFalseValue((new LiveRestoreAttestationGate())->evaluate($identity, $identity, $reader, $runner, $run, [$otherTarget])['open']);
    $otherRunner = new BackupApp\Restore\Integration\Attestation\PrivilegeEvidence('restore_runner', $identity, true, [], [$otherTarget->value()]);
    assertFalseValue((new LiveRestoreAttestationGate())->evaluate($identity, $identity, $reader, $otherRunner, $run, [$otherTarget])['open']);
    $remoteIdentity = (new ServerIdentityProbe())->probe(new RecordingMetadataConnection([], [
        'product' => 'mysql', 'version' => '8.4.0', 'stable_id' => 'mysql:uuid-1', 'endpoint' => '10.0.0.5:3306',
    ], 'runner@remote', []));
    assertFalseValue((new LiveRestoreAttestationGate())->evaluate($remoteIdentity, $remoteIdentity, $reader, $runner, $run, [$target])['open']);
});

test('RestoreIntegrationPolicy attestation service consumes connection-derived privilege context', function (): void {
    $grants = [
        "GRANT USAGE ON *.* TO `backup_reader`@`localhost`",
        "GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON `autoprintdb`.* TO `backup_reader`@`localhost`",
        "GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON `drugmap\\_db`.* TO `backup_reader`@`localhost`",
        "GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON `drug\\_stock\\_check`.* TO `backup_reader`@`localhost`",
        "GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON `prepack\\_labels`.* TO `backup_reader`@`localhost`",
    ];
    $connection = new RecordingMetadataConnection([], [
        'product' => 'mysql', 'version' => '8.4.0', 'stable_id' => 'mysql:uuid-fixture', 'endpoint' => '127.0.0.1:3306',
    ], 'backup_reader@localhost', $grants);
    assertTrueValue((new PrivilegeAttestationService())->backupReader($connection)['privileges']->accepted());
});
