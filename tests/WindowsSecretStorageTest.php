<?php

declare(strict_types=1);

use BackupApp\Restore\Integration\Secrets\FileSecretEnvelopeStore;
use BackupApp\Restore\Integration\Secrets\NativeSecretEnvelopeStore;
use BackupApp\Restore\Integration\Secrets\SecretRole;
use BackupApp\Restore\Integration\Secrets\WindowsAclHardener;
use BackupApp\Restore\Integration\Secrets\WindowsIdentity;

final class RecordingSecretAcl implements WindowsAclHardener
{
    public array $directories = [];
    public array $files = [];
    public bool $failFile = false;
    public ?int $failFileCall = null;

    public function hardenDirectory(string $path, WindowsIdentity $identity): void { $this->directories[] = [$path, $identity->sid()]; }
    public function hardenFile(string $path, WindowsIdentity $identity): void
    {
        $this->files[] = [$path, $identity->sid()];
        if ($this->failFile || $this->failFileCall === count($this->files)) { throw new \BackupApp\Restore\Integration\Secrets\SecretProviderException('acl_hardening_failed'); }
    }
}

test('native production store is fixed outside the repository web tree', function (): void {
    $store = new NativeSecretEnvelopeStore(new RecordingSecretAcl());
    assertSameValue(
        'C:\\ProgramData\\BackupPhpMyAdmin\\SecretProvider\\secrets\\backup-reader.dpapi.json',
        $store->pathFor(SecretRole::BackupReader),
    );
    assertSameValue(
        'C:\\ProgramData\\BackupPhpMyAdmin\\SecretProvider\\secrets\\restore-runner.dpapi.json',
        $store->pathFor(SecretRole::RestoreRunner),
    );
});

function secretStorageFixture(): string
{
    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup-secret-' . bin2hex(random_bytes(6));
    mkdir($root, 0700, true);
    return $root;
}

function removeSecretFixture(string $root): void
{
    if (!is_dir($root)) { return; }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
    rmdir($root);
}

test('secret store uses fixed role paths and atomic create without overwrite', function (): void {
    $root = secretStorageFixture();
    try {
        $acl = new RecordingSecretAcl();
        $store = new FileSecretEnvelopeStore($root, $acl);
        $identity = new WindowsIdentity('DESKTOP-TEST\\svc', 'S-1-5-21-100-200-300-1001');
        $store->create(SecretRole::BackupReader, '{"safe":true}', $identity);
        assertSameValue('{"safe":true}', $store->read(SecretRole::BackupReader));
        assertTrueValue(str_ends_with($store->pathFor(SecretRole::BackupReader), 'backup-reader.dpapi.json'));
        assertTrueValue(str_ends_with($store->pathFor(SecretRole::RestoreRunner), 'restore-runner.dpapi.json'));
        assertSameValue(1, count($acl->directories));
        assertSameValue(2, count($acl->files)); // temporary file, then published final file
        assertSecretCode('secret_already_exists', fn () => $store->create(SecretRole::BackupReader, '{"changed":true}', $identity));
        assertSameValue('{"safe":true}', $store->read(SecretRole::BackupReader));
    } finally { removeSecretFixture($root); }
});

test('secret store fails closed and removes owned temp file on ACL failure', function (): void {
    $root = secretStorageFixture();
    try {
        $acl = new RecordingSecretAcl(); $acl->failFile = true;
        $store = new FileSecretEnvelopeStore($root, $acl);
        $identity = new WindowsIdentity('DESKTOP-TEST\\svc', 'S-1-5-21-100-200-300-1001');
        assertSecretCode('acl_hardening_failed', fn () => $store->create(SecretRole::RestoreRunner, '{"sentinel":"never-persist"}', $identity));
        assertSecretCode('secret_missing', fn () => $store->read(SecretRole::RestoreRunner));
        $files = glob(dirname($store->pathFor(SecretRole::RestoreRunner)) . DIRECTORY_SEPARATOR . '*') ?: [];
        assertSameValue([], $files);
    } finally { removeSecretFixture($root); }
});

test('secret store bounds reads and exact-role deletion', function (): void {
    $root = secretStorageFixture();
    try {
        $acl = new RecordingSecretAcl();
        $store = new FileSecretEnvelopeStore($root, $acl);
        $identity = new WindowsIdentity('DESKTOP-TEST\\svc', 'S-1-5-21-100-200-300-1001');
        assertSecretCode('secret_missing', fn () => $store->read(SecretRole::BackupReader));
        $store->create(SecretRole::RestoreRunner, '{}', $identity);
        file_put_contents($store->pathFor(SecretRole::RestoreRunner), str_repeat('x', 65537));
        assertSecretCode('secret_envelope_invalid', fn () => $store->read(SecretRole::RestoreRunner));
        $store->delete(SecretRole::RestoreRunner);
        assertSecretCode('secret_missing', fn () => $store->read(SecretRole::RestoreRunner));
        assertSecretCode('secret_storage_failed', fn () => new FileSecretEnvelopeStore($root . '/missing-root', $acl));
    } finally { removeSecretFixture($root); }
});

test('secret store removes published envelope when final ACL verification fails', function (): void {
    $root = secretStorageFixture();
    try {
        $acl = new RecordingSecretAcl(); $acl->failFileCall = 2;
        $store = new FileSecretEnvelopeStore($root, $acl);
        $identity = new WindowsIdentity('DESKTOP-TEST\\svc', 'S-1-5-21-100-200-300-1001');
        assertSecretCode('acl_hardening_failed', fn () => $store->create(SecretRole::BackupReader, '{"sentinel":"must-remove"}', $identity));
        assertSecretCode('secret_missing', fn () => $store->read(SecretRole::BackupReader));
    } finally { removeSecretFixture($root); }
});
