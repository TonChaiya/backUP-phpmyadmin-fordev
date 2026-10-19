<?php
declare(strict_types=1);
use BackupApp\Support\Security;
$importArtifacts=is_array($importArtifacts??null)?$importArtifacts:[];
?>
<div class="workflow-sequence" data-local-import data-api="import-job.php" data-csrf="<?= Security::e((string)$csrfToken) ?>" data-active-job="<?= Security::e((string)($activeImportJobId??'')) ?>">
<section class="editorial-section" aria-labelledby="import-artifact-title">
    <div class="section-heading"><h2 id="import-artifact-title">เลือกไฟล์ Export</h2><span class="panel-badge">mysql 8.4.3</span></div>
    <p>ใช้ official mysql.exe รับเฉพาะไฟล์จาก mysqldump profile ของแอปที่มี job provenance ระบบจะตรวจ manifest, SHA-256 และขนาดจริงก่อน Import ไฟล์เก่าและไฟล์จากภายนอกที่ไม่ยืนยันรูปแบบจะไม่แสดง</p>
    <form data-import-prepare action="import-job.php" method="post" class="section-form">
        <input type="hidden" name="csrf_token" value="<?= Security::e((string)$csrfToken) ?>">
        <input type="hidden" name="action" value="prepare">
        <div class="field"><label for="import-artifact">ไฟล์ SQL ที่มี provenance</label><select id="import-artifact" name="artifact" required>
            <option value="">เลือกไฟล์</option>
            <?php foreach($importArtifacts as $artifact): ?><option value="<?= Security::e($artifact['relative_path']) ?>"<?= ($selectedImportArtifact??'')===$artifact['relative_path']?' selected':'' ?>><?= Security::e($artifact['database'].' · '.$artifact['relative_path'].' · '.$artifact['bytes'].' bytes') ?></option><?php endforeach; ?>
        </select></div>
        <?php if($importArtifacts===[]): ?><p class="empty-state">ยังไม่มีไฟล์ที่รองรับ <a href="index.php?page=backup">ไปหน้า Export เพื่อสร้าง Backup</a> ด้วย mysqldump ก่อน</p><?php endif; ?>
        <button class="primary-action" type="submit"<?= $importArtifacts===[]?' disabled':'' ?>>ตรวจไฟล์และเตรียมฐานใหม่</button>
    </form>
</section>
<section class="editorial-section" aria-labelledby="import-confirm-title">
    <div class="section-heading"><h2 id="import-confirm-title">ฐานปลายทางและการยืนยัน</h2><strong class="job-state" data-import-state>ยังไม่ได้เตรียมงาน</strong></div>
    <p>ชื่อฐานใหม่สร้างโดยระบบ ไม่เขียนทับฐานเดิม ต้องใช้บัญชี Import ที่มีสิทธิ์เฉพาะฐานนี้ ไม่ใช้ backup_reader หรือ root สำหรับ Import และจะไม่ลบฐานปลายทางอัตโนมัติ</p>
    <p>รหัส MySQL ไม่ใช่รหัสผ่านเว็บไซต์ เว็บนี้ไม่ต้อง Login จาก localhost หากไม่มีรหัสบัญชีเฉพาะงาน ให้ติดต่อผู้ดูแล ระบบไม่สร้างบัญชี เพิ่มสิทธิ์ หรือรีเซ็ตรหัสผ่านให้เอง</p>
    <dl class="definition-list" data-import-details></dl>
    <p data-import-readiness>เตรียมงานเพื่อดูชื่อฐานและบัญชีที่ต้องอนุมัติ provisioning ก่อนใช้งานจริง</p>
    <form data-import-execute action="import-job.php" method="post" class="section-form" hidden autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= Security::e((string)$csrfToken) ?>">
        <input type="hidden" name="action" value="execute"><input type="hidden" name="job_id" value="">
        <div class="field"><label for="import-password">รหัสผ่านบัญชี Import เฉพาะงานนี้</label><input id="import-password" name="import_password" type="password" autocomplete="off" required></div>
        <div class="field"><label for="import-confirmation">พิมพ์ IMPORT เพื่อยืนยันฐานใหม่ที่แสดงด้านบน</label><input id="import-confirmation" name="confirmation" pattern="IMPORT" required autocomplete="off"></div>
        <button type="submit" class="primary-action">ยืนยันและ Import</button>
    </form>
    <p class="job-message" data-import-message role="status" aria-live="polite"></p>
</section>
<section class="editorial-section" aria-labelledby="import-result-title">
    <div class="section-heading"><h2 id="import-result-title">ผลการทำงานและการตรวจสอบ</h2></div>
    <p>Import เสร็จ ไม่ได้แปลว่าตรวจข้อมูลครบทุกด้าน การเทียบจำนวนแถวไม่ใช่การตรวจค่าทุกค่า และผลเทียบ Live Source ไม่ใช่ผลเทียบ SQL Snapshot เดิม ปัจจุบันยังไม่รองรับการพิสูจน์ค่า Snapshot อย่างอิสระ จึงไม่แสดงว่า Fully verified</p>
    <dl class="definition-list" data-import-result aria-live="polite">
        <div><dt>Import execution</dt><dd data-import-execution>ยังไม่มีผล</dd></div>
        <div><dt>Schema verified</dt><dd data-import-schema>ยังไม่มีผล</dd></div>
        <div><dt>Data / Live Source</dt><dd data-import-live-data>ยังไม่มีผล</dd></div>
        <div><dt>Data / SQL Snapshot</dt><dd data-import-snapshot-data>NOT VERIFIED — ยังไม่ยืนยัน</dd></div>
        <div><dt>Verification</dt><dd data-import-verification>รอผลตรวจ</dd></div>
    </dl>
    <p class="table-subtext" data-import-review-note>ผลตรวจเพิ่มเติมเป็นหลักฐาน ณ เวลาที่ตรวจ ไม่เปลี่ยนประวัติสถานะงานเดิม</p>
    <noscript><p class="empty-state">ต้องเปิด JavaScript เพื่อแสดงสถานะงาน ไม่มีการเริ่ม Import อัตโนมัติ</p></noscript>
</section>
</div>
