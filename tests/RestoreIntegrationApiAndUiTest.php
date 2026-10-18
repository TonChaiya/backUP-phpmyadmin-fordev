<?php

declare(strict_types=1);

use BackupApp\Http\Router;
use BackupApp\Restore\Integration\Authorization\LiveAuthorizationReceipt;
use BackupApp\Restore\Integration\Authorization\LiveAuthorizationReceiptStore;
use BackupApp\Restore\Integration\Http\RestoreIntegrationApiAuthorization;
use BackupApp\Support\Auth;
use BackupApp\Support\Security;
use BackupApp\Support\View;

test('RestoreIntegrationApi requires auth and csrf and hard blocks every live action', function (): void {
    $_SESSION = [];
    $auth = new Auth(['username' => 'admin', 'password_hash' => password_hash('secret', PASSWORD_DEFAULT)]);
    $token = Security::csrfToken();
    $policy = new RestoreIntegrationApiAuthorization();
    assertFalseValue($policy->authorize($auth, '127.0.0.1', $token, 'proposal')['allowed']);
    assertTrueValue($auth->attempt('admin', 'secret'));
    assertTrueValue($policy->authorize($auth, '127.0.0.1', $token, 'proposal')['allowed']);
    assertFalseValue($policy->authorize($auth, '127.0.0.1', 'bad', 'proposal')['allowed']);
    foreach (['authorize', 'execute', 'cleanup'] as $action) {
        $decision = $policy->authorize($auth, '127.0.0.1', $token, $action);
        assertFalseValue($decision['allowed']);
        assertSameValue('live_restore_execution_disabled', $decision['code']);
    }
});

test('RestoreIntegrationApi receipt is exact scoped expiring and single use', function (): void {
    $receipt = new LiveAuthorizationReceipt(
        str_repeat('a', 32), str_repeat('b', 32), str_repeat('c', 64),
        ['restore_test_autoprintdb_' . str_repeat('b', 32)], time() + 60, 'session-context',
        ['autoprintdb' => str_repeat('d', 64)]
    );
    assertTrueValue($receipt->validFor(str_repeat('a', 32), str_repeat('b', 32), str_repeat('c', 64)));
    assertFalseValue($receipt->validFor(str_repeat('d', 32), str_repeat('b', 32), str_repeat('c', 64)));
    assertTrueValue($receipt->validForArtifacts(['autoprintdb' => str_repeat('d', 64)]));
    assertTrueValue($receipt->validForContext('session-context'));
    assertFalseValue($receipt->validForContext('other-context'));
    $receipt->consume();
    assertFalseValue($receipt->validFor(str_repeat('a', 32), str_repeat('b', 32), str_repeat('c', 64)));

    $stored = new LiveAuthorizationReceipt(
        str_repeat('a', 32), str_repeat('b', 32), str_repeat('c', 64),
        ['restore_test_autoprintdb_' . str_repeat('b', 32)], time() + 60, 'session-context',
        ['autoprintdb' => str_repeat('d', 64)]
    );
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'receipt-' . bin2hex(random_bytes(5));
    $store = new LiveAuthorizationReceiptStore($directory); $id = str_repeat('e', 32); $store->save($id, $stored);
    $store->consume($id, str_repeat('a', 32), str_repeat('b', 32), str_repeat('c', 64),
        ['restore_test_autoprintdb_' . str_repeat('b', 32)], ['autoprintdb' => str_repeat('d', 64)], 'session-context');
    $thrown = false;
    try {
        $store->consume($id, str_repeat('a', 32), str_repeat('b', 32), str_repeat('c', 64),
            ['restore_test_autoprintdb_' . str_repeat('b', 32)], ['autoprintdb' => str_repeat('d', 64)], 'session-context');
    } catch (DomainException) { $thrown = true; }
    assertTrueValue($thrown, 'Persisted authorization must be single use');

    $corruptId = str_repeat('f', 32);
    $store->save($corruptId, new LiveAuthorizationReceipt(
        str_repeat('a', 32), str_repeat('b', 32), str_repeat('c', 64),
        ['restore_test_autoprintdb_' . str_repeat('b', 32)], time() + 60, 'session-context',
        ['autoprintdb' => str_repeat('d', 64)]
    ));
    file_put_contents($directory . DIRECTORY_SEPARATOR . $corruptId . '.json', '{"consumed":true', FILE_APPEND);
    $thrown = false;
    try {
        $store->consume($corruptId, str_repeat('a', 32), str_repeat('b', 32), str_repeat('c', 64),
            ['restore_test_autoprintdb_' . str_repeat('b', 32)], ['autoprintdb' => str_repeat('d', 64)], 'session-context');
    } catch (DomainException $exception) { $thrown = $exception->getMessage() === 'authorization_receipt_corrupt'; }
    assertTrueValue($thrown, 'A truncated receipt tail must fail closed');
});

test('RestoreIntegrationUi renders offline proposal and no live execution control', function (): void {
    $route = Router::resolve('restore-integration');
    assertSameValue('restore-integration', $route['view']);
    $html = View::render('restore-integration', [
        'pageTitle' => 'Restore Integration', 'heading' => 'Restore Integration',
        'description' => 'Offline only', 'csrfToken' => 'safe-token', 'flashMessages' => [],
        'authorizedSources' => ['autoprintdb', 'drugmap_db', 'drug_stock_check', 'prepack_labels'],
        'activeIntegrationJobId' => '', 'pageScript' => 'assets/restore-integration.js',
    ], 'restore-integration', ['app_name' => 'Test'], true);
    assertContainsValue('Offline implementation only', $html);
    assertContainsValue('สร้าง Offline Proposal', $html);
    assertContainsValue('autoprintdb', $html);
    assertContainsValue('status-strip integration-safety', $html);
    assertContainsValue('editorial-section integration-proposal', $html);
    assertContainsValue('editorial-section integration-readiness', $html);
    assertContainsValue('action="restore-integration.php"', $html);
    assertContainsValue('data-restore-integration', $html);
    assertNotContainsValue('class="panel"', $html);
    assertNotContainsValue('เริ่ม Live Restore', $html);
    assertNotContainsValue('Execute SQL', $html);
    assertContainsValue('live_execution_enabled="false"', $html);
});
