<?php

declare(strict_types=1);

use BackupApp\Support\Security;

$recentBackupJobs = is_array($recentBackupJobs ?? null) ? $recentBackupJobs : [];
?>
<section class="metric-strip" aria-label="ภาพรวมระบบ">
    <article class="metric-item"><span>ฐานข้อมูลที่เลือกได้</span><strong><?= Security::e((string) ($databaseCount ?? 0)) ?></strong><small>จาก connection ใน session นี้</small></article>
    <article class="metric-item"><span>ไฟล์สำรอง</span><strong><?= Security::e((string) ($backupCount ?? 0)) ?></strong><small>ภายใต้ storage root ปัจจุบัน</small></article>
    <article class="metric-item"><span>สำรองล่าสุด</span><strong class="metric-date"><?= Security::e((string) ($latestBackupLabel ?? 'ยังไม่มี')) ?></strong><small>อ้างอิงเวลาของไฟล์ล่าสุด</small></article>
</section>

<div class="dashboard-flow">
<section class="editorial-section dashboard-activity" aria-labelledby="recent-backup-heading">
    <div class="section-heading">
        <div><span class="panel-kicker">Recent jobs</span><h2 id="recent-backup-heading">งานสำรองล่าสุด</h2></div>
        <a class="text-link" href="index.php?page=backup">ดูสถานะ</a>
    </div>
    <?php if ($recentBackupJobs === []): ?>
        <div class="empty-state"><strong>ยังไม่มีงานใน session นี้</strong><span>เริ่มงานจากหน้า Export แล้วสถานะจริงจะแสดงที่นี่</span></div>
    <?php else: ?>
        <ul class="activity-list">
        <?php foreach ($recentBackupJobs as $job): $summary = is_array($job['summary'] ?? null) ? $job['summary'] : []; ?>
            <li><div><strong>งาน <?= Security::e(substr((string) ($job['id'] ?? ''), 0, 8)) ?></strong><span><?= Security::e((string) ($job['updated_at'] ?? '')) ?></span></div><div class="activity-result"><span class="status-pill" data-state="<?= Security::e((string) ($job['status'] ?? 'queued')) ?>"><?= Security::e((string) ($job['status'] ?? 'queued')) ?></span><small><?= Security::e((string) ($summary['succeeded'] ?? 0)) ?> สำเร็จ · <?= Security::e((string) ($summary['failed'] ?? 0)) ?> ล้มเหลว · <?= Security::e((string) ($summary['total'] ?? 0)) ?> ทั้งหมด</small></div></li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<aside class="editorial-section safety-summary" aria-labelledby="restore-safety-heading">
    <span class="panel-kicker">Import protection</span>
    <h2 id="restore-safety-heading">Import ลงฐานใหม่เท่านั้น</h2>
    <p>ตรวจไฟล์และ SHA-256 ก่อนใช้ mysql.exe ระบบสร้างชื่อฐานปลายทางเองและใช้บัญชีที่มีสิทธิ์เฉพาะฐานนั้น จึงไม่เขียนทับฐานต้นทาง</p>
    <a class="secondary-action" href="index.php?page=import">เปิด Import</a>
</aside>
</div>

