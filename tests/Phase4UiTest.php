<?php

declare(strict_types=1);

use BackupApp\Backup\BackupJobAccess;
use BackupApp\Support\View;

test('Phase4Ui backup history exposes unique valid session jobs newest first', static function (): void {
    $oldest = str_repeat('a', 32);
    $newest = str_repeat('b', 32);
    $_SESSION['backup_job_ids'] = [$oldest, 'invalid', $newest, $oldest, 42];

    assertSameValue([$oldest, $newest], (new BackupJobAccess())->ownedJobIds());
});

test('Phase4Ui shared shell exposes accessible responsive navigation', static function (): void {
    $html = View::render('placeholder', [
        'pageTitle' => 'History',
        'heading' => 'History',
        'description' => 'รายการไฟล์',
        'placeholderTitle' => 'ว่าง',
        'placeholderMessage' => 'ยังไม่มีข้อมูล',
        'csrfToken' => 'token',
    ], 'history', ['app_name' => 'Backup Manager']);

    assertContainsValue('class="skip-link"', $html);
    assertContainsValue('hidden data-nav-toggle', $html);
    assertContainsValue('aria-expanded="false"', $html);
    assertContainsValue('id="primary-navigation"', $html);
    assertNotContainsValue('id="primary-navigation" hidden', $html);
    assertContainsValue('assets/app.js', $html);
});

test('Phase4Ui shared shell establishes one compact editorial content flow', static function (): void {
    $html = View::render('placeholder', [
        'pageTitle' => 'History',
        'heading' => 'History',
        'description' => 'รายการไฟล์',
        'placeholderTitle' => 'ว่าง',
        'placeholderMessage' => 'ยังไม่มีข้อมูล',
        'csrfToken' => 'token',
    ], 'history', ['app_name' => 'Backup Manager']);

    assertContainsValue('class="app-shell editorial-shell"', $html);
    assertContainsValue('class="page-header page-header--compact"', $html);
    assertSameValue(1, substr_count($html, '<h1'));
    assertContainsValue('id="main-content"', $html);
    assertContainsValue('class="skip-link"', $html);
});

test('Phase4Ui dashboard renders persisted facts and hard-closed restore safety', static function (): void {
    $html = View::render('dashboard', [
        'pageTitle' => 'Dashboard',
        'heading' => 'Dashboard',
        'description' => 'ภาพรวม',
        'csrfToken' => 'token',
        'isConnected' => true,
        'databaseCount' => 3,
        'backupCount' => 7,
        'latestBackupLabel' => '2026-10-17 08:45',
        'recentBackupJobs' => [[
            'id' => str_repeat('a', 32),
            'status' => 'failed',
            'updated_at' => '2026-10-17T08:40:00+07:00',
            'summary' => ['succeeded' => 2, 'failed' => 1, 'total' => 3],
        ]],
    ], 'dashboard', ['app_name' => 'Backup Manager']);

    assertContainsValue('เชื่อมต่อแล้ว', $html);
    assertContainsValue('failed', $html);
    assertContainsValue('2 สำเร็จ · 1 ล้มเหลว · 3 ทั้งหมด', $html);
    assertContainsValue('Import ลงฐานใหม่เท่านั้น', $html);
    assertNotContainsValue('success rate', strtolower($html));
});

test('Phase4Ui dashboard uses a seamless header metrics and editorial activity flow', static function (): void {
    $html = View::render('dashboard', [
        'pageTitle' => 'Dashboard',
        'heading' => 'Dashboard',
        'description' => 'ภาพรวมการสำรองข้อมูลและสถานะล่าสุด',
        'csrfToken' => 'token',
        'isConnected' => false,
        'databaseCount' => 0,
        'backupCount' => 0,
        'latestBackupLabel' => 'ยังไม่มี',
        'recentBackupJobs' => [],
    ], 'dashboard', ['app_name' => 'Backup Manager']);

    assertSameValue(1, substr_count($html, '<h1'));
    assertContainsValue('page-header--dashboard', $html);
    assertContainsValue('page-header__actions', $html);
    assertContainsValue('dashboard-connection-status', $html);
    assertContainsValue('class="metric-strip"', $html);
    assertContainsValue('class="dashboard-flow"', $html);
    assertContainsValue('editorial-section dashboard-activity', $html);
    assertContainsValue('editorial-section safety-summary', $html);
    assertContainsValue('>เริ่ม Export</a>', $html);
    assertNotContainsValue('dashboard-hero', $html);
});

test('Phase4Ui settings groups connection storage security and environment without secrets', static function (): void {
    $secret = 'PHASE4-DO-NOT-RENDER';
    $html = View::render('settings', [
        'pageTitle' => 'Settings',
        'heading' => 'Settings',
        'description' => 'การตั้งค่า',
        'csrfToken' => 'token',
        'activeBackupPath' => 'C:\\safe\\backups',
        'connectionData' => ['host' => '127.0.0.1', 'port' => 3306, 'username' => 'reader', 'password' => $secret],
        'isConnected' => true,
        'authMode' => 'configured',
        'runtimeInfo' => ['php_version' => '8.3.30', 'environment' => 'Windows'],
    ], 'settings', ['app_name' => 'Backup Manager']);

    foreach (['การเชื่อมต่อฐานข้อมูล', 'Storage', 'Security &amp; Access', 'Environment'] as $label) {
        assertContainsValue($label, $html);
    }
    assertContainsValue('class="settings-flow"', $html);
    foreach (['settings-connection', 'settings-storage', 'settings-security', 'settings-environment'] as $section) {
        assertContainsValue('editorial-section ' . $section, $html);
    }
    assertNotContainsValue('settings-grid', $html);
    assertNotContainsValue('class="content-section', $html);
    assertContainsValue('reader', $html);
    assertNotContainsValue($secret, $html);
});

test('Phase4Ui backup renders searchable selection count and explicit confirmation', static function (): void {
    $html = View::render('backup', [
        'pageTitle' => 'Backup',
        'heading' => 'Manual Backup',
        'description' => 'เลือกฐานข้อมูล',
        'csrfToken' => 'token',
        'connectionData' => ['host' => '127.0.0.1', 'port' => 3306, 'username' => 'reader'],
        'databases' => ['alpha_db', 'beta_db'],
        'isConnected' => true,
        'activeJobId' => '',
        'pageScript' => 'assets/backup-jobs.js',
    ], 'backup', ['app_name' => 'Backup Manager']);

    assertContainsValue('data-database-search', $html);
    assertContainsValue('data-select-visible', $html);
    assertContainsValue('data-clear-selection', $html);
    assertContainsValue('data-selected-count', $html);
    assertContainsValue('<dialog', $html);
    assertContainsValue('data-confirm-backup', $html);
    assertContainsValue('editorial-section backup-connection', $html);
    assertContainsValue('editorial-section backup-selection', $html);
    assertContainsValue('editorial-section backup-job-status', $html);
    assertContainsValue('href="index.php?page=settings"', $html);
    assertContainsValue('action="job.php"', $html);
    assertContainsValue('data-backup-jobs', $html);
    assertNotContainsValue('class="panel"', $html);
    assertNotContainsValue('percent', strtolower($html));
    assertNotContainsValue('%', $html);
});

test('Phase4Ui history renders metadata-only integrity provenance and eligibility controls', static function (): void {
    $html = View::render('history', [
        'pageTitle' => 'History',
        'heading' => 'Backup History',
        'description' => 'รายการไฟล์',
        'csrfToken' => 'token',
        'backupFiles' => [[
            'name' => 'safe.sql',
            'folder' => 'safe_db',
            'relative_path' => 'safe_db/safe.sql',
            'size' => 2048,
            'modified' => 1792200000,
            'database' => 'safe_db',
            'integrity_status' => 'not_checked',
            'provenance_status' => 'verified',
            'restore_eligibility' => 'preflight_required',
            'restore_status' => 'candidate',
            'import_status' => 'ready',
            'download_url' => 'download.php?file=safe&amp;token=token',
        ]],
    ], 'history', ['app_name' => 'Backup Manager']);

    foreach (['ฐานข้อมูล', 'Integrity', 'Provenance', 'Eligibility', 'ยังไม่ได้ตรวจ SHA-256', 'verified', 'ตรวจไฟล์ก่อน Import'] as $label) {
        assertContainsValue($label, $html);
    }
    assertContainsValue('data-history-search', $html);
    assertContainsValue('data-history-filter', $html);
    assertContainsValue('editorial-section history-section', $html);
    assertContainsValue('class="history-toolbar"', $html);
    assertContainsValue('role="region"', $html);
    assertNotContainsValue('class="content-section', $html);
    assertContainsValue('page=import', $html);
    assertContainsValue('ดาวน์โหลด', $html);
});

