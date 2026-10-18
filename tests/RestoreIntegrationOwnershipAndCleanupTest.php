<?php

declare(strict_types=1);

use BackupApp\Restore\Integration\Ownership\CleanupDecisionEngine;
use BackupApp\Restore\Integration\Ownership\OwnershipEvent;
use BackupApp\Restore\Integration\Ownership\OwnershipJournal;
use BackupApp\Restore\Integration\Ownership\OwnershipMarker;
use BackupApp\Restore\Integration\Policy\TrustedRunId;

test('RestoreIntegrationOwnership journal detects append tampering and incomplete records', function (): void {
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'restore-owner-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700, true);
    $run = new TrustedRunId('0123456789abcdef0123456789abcdef');
    $journal = new OwnershipJournal($directory, $run);
    $snapshot = $journal->create([
        'server_fingerprint' => str_repeat('a', 64),
        'source' => 'autoprintdb',
        'target' => 'restore_test_autoprintdb_' . $run->value(),
        'job_id' => str_repeat('b', 32),
    ]);
    assertSameValue(1, $snapshot->revision());
    $snapshot = $journal->append(new OwnershipEvent('target_created', ['creation_token_hash' => str_repeat('c', 64)]));
    assertSameValue(2, $snapshot->revision());
    assertSameValue('target_created', $snapshot->latestType());
    $thrown = false;
    try { $journal->append(new OwnershipEvent('target_created', ['creation_token_hash' => str_repeat('d', 64)])); }
    catch (DomainException) { $thrown = true; }
    assertTrueValue($thrown, 'Duplicate target creation evidence must fail closed');

    file_put_contents($journal->path(), "{broken", FILE_APPEND);
    $thrown = false;
    try { $journal->read(); } catch (DomainException) { $thrown = true; }
    assertTrueValue($thrown);
});

test('RestoreIntegrationOwnership marker public evidence never exposes nonce', function (): void {
    $marker = OwnershipMarker::generate(new TrustedRunId('0123456789abcdef0123456789abcdef'));
    assertSameValue(64, strlen($marker->nonceHash()));
    assertNotContainsValue($marker->nonce(), json_encode($marker->publicEvidence(), JSON_THROW_ON_ERROR));
    $recovered = OwnershipMarker::fromEvidence(new TrustedRunId('0123456789abcdef0123456789abcdef'), $marker->nonceHash());
    assertSameValue($marker->table(), $recovered->table());
    assertSameValue($marker->nonceHash(), $recovered->nonceHash());
});

test('RestoreIntegrationOwnership cleanup allows only complete exact ownership evidence', function (): void {
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'restore-clean-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700, true);
    $run = new TrustedRunId('0123456789abcdef0123456789abcdef');
    $target = 'restore_test_autoprintdb_' . $run->value();
    $journal = new OwnershipJournal($directory, $run);
    $snapshot = $journal->create([
        'server_fingerprint' => str_repeat('a', 64), 'source' => 'autoprintdb',
        'target' => $target, 'job_id' => str_repeat('b', 32),
    ]);
    $snapshot = $journal->append(new OwnershipEvent('target_created', [
        'preexisting' => false, 'marker_hash' => str_repeat('c', 64), 'created' => true,
    ]));
    $runtime = [
        'run_id' => $run->value(), 'server_fingerprint' => str_repeat('a', 64),
        'target' => $target, 'job_id' => str_repeat('b', 32),
        'marker_hash' => str_repeat('c', 64), 'shared_lock_held' => true,
        'session_owned' => true, 'target_exists' => true,
    ];
    $engine = new CleanupDecisionEngine();
    assertTrueValue($engine->authorize($snapshot, $runtime)->allowed());

    foreach (['target', 'server_fingerprint', 'run_id', 'marker_hash', 'job_id'] as $field) {
        $bad = $runtime;
        $bad[$field] = 'mismatch';
        assertFalseValue($engine->authorize($snapshot, $bad)->allowed(), "{$field} mismatch must block");
    }
    $bad = $runtime; $bad['shared_lock_held'] = false;
    assertFalseValue($engine->authorize($snapshot, $bad)->allowed());
});
