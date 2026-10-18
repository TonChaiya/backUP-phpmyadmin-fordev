<?php

declare(strict_types=1);

use BackupApp\Support\Security;

$restoreCandidates = is_array($restoreCandidates ?? null) ? $restoreCandidates : [];
$selectedRestoreArtifact = is_string($selectedRestoreArtifact ?? null) ? $selectedRestoreArtifact : '';
$restoreSafety = is_array($restoreSafety ?? null) ? $restoreSafety : ['open' => false, 'checks' => []];
$recentRestoreJobs = is_array($recentRestoreJobs ?? null) ? $recentRestoreJobs : [];
?>
<section class="status-strip restore-safety" role="status" aria-labelledby="restore-disabled-title">
    <div class="safety-banner-icon" aria-hidden="true">!</div>
    <div><span class="panel-kicker">Fail-closed architecture</span><h2 id="restore-disabled-title">Restore execution ปิดอยู่ — ไม่มีระบบประมวลผล SQL</h2><p>หน้านี้ตรวจสอบ artifact, provenance, SHA-256, SQL structure และ target เท่านั้น ไม่มีการเชื่อมต่อหรือส่งคำสั่งไปยังฐานข้อมูล</p></div>
</section>

<section class="gate-grid" aria-label="Restore safety gates">
    <?php foreach (($restoreSafety['checks'] ?? []) as $name => $value): ?>
        <article class="gate-item"><span><?= Security::e(str_replace('_', ' ', (string) $name)) ?></span><strong class="status-pill" data-state="blocked"><?= Security::e((string) $value) ?></strong></article>
    <?php endforeach; ?>
</section>

<div class="workflow-sequence">
    <section class="editorial-section restore-preflight" aria-labelledby="restore-preflight-title">
        <div class="section-heading">
            <div>
                <span class="panel-kicker">Fail-Closed Validation</span>
                <h2 id="restore-preflight-title">ตรวจสอบก่อน Restore</h2>
            </div>
            <span class="panel-badge">Execution ปิดอยู่</span>
        </div>
        <form class="section-form" id="restore-preflight-form" method="post" action="restore-job.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= Security::e((string) $csrfToken) ?>">
            <div class="field">
                <label for="restore-artifact">Backup artifact ที่มี Phase 2 provenance</label>
                <select id="restore-artifact" name="artifact" required>
                    <option value="">เลือกไฟล์</option>
                    <?php foreach ($restoreCandidates as $candidate): ?>
                        <option value="<?= Security::e((string) $candidate['relative_path']) ?>"<?= hash_equals((string) $candidate['relative_path'], $selectedRestoreArtifact) ? ' selected' : '' ?>>
                            <?= Security::e((string) $candidate['relative_path']) ?> (<?= Security::e((string) $candidate['status']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="restore-target">ชื่อฐานทดลองปลายทาง</label>
                <input id="restore-target" name="target" type="text" maxlength="64" pattern="[A-Za-z0-9_]+" required>
                <small>ระบบตรวจชื่อและไฟล์เท่านั้น ไม่เชื่อมต่อหรือแก้ไขฐานข้อมูล</small>
            </div>
            <button class="primary-action" type="submit">สร้างงานตรวจสอบ Preflight</button>
            <noscript><div class="flash warning">Restore preflight UI ต้องใช้ JavaScript และจะไม่ทำงานอัตโนมัติ</div></noscript>
        </form>
    </section>

    <section
        class="editorial-section restore-findings"
        aria-labelledby="restore-findings-title"
        data-restore-jobs
        data-job-api="restore-job.php"
        data-csrf-token="<?= Security::e((string) $csrfToken) ?>"
        data-active-job-id="<?= Security::e((string) ($activeRestoreJobId ?? '')) ?>"
    >
        <div class="section-heading">
            <div><span class="panel-kicker">Preflight State</span><h2 id="restore-findings-title">ผลการตรวจสอบ</h2></div>
            <strong class="job-state" data-restore-state>ยังไม่มีงาน</strong>
        </div>
        <div class="job-status-body">
            <p class="job-message" data-restore-message aria-live="polite">งานที่ผ่านจะจบสถานะ blocked จนกว่าจะมีระบบทดสอบที่แยกขาดและสิทธิ์เฉพาะ</p>
            <ul class="job-database-list" data-restore-findings></ul>
            <button class="secondary-action" type="button" data-restore-resume hidden>เริ่มตรวจสอบไฟล์</button>
        </div>
    </section>
</div>

<section class="editorial-section restore-history" aria-labelledby="restore-history-title">
    <div class="section-heading"><div><span class="panel-kicker">Preflight history</span><h2 id="restore-history-title">การตรวจสอบล่าสุดใน Session</h2></div><span class="panel-badge"><?= Security::e((string) count($recentRestoreJobs)) ?> งาน</span></div>
    <?php if ($recentRestoreJobs === []): ?>
        <div class="empty-state"><strong>ยังไม่มี Preflight history</strong><span>เลือก artifact และฐานทดลองเพื่อเริ่มการตรวจ โดยไม่มีการส่งคำสั่งไปยังฐานข้อมูล</span></div>
    <?php else: ?>
        <ul class="activity-list">
        <?php foreach ($recentRestoreJobs as $job): ?>
            <li><div><strong><?= Security::e((string) ($job['artifact'] ?? '')) ?></strong><span>Target: <?= Security::e((string) ($job['target'] ?? '')) ?> · <?= Security::e((string) ($job['updated_at'] ?? '')) ?></span></div><div class="activity-result"><span class="status-pill" data-state="<?= Security::e((string) ($job['status'] ?? 'queued')) ?>"><?= Security::e((string) ($job['status'] ?? 'queued')) ?></span><?php if (is_array($job['result'] ?? null)): ?><small><?= Security::e((string) ($job['result']['message'] ?? '')) ?></small><?php endif; ?></div></li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
