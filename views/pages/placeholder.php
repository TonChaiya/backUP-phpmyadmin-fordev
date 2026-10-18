<?php

declare(strict_types=1);

use BackupApp\Support\Security;
?>
<section class="content-section placeholder-section">
    <span class="panel-kicker">Foundation</span>
    <h2><?= Security::e((string) ($placeholderTitle ?? 'ยังไม่เปิดใช้งาน')) ?></h2>
    <p><?= Security::e((string) ($placeholderMessage ?? 'ฟังก์ชันนี้อยู่นอกขอบเขต Phase 1')) ?></p>
</section>

