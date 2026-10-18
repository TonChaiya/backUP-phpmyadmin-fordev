<?php

declare(strict_types=1);

use BackupApp\Http\Router;
use BackupApp\Support\View;

test('router resolves every Phase 1 page through an allowlist', static function (): void {
    $expected = [
        'dashboard' => 'dashboard',
        'backup' => 'backup',
        'restore' => 'restore',
        'history' => 'history',
        'settings' => 'settings',
    ];

    foreach ($expected as $page => $view) {
        $route = Router::resolve($page);
        assertSameValue($page, $route['name']);
        assertSameValue($view, $route['view']);
        assertSameValue(200, $route['status']);
    }
});

test('router returns the shared not-found view for an unknown page', static function (): void {
    $route = Router::resolve('../../config/local.php');

    assertSameValue('not-found', $route['name']);
    assertSameValue('placeholder', $route['view']);
    assertSameValue(404, $route['status']);
});

test('shared layout renders consistent navigation and marks the active page', static function (): void {
    $html = View::render('placeholder', [
        'pageTitle' => 'Restore',
        'heading' => 'Restore',
        'description' => 'Phase 2',
        'placeholderTitle' => 'ยังไม่เปิดใช้งาน',
        'placeholderMessage' => 'เตรียมไว้สำหรับ Phase ถัดไป',
    ], 'restore', ['app_name' => 'Backup Manager']);

    foreach (['Dashboard', 'Backup', 'Restore', 'History', 'Settings'] as $label) {
        assertContainsValue($label, $html);
    }

    assertContainsValue('aria-current="page"', $html);
    assertContainsValue('page=restore', $html);
});

test('backup view never renders the server-side database password', static function (): void {
    $sentinel = 'DO-NOT-RENDER-DB-PASSWORD';
    $html = View::render('backup', [
        'pageTitle' => 'Backup',
        'heading' => 'Backup',
        'description' => 'Manual backup',
        'csrfToken' => 'csrf-token',
        'connectionData' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'username' => 'root',
            'password' => $sentinel,
        ],
        'databases' => [],
        'isConnected' => true,
    ], 'backup', ['app_name' => 'Backup Manager']);

    assertNotContainsValue($sentinel, $html);
    assertContainsValue('name="password"', $html);
    assertNotContainsValue('name="password" value=', $html);
});

