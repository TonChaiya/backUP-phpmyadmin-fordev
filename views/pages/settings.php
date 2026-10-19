<?php

declare(strict_types=1);

use BackupApp\Support\Security;

$connectionData = is_array($connectionData ?? null) ? $connectionData : [];
$runtimeInfo = is_array($runtimeInfo ?? null) ? $runtimeInfo : [];
?>
<div class="settings-flow">
<section class="editorial-section settings-connection settings-section" aria-labelledby="connection-settings-title">
    <div class="section-heading"><div><span class="panel-kicker">Database</span><h2 id="connection-settings-title">การเชื่อมต่อฐานข้อมูล</h2></div><span class="status-pill" data-state="<?= !empty($isConnected) ? 'succeeded' : 'neutral' ?>"><?= !empty($isConnected) ? 'Connected' : 'Disconnected' ?></span></div>
    <p><?= Security::e((string) ($connectionStatus['error'] ?? '')) ?></p>
    <?php if (!empty($connectionStatus['checked_at'])): ?><p>ตรวจจริงล่าสุด: <?= Security::e($connectionStatus['checked_at']) ?></p><?php endif; ?>
    <form method="post" action="index.php?page=settings" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= Security::e((string) $csrfToken) ?>">
        <input type="hidden" name="settings_revision" value="<?= Security::e((string) ($connectionData['revision'] ?? '')) ?>">
        <div class="form-grid">
            <div class="field"><label for="connection-host">Host / IP</label><input id="connection-host" name="host" type="text" value="<?= Security::e((string) ($connectionData['host'] ?? '127.0.0.1')) ?>" required></div>
            <div class="field"><label for="connection-port">Port</label><input id="connection-port" name="port" type="number" min="1" max="65535" value="<?= Security::e((string) ($connectionData['port'] ?? 3306)) ?>" required></div>
        </div>
        <div class="field"><label for="connection-username">Username</label><input id="connection-username" name="username" type="text" value="<?= Security::e((string) ($connectionData['username'] ?? 'root')) ?>" autocomplete="username" required></div>
        <details><summary>เปลี่ยนรหัสผ่าน / Change password</summary>
            <div class="field"><label for="connection-password">Password</label><input id="connection-password" name="password" type="password" autocomplete="new-password" maxlength="4096" aria-describedby="connection-password-help">
                <small id="connection-password-help"><?= empty($connectionData['profile_saved']) ? 'ยังไม่มีโปรไฟล์ — กด Test/Save อย่างชัดเจนก่อนใช้ รหัสว่างได้' : (!empty($connectionData['password_saved']) ? 'มีรหัสบันทึกไว้ — เว้นว่างเพื่อคงเดิม' : 'มีโปรไฟล์รหัสว่างบันทึกไว้แล้ว') ?> ไม่แสดงรหัสเดิมและไม่ Reset อัตโนมัติ</small>
            </div>
            <label><input name="clear_password" type="checkbox" value="1"> ล้างรหัสเดิมและใช้รหัสว่างอย่างชัดเจน (ไม่แก้รหัสบัญชีบน MySQL)</label>
        </details>
        <label><input type="checkbox" name="replace_password" value="1">แทนรหัสเดิมด้วยค่าที่กรอก (รวมรหัสว่าง)</label>
        <label><input type="checkbox" name="confirm_identity_change" value="1">ยืนยันเปลี่ยน Server/บัญชี งานเดิมที่รอจะใช้ต่อไม่ได้</label>
        <p>เฉพาะ MySQL ในเครื่องนี้ ไม่รับ remote หรือ port 3333 ค่าเริ่มต้นไม่ถูกนำไปเชื่อมต่อจนกด Test/Save Import ใช้โปรไฟล์เดียวกันและตรวจสิทธิ์บนฐานใหม่ก่อนเริ่ม</p>
        <div class="action-footer start-actions">
            <button class="secondary-action" type="submit" name="action" value="test_connection_settings">Test Connection</button>
            <button class="primary-action" type="submit" name="action" value="save_connection_settings">Save Settings</button>
            <a class="text-action" href="index.php?page=backup">ไปที่ Export</a>
        </div>
    </form>
    <p>รหัส MySQL แยกจากรหัสผ่านเว็บไซต์ซึ่งไม่ต้อง Login บน localhost เก็บเป็น plaintext เฉพาะ private server-side directory นอก Web Root ภายใต้ NTFS ACL ไม่ใช่ encrypted vault ระบบไม่รีเซ็ตรหัสผ่าน ไม่สร้างบัญชีหรือเพิ่ม GRANT อัตโนมัติ</p>
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
            <small>เลือกได้เฉพาะ Allowlist ที่ derive จาก runtime_data_root/backups เดิม</small>
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
        <div><dt>Website access</dt><dd>Direct loopback only · ไม่ใช้ Application Login</dd></div>
        <div><dt>Request protection</dt><dd>CSRF + SameSite session</dd></div>
        <div><dt>Downloads</dt><dd>Signed, authorized artifacts only</dd></div>
    </dl>
</section>

<section class="editorial-section settings-environment settings-section" aria-labelledby="environment-settings-title">
    <div class="section-heading"><div><span class="panel-kicker">Runtime</span><h2 id="environment-settings-title">Environment</h2></div></div>
    <dl class="definition-list">
        <div><dt>PHP</dt><dd><?= Security::e((string) ($runtimeInfo['php_version'] ?? PHP_VERSION)) ?></dd></div>
        <div><dt>Platform</dt><dd><?= Security::e((string) ($runtimeInfo['environment'] ?? PHP_OS_FAMILY)) ?></dd></div>
        <div><dt>Import</dt><dd>Official mysql.exe · new exact-scoped target only</dd></div>
    </dl>
</section>
</div>

