<?php

declare(strict_types=1);

use BackupApp\Support\Security;

$connectionData = is_array($connectionData ?? null) ? $connectionData : [];
$databases = !empty($isConnected) && is_array($databases ?? null) ? $databases : [];
$databases=(new \BackupApp\Database\AuthorizedSourcePolicy())->filterAuthorized($databases);
?>
<div class="workflow-sequence">
    <section class="editorial-section backup-connection" aria-labelledby="backup-connection-title">
        <div class="section-heading">
            <div><span class="panel-kicker">Connection</span><h2 id="backup-connection-title">เชื่อมต่อฐานข้อมูล</h2></div>
            <span class="panel-badge"><?= !empty($isConnected) ? 'Connected' : 'Disconnected' ?></span>
        </div>
        <p><?= Security::e((string) ($connectionData['host'] ?? '127.0.0.1')) ?>:<?= Security::e((string) ($connectionData['port'] ?? 3306)) ?> · <?= Security::e((string) ($connectionData['username'] ?? 'root')) ?></p>
        <?php if (!empty($isConnected)): ?>
            <p>MySQL <?= Security::e((string) ($connectionStatus['server']['version'] ?? '')) ?> · ตรวจจริงล่าสุด <?= Security::e((string) ($connectionStatus['checked_at'] ?? '')) ?></p>
        <?php else: ?>
            <p class="flash warning"><?= Security::e((string) ($connectionStatus['error'] ?? 'Disconnected')) ?></p>
            <button type="button" class="primary-action" disabled>Export ไม่พร้อม</button>
        <?php endif; ?>
        <a class="secondary-action" href="index.php?page=settings">ไปที่ Settings เพื่อแก้ไขการเชื่อมต่อ</a>
    </section>

    <section class="editorial-section backup-selection" aria-labelledby="backup-selection-title">
        <div class="section-heading">
            <div><span class="panel-kicker">Databases</span><h2 id="backup-selection-title">เลือกฐานข้อมูลเพื่อสำรอง</h2></div>
            <span class="panel-badge"><?= Security::e((string) count($databases)) ?> ฐาน</span>
        </div>
        <form class="section-form" id="backup-job-form" method="post" action="job.php" autocomplete="off">
            <p>รายชื่อจากสิทธิ์บัญชีใน Settings เลือกหนึ่งฐาน หลายฐาน หรือทั้งหมดที่ Export ได้ โดยไม่รวมฐานที่ป้องกันและ System schemas</p>
            <div class="dialog-notice" data-export-scope>
                <p>MySQL 8.4 mysqldump: รองรับโครงสร้าง InnoDB base table และแถวข้อมูล ในรูปแบบ SQL ของ official MySQL utility เท่านั้น ไม่ใช่ Full database backup</p>
                <p>ไม่รวม Views, Triggers, Stored procedures/functions, Events และ Non-InnoDB engines — ไม่รองรับการ Export วัตถุเหล่านี้ หลีกเลี่ยงการเปลี่ยนโครงสร้างตารางระหว่างสำรอง</p>
            </div>
            <input type="hidden" name="csrf_token" value="<?= Security::e((string) $csrfToken) ?>">
            <?php if ($databases === []): ?>
                <div class="empty-state"><strong>ยังไม่มีรายการฐานข้อมูล</strong><span>ไปที่ Settings เพื่อบันทึกและทดสอบ Connection ก่อนเริ่ม Export</span></div>
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
        <?php if(!empty($isConnected)&&!empty($systemDatabases)): ?>
            <h3>System schemas — แสดงแยก ไม่อนุญาต Export/Import</h3>
            <p><?= Security::e(implode(', ',array_filter($systemDatabases,static fn($name)=>is_string($name)&&(new \BackupApp\Database\AuthorizedSourcePolicy())->isSystem($name)))) ?></p>
        <?php endif; ?>
        <?php if(!empty($isConnected)&&!empty($unsupportedDatabases)): ?>
            <h3>ชื่อฐานที่ยังไม่รองรับการจัดเก็บไฟล์บน Windows</h3>
            <ul><?php foreach($unsupportedDatabases as $name): ?><?php if(is_string($name)&&!(new \BackupApp\Database\AuthorizedSourcePolicy())->isProtected($name)): ?><li><?= Security::e($name) ?></li><?php endif; ?><?php endforeach; ?></ul>
        <?php endif; ?>
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

