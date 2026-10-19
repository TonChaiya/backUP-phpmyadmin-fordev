<?php

declare(strict_types=1);

use BackupApp\Support\Security;

$connectionData = is_array($connectionData ?? null) ? $connectionData : [];
$databases = is_array($databases ?? null) ? $databases : [];
?>
<div class="workflow-sequence">
    <section class="editorial-section backup-connection" aria-labelledby="backup-connection-title">
        <div class="section-heading">
            <div><span class="panel-kicker">Connection</span><h2 id="backup-connection-title">เชื่อมต่อฐานข้อมูล</h2></div>
            <span class="panel-badge"><?= !empty($isConnected) ? 'Connected' : 'MySQL' ?></span>
        </div>
        <form class="section-form" method="post" action="index.php?page=backup" autocomplete="off">
            <p>ใช้บัญชี backup_reader ที่จำกัดสิทธิ์เฉพาะฐานที่อนุญาต รหัส MySQL ไม่ใช่รหัสผ่านเว็บไซต์ หากไม่มีรหัสให้ติดต่อผู้ดูแล ไม่ใช้ root แทนและไม่รีเซ็ตรหัสอัตโนมัติ</p>
            <input type="hidden" name="csrf_token" value="<?= Security::e((string) $csrfToken) ?>">
            <div class="form-grid">
                <div class="field">
                    <label for="host">Host / IP</label>
                    <input id="host" name="host" type="text" value="<?= Security::e((string) ($connectionData['host'] ?? '127.0.0.1')) ?>" required>
                </div>
                <div class="field">
                    <label for="port">Port</label>
                    <input id="port" name="port" type="number" min="1" max="65535" value="<?= Security::e((string) ($connectionData['port'] ?? 3306)) ?>" required>
                </div>
            </div>
            <div class="field">
                <label for="db-username">Username</label>
                <input id="db-username" name="username" type="text" value="<?= Security::e((string) ($connectionData['username'] ?? 'backup_reader')) ?>" autocomplete="username" required>
            </div>
            <div class="field">
                <label for="db-password">Password</label>
                <input id="db-password" name="password" type="password" autocomplete="current-password">
                <?php if (!empty($isConnected)): ?><small>ใช้รายการฐานด้านล่างได้โดยไม่ต้องส่งฟอร์มเชื่อมต่อใหม่ รหัสอยู่เฉพาะ Server Session หากเชื่อมต่อใหม่ต้องกรอกรหัสอีกครั้ง</small><?php endif; ?>
            </div>
            <button class="primary-action" type="submit" name="action" value="connect">ทดสอบและดึงรายการฐานข้อมูล</button>
        </form>
    </section>

    <section class="editorial-section backup-selection" aria-labelledby="backup-selection-title">
        <div class="section-heading">
            <div><span class="panel-kicker">Databases</span><h2 id="backup-selection-title">เลือกฐานข้อมูลเพื่อสำรอง</h2></div>
            <span class="panel-badge"><?= Security::e((string) count($databases)) ?> ฐาน</span>
        </div>
        <form class="section-form" id="backup-job-form" method="post" action="job.php" autocomplete="off">
            <div class="dialog-notice" data-export-scope>
                <p>MySQL 8.4 mysqldump: รองรับโครงสร้าง InnoDB base table และแถวข้อมูล ในรูปแบบ SQL ของ official MySQL utility เท่านั้น ไม่ใช่ Full database backup</p>
                <p>ไม่รวม Views, Triggers, Stored procedures/functions, Events และ Non-InnoDB engines — ไม่รองรับการ Export วัตถุเหล่านี้ หลีกเลี่ยงการเปลี่ยนโครงสร้างตารางระหว่างสำรอง</p>
            </div>
            <input type="hidden" name="csrf_token" value="<?= Security::e((string) $csrfToken) ?>">
            <?php if ($databases === []): ?>
                <div class="empty-state"><strong>ยังไม่มีรายการฐานข้อมูล</strong><span>เชื่อมต่อก่อนเริ่ม Manual Backup</span></div>
            <?php else: ?>
                <div class="selection-toolbar">
                    <div class="field search-field">
                        <label for="database-search">ค้นหาฐานข้อมูล</label>
                        <input id="database-search" type="search" placeholder="พิมพ์ชื่อฐานข้อมูล" data-database-search>
                    </div>
                    <div class="selection-actions" aria-label="จัดการรายการที่เลือก">
                        <button class="text-button" type="button" data-select-visible>เลือกที่มองเห็น</button>
                        <button class="text-button" type="button" data-clear-selection>ล้างรายการ</button>
                    </div>
                </div>
                <p class="selection-summary" aria-live="polite"><strong data-selected-count>0</strong> ฐานที่เลือก จาก <?= Security::e((string) count($databases)) ?> ฐานที่อนุญาต</p>
                <div class="database-list">
                    <?php foreach ($databases as $database): ?>
                        <label class="db-option" data-database-option data-database-name="<?= Security::e(strtolower((string) $database)) ?>">
                            <input type="checkbox" name="databases[]" value="<?= Security::e((string) $database) ?>">
                            <span><?= Security::e((string) $database) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="action-footer">
                    <button class="primary-action" type="submit" data-job-mode="selected">สำรองฐานที่เลือก</button>
                    <button class="secondary-action" type="submit" data-job-mode="all">สำรองทั้งหมดทีละฐาน</button>
                </div>
                <noscript><div class="flash warning">หน้าสถานะงานสำรองต้องใช้ JavaScript และจะไม่สร้างงานโดยอัตโนมัติ</div></noscript>
            <?php endif; ?>
        </form>
    </section>
</div>

<dialog class="confirm-dialog" data-backup-dialog aria-labelledby="backup-dialog-title">
    <form method="dialog">
        <div class="dialog-icon" aria-hidden="true">!</div>
        <h2 id="backup-dialog-title">ยืนยัน Manual Backup</h2>
        <p data-confirm-summary>ตรวจสอบรายการก่อนสร้างงานสำรอง</p>
        <div class="dialog-notice">ระบบจะประมวลผลทีละฐาน (concurrency = 1) และจะดำเนินการฐานถัดไปเมื่อฐานหนึ่งล้มเหลว</div>
        <div class="dialog-actions">
            <button class="secondary-action" type="submit" value="cancel">ยกเลิก</button>
            <button class="primary-action" type="button" data-confirm-backup>ยืนยันและเริ่มงาน</button>
        </div>
    </form>
</dialog>

<section
    class="editorial-section backup-job-status"
    aria-labelledby="backup-job-status-title"
    data-backup-jobs
    data-job-api="job.php"
    data-csrf-token="<?= Security::e((string) $csrfToken) ?>"
    data-active-job-id="<?= Security::e((string) ($activeJobId ?? '')) ?>"
>
    <div class="section-heading">
        <div>
            <span class="panel-kicker">Persisted Job State</span>
            <h2 id="backup-job-status-title">สถานะงานสำรอง</h2>
        </div>
        <strong class="job-state" data-job-state>ยังไม่มีงาน</strong>
    </div>
    <div class="job-status-body">
        <p class="job-summary" data-job-summary>queued 0 · running 0 · succeeded 0 · failed 0 · interrupted 0 · total 0</p>
        <p class="job-message" data-job-message aria-live="polite"></p>
        <ul class="job-database-list" data-job-databases></ul>
        <button class="secondary-action" type="button" data-job-resume hidden>เริ่มงานที่รออยู่</button>
    </div>
</section>

