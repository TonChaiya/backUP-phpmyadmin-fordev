<?php

declare(strict_types=1);

use BackupApp\Support\Security;

$sources = is_array($authorizedSources ?? null) ? $authorizedSources : [];
?>
<section class="status-strip integration-safety" role="status" data-live_execution_enabled="false">
    <div class="safety-banner-icon" aria-hidden="true">!</div>
    <div><span class="panel-kicker">Offline implementation only</span><h2>Live Restore ถูกปิดตาย</h2><p>หน้านี้สร้าง proposal และแสดงสถานะไฟล์เท่านั้น ไม่มี mysqli connection, SQL execution หรือ database cleanup</p></div>
</section>
<div class="workflow-sequence">
    <section class="editorial-section integration-proposal" aria-labelledby="integration-proposal-title">
        <div class="section-heading"><div><span class="panel-kicker">Exact scope</span><h2 id="integration-proposal-title">Restore Integration Proposal</h2></div><span class="panel-badge">Offline</span></div>
        <form class="section-form" method="post" action="restore-integration.php" data-integration-proposal>
            <input type="hidden" name="csrf_token" value="<?= Security::e((string) $csrfToken) ?>">
            <input type="hidden" name="action" value="proposal">
            <ul class="job-database-list">
                <?php foreach ($sources as $source): ?><li><strong><?= Security::e((string) $source) ?></strong><span>authorized source · no connection</span></li><?php endforeach; ?>
            </ul>
            <button class="primary-action" type="submit">สร้าง Offline Proposal</button>
        </form>
    </section>
    <section class="editorial-section integration-readiness" aria-labelledby="integration-readiness-title" data-restore-integration data-api="restore-integration.php" data-csrf-token="<?= Security::e((string) $csrfToken) ?>" data-job-id="<?= Security::e((string) ($activeIntegrationJobId ?? '')) ?>">
        <div class="section-heading"><div><span class="panel-kicker">Safety state</span><h2 id="integration-readiness-title">Isolation Readiness</h2></div><strong class="job-state" data-integration-state>blocked</strong></div>
        <div class="job-status-body"><p data-integration-message aria-live="polite">Milestones 10–11 ยังไม่ได้รับอนุญาต</p></div>
    </section>
</div>
