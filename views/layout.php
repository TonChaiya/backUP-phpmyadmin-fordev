<?php

declare(strict_types=1);

use BackupApp\Support\Security;

$pageTitle = isset($pageTitle) ? (string) $pageTitle : (string) ($config['app_name'] ?? 'Backup Manager');
$heading = isset($heading) ? (string) $heading : $pageTitle;
$description = isset($description) ? (string) $description : '';
$flashMessages = is_array($flashMessages ?? null) ? $flashMessages : [];
$authConfigured = (bool) ($authConfigured ?? false);
$csrfToken = (string) ($csrfToken ?? '');
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Security::e($pageTitle) ?> · <?= Security::e((string) ($config['app_name'] ?? 'Backup Manager')) ?></title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body data-page="<?= Security::e($activePage) ?>">
    <div class="app-frame">
        <?php if ($showNavigation): ?>
            <?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'navigation.php'; ?>
        <?php endif; ?>

        <main class="app-shell editorial-shell" id="main-content">
            <header class="page-header page-header--compact<?= $activePage === 'dashboard' ? ' page-header--dashboard' : '' ?>">
                <div>
                    <span class="eyebrow">Manual Database Operations</span>
                    <h1><?= Security::e($heading) ?></h1>
                    <?php if ($description !== ''): ?>
                        <p class="hero-subtitle"><?= Security::e($description) ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($activePage === 'dashboard'): ?>
                    <div class="page-header__actions">
                        <div class="dashboard-connection-status" data-state="<?= !empty($isConnected) ? 'connected' : 'disconnected' ?>" aria-label="สถานะการเชื่อมต่อฐานข้อมูล">
                            <span class="status-dot <?= !empty($isConnected) ? 'is-online' : '' ?>" aria-hidden="true"></span>
                            <span><strong><?= !empty($isConnected) ? 'เชื่อมต่อแล้ว' : 'ยังไม่เชื่อมต่อ' ?></strong><small>MySQL / MariaDB session</small></span>
                        </div>
                        <a class="primary-action" href="index.php?page=backup">เริ่ม Manual Backup</a>
                    </div>
                <?php endif; ?>
            </header>

            <?php if ($flashMessages !== []): ?>
                <div class="flash-stack" aria-live="polite">
                    <?php foreach ($flashMessages as $message): ?>
                        <div class="flash <?= Security::e((string) ($message['type'] ?? 'info')) ?>">
                            <?= Security::e((string) ($message['message'] ?? '')) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </main>
    </div>
<script src="assets/app.js" defer></script>
<?php if (in_array(($pageScript ?? ''), ['assets/backup-jobs.js', 'assets/restore-jobs.js'], true)): ?>
    <script src="<?= Security::e((string) $pageScript) ?>" defer></script>
<?php endif; ?>
</body>
</html>

