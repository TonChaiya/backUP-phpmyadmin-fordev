<?php

declare(strict_types=1);

use BackupApp\Support\Security;
use BackupApp\Support\View;

$backupFiles = is_array($backupFiles ?? null) ? $backupFiles : [];
?>
<section class="editorial-section history-section">
    <div class="section-heading">
        <div>
            <span class="panel-kicker">Backup Files</span>
            <h2>ประวัติไฟล์สำรอง</h2>
        </div>
        <span class="panel-badge"><?= Security::e((string) count($backupFiles)) ?> ไฟล์</span>
    </div>
    <?php if ($backupFiles === []): ?>
        <div class="empty-state"><strong>ยังไม่มีไฟล์สำรอง</strong><span>ไฟล์ที่สำรองสำเร็จจะแสดงที่นี่</span></div>
    <?php else: ?>
        <div class="history-toolbar">
            <div class="field search-field"><label for="history-search">ค้นหาไฟล์หรือฐานข้อมูล</label><input id="history-search" type="search" placeholder="ชื่อฐานข้อมูลหรือไฟล์" data-history-search></div>
            <div class="field filter-field"><label for="history-filter">Restore eligibility</label><select id="history-filter" data-history-filter><option value="all">ทั้งหมด</option><option value="candidate">ต้อง Preflight</option><option value="ineligible">ไม่ eligible</option></select></div>
        </div>
        <p class="filter-result" aria-live="polite" data-history-result><?= Security::e((string) count($backupFiles)) ?> รายการ</p>
        <div class="table-wrap" role="region" aria-label="ตารางประวัติไฟล์สำรอง" tabindex="0">
            <table class="backup-table">
                <thead><tr><th>ฐานข้อมูล / ไฟล์</th><th>วันที่ / ขนาด</th><th>Integrity</th><th>Provenance</th><th>Eligibility</th><th><span class="sr-only">การทำงาน</span></th></tr></thead>
                <tbody>
                <?php foreach ($backupFiles as $file): ?>
                    <?php $eligible = ($file['restore_status'] ?? null) === 'candidate'; ?>
                    <tr data-history-row data-search-value="<?= Security::e(strtolower((string) ($file['database'] ?? '') . ' ' . (string) $file['name'])) ?>" data-history-status="<?= $eligible ? 'candidate' : 'ineligible' ?>">
                        <td><strong class="file-name"><?= Security::e((string) ($file['database'] ?? 'ไม่ทราบ')) ?></strong><small class="table-subtext"><?= Security::e((string) $file['name']) ?></small></td>
                        <td><?= Security::e(date('Y-m-d H:i:s', (int) $file['modified'])) ?><small class="table-subtext"><?= Security::e(View::humanSize((int) $file['size'])) ?></small></td>
                        <td><span class="status-pill" data-state="neutral"><?= Security::e((string) ($file['integrity_status'] ?? 'not_checked')) ?></span><small class="table-subtext">ยังไม่ได้ตรวจ SHA-256</small></td>
                        <td><span class="status-pill" data-state="<?= ($file['provenance_status'] ?? '') === 'verified' ? 'succeeded' : 'blocked' ?>"><?= Security::e((string) ($file['provenance_status'] ?? 'unavailable')) ?></span></td>
                        <td><strong><?= $eligible ? 'ต้อง Preflight' : 'ไม่ eligible' ?></strong><small class="table-subtext"><?= Security::e((string) ($file['restore_status'] ?? 'not_checked')) ?></small></td>
                        <td class="download-cell">
                            <?php if ($eligible): ?>
                                <a class="download-link" href="index.php?page=restore&amp;artifact=<?= rawurlencode((string) $file['relative_path']) ?>">Preflight</a>
                            <?php else: ?>
                                <span>ไม่ eligible</span>
                            <?php endif; ?>
                            <a class="download-link" href="<?= Security::e((string) $file['download_url']) ?>">ดาวน์โหลด</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

