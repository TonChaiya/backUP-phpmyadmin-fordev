<?php

declare(strict_types=1);

use BackupApp\Http\Router;
use BackupApp\Support\View;

test('Phase3ApiAndUi restore route renders preflight-only blocked workflow', function (): void {
    $route = Router::resolve('restore');
    assertSameValue('restore', $route['view']);

    $html = View::render('restore', [
        'pageTitle' => 'Restore',
        'heading' => 'Restore Preflight',
        'description' => 'ตรวจสอบเท่านั้น',
        'csrfToken' => 'csrf-safe',
        'flashMessages' => [],
        'restoreCandidates' => [[
            'relative_path' => 'safe_db/fixture.sql',
            'name' => 'fixture.sql',
            'status' => 'candidate',
        ]],
        'activeRestoreJobId' => '',
        'pageScript' => 'assets/restore-jobs.js',
    ], 'restore', ['app_name' => 'Test'], true);

    assertContainsValue('Restore Preflight', $html);
    assertContainsValue('safe_db/fixture.sql', $html);
    assertContainsValue('ตรวจสอบก่อน Restore', $html);
    assertContainsValue('Execution ปิดอยู่', $html);
    assertNotContainsValue('Execute SQL', $html);
    assertNotContainsValue('เริ่ม Restore', $html);
    assertContainsValue('assets/restore-jobs.js', $html);
});

test('Phase3ApiAndUi history labels quick status without claiming checksum verification', function (): void {
    $html = View::render('history', [
        'pageTitle' => 'History',
        'heading' => 'History',
        'description' => '',
        'csrfToken' => 'csrf-safe',
        'flashMessages' => [],
        'backupFiles' => [
            [
                'name' => 'legacy.sql',
                'folder' => 'legacy',
                'relative_path' => 'legacy/legacy.sql',
                'size' => 100,
                'modified' => 1,
                'download_url' => 'download.php?safe=1',
                'restore_status' => 'unverified_origin',
            ],
            [
                'name' => 'candidate.sql',
                'folder' => 'safe',
                'relative_path' => 'safe/candidate.sql',
                'size' => 100,
                'modified' => 2,
                'download_url' => 'download.php?safe=2',
                'restore_status' => 'candidate',
            ],
        ],
    ], 'history', ['app_name' => 'Test'], true);

    assertContainsValue('unverified_origin', $html);
    assertContainsValue('ยังไม่ได้ตรวจ SHA-256', $html);
    assertContainsValue('ไม่ eligible', $html);
    assertContainsValue('index.php?page=restore&amp;artifact=safe%2Fcandidate.sql', $html);
    assertNotContainsValue('ตรวจสอบแล้ว', $html);
});
