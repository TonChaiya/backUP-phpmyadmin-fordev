<?php

declare(strict_types=1);

use BackupApp\Support\Security;

$connectionData = is_array($connectionData ?? null) ? $connectionData : [];
$runtimeInfo = is_array($runtimeInfo ?? null) ? $runtimeInfo : [];
?>
<div class="settings-flow">
<section class="editorial-section settings-connection settings-section" aria-labelledby="connection-settings-title">
    <div class="section-heading"><div><span class="panel-kicker">Database</span><h2 id="connection-settings-title">การเชื่อมต่อฐานข้อมูล</h2></div><span class="status-pill" data-state="<?= !empty($isConnected) ? 'succeeded' : 'neutral' ?>"><?= !empty($isConnected) ? 'connected' : 'not connected' ?></span></div>
    <dl class="definition-list">
        <div><dt>Host</dt><dd><?= Security::e((string) ($connectionData['host'] ?? '127.0.0.1')) ?></dd></div>
        <div><dt>Port</dt><dd><?= Security::e((string) ($connectionData['port'] ?? 3306)) ?></dd></div>
        <div><dt>Username</dt><dd><?= Security::e((string) ($connectionData['username'] ?? 'root')) ?></dd></div>
        <div><dt>Password</dt><dd>ไม่แสดงผล</dd></div>
    </dl>
    <a class="secondary-action" href="index.php?page=backup">จัดการ Connection</a>
</section>

<section class="editorial-section settings-storage settings-section" aria-labelledby="storage-settings-title">
    <div class="section-heading">
        <div>
            <span class="panel-kicker">Storage</span>
            <h2 id="storage-settings-title">ตำแหน่งจัดเก็บ Backup</h2>
        </div>
    </div>
    <form method="post" action="index.php?page=settings" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= Security::e((string) $csrfToken) ?>">
        <div class="field">
            <label for="backup-path">Absolute path ภายใต้ Allowlist</label>
            <input id="backup-path" name="backup_path" type="text" value="<?= Security::e((string) ($activeBackupPath ?? '')) ?>" required>
            <small>เพิ่ม root ภายนอกผ่าน <code>config/local.php</code> ก่อนเลือกใช้งาน</small>
        </div>
        <div class="action-footer start-actions">
            <button class="primary-action" type="submit" name="action" value="set_backup_path">บันทึกตำแหน่ง</button>
            <button class="secondary-action" type="submit" name="action" value="open_backup_folder">เปิดโฟลเดอร์</button>
        </div>
    </form>
</section>

<section class="editorial-section settings-security settings-section" aria-labelledby="security-settings-title">
    <div class="section-heading"><div><span class="panel-kicker">Protection</span><h2 id="security-settings-title">Security &amp; Access</h2></div></div>
    <dl class="definition-list">
        <div><dt>Authentication</dt><dd><?= ($authMode ?? '') === 'configured' ? 'Administrator configured' : 'Direct loopback only' ?></dd></div>
        <div><dt>Request protection</dt><dd>CSRF + SameSite session</dd></div>
        <div><dt>Downloads</dt><dd>Signed, authorized artifacts only</dd></div>
    </dl>
</section>

<section class="editorial-section settings-environment settings-section" aria-labelledby="environment-settings-title">
    <div class="section-heading"><div><span class="panel-kicker">Runtime</span><h2 id="environment-settings-title">Environment</h2></div></div>
    <dl class="definition-list">
        <div><dt>PHP</dt><dd><?= Security::e((string) ($runtimeInfo['php_version'] ?? PHP_VERSION)) ?></dd></div>
        <div><dt>Platform</dt><dd><?= Security::e((string) ($runtimeInfo['environment'] ?? PHP_OS_FAMILY)) ?></dd></div>
        <div><dt>Restore execution</dt><dd class="status-text warning">Disabled · no SQL executor</dd></div>
    </dl>
</section>
</div>

