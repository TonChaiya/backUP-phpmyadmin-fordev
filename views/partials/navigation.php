<?php

declare(strict_types=1);

use BackupApp\Support\Security;

$items = [
    'dashboard' => 'Dashboard',
    'backup' => 'Backup',
    'restore' => 'Restore',
    'restore-integration' => 'Restore Lab',
    'history' => 'History',
    'settings' => 'Settings',
];
?>
<a class="skip-link" href="#main-content">ข้ามไปยังเนื้อหา</a>
<header class="topbar">
    <a class="brand" href="index.php?page=dashboard" aria-label="Dashboard">
        <span class="brand-mark" aria-hidden="true">DB</span>
        <span><?= Security::e((string) ($config['app_name'] ?? 'Backup Manager')) ?></span>
    </a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation" hidden data-nav-toggle>
        <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path d="M4 6h16M4 12h16M4 18h16" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2"/></svg>
        <span>เมนู</span>
    </button>
    <nav class="primary-nav" id="primary-navigation" aria-label="เมนูหลัก" data-primary-nav>
        <?php foreach ($items as $name => $label): ?>
            <a href="index.php?page=<?= Security::e($name) ?>"<?= $activePage === $name ? ' aria-current="page"' : '' ?>>
                <?= Security::e($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php if ($authConfigured): ?>
        <form method="post" action="index.php" class="logout-form">
            <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken) ?>">
            <button class="text-action" type="submit" name="action" value="logout">ออกจากระบบ</button>
        </form>
    <?php endif; ?>
</header>

