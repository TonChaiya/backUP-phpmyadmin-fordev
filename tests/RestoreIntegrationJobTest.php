<?php

declare(strict_types=1);

use BackupApp\Restore\Integration\Job\RestoreIntegrationJobAccess;
use BackupApp\Restore\Integration\Job\RestoreIntegrationJobGuard;
use BackupApp\Restore\Integration\Job\RestoreIntegrationJobStore;
use BackupApp\Restore\Integration\Policy\RestoreIntegrationPolicy;
use BackupApp\Restore\Integration\Policy\TrustedRunId;

function integrationJobFixture(): array
{
    $policy = new RestoreIntegrationPolicy();
    $run = new TrustedRunId('0123456789abcdef0123456789abcdef');
    $items = [];
    foreach ($policy->authorizedSources() as $source) {
        $items[] = ['source' => $source, 'artifact' => "{$source}.sql", 'artifact_sha256' => str_repeat('a', 64), 'target' => $policy->targetFor($source, $run)->value()];
    }
    return [$run, $items];
}

test('RestoreIntegrationJob persists four ordered items and safe states', function (): void {
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'restore-job-' . bin2hex(random_bytes(6));
    [$run, $items] = integrationJobFixture();
    $store = new RestoreIntegrationJobStore($path);
    $job = $store->create($run, $items);
    assertSameValue('queued', $job['status']);
    assertSameValue(['autoprintdb', 'drugmap_db', 'drug_stock_check', 'prepack_labels'], array_column($job['items'], 'source'));
    $job = $store->update($job['id'], static function (array $state): array { $state['status'] = 'validating'; return $state; });
    assertSameValue('validating', $job['status']);
    assertSameValue(2, $job['revision']);
});

test('RestoreIntegrationJob rejects illegal transitions and premature success', function (): void {
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'restore-job-' . bin2hex(random_bytes(6));
    [$run, $items] = integrationJobFixture();
    $store = new RestoreIntegrationJobStore($path);
    $job = $store->create($run, $items);
    foreach (['succeeded', 'running'] as $status) {
        $thrown = false;
        try { $store->update($job['id'], static function (array $state) use ($status): array { $state['status'] = $status; return $state; }); }
        catch (DomainException) { $thrown = true; }
        assertTrueValue($thrown, "queued to {$status} must fail");
    }
});

test('RestoreIntegrationJob repairs an incomplete trailing record before appending', function (): void {
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'restore-job-' . bin2hex(random_bytes(6));
    [$run, $items] = integrationJobFixture();
    $store = new RestoreIntegrationJobStore($path);
    $job = $store->create($run, $items);
    file_put_contents($store->pathFor($job['id']), '{"partial":', FILE_APPEND);
    $updated = $store->update($job['id'], static function (array $state): array { $state['status'] = 'validating'; return $state; });
    assertSameValue(2, $updated['revision']);
    assertSameValue('validating', $store->read($job['id'])['status']);
});

test('RestoreIntegrationJob guard and session ownership reject duplicates and hide secrets', function (): void {
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'restore-job-' . bin2hex(random_bytes(6));
    [$run, $items] = integrationJobFixture();
    $store = new RestoreIntegrationJobStore($path);
    $job = $store->create($run, $items);
    $guardA = new RestoreIntegrationJobGuard($path);
    $guardB = new RestoreIntegrationJobGuard($path);
    $handle = $guardA->acquireJob($job['id']);
    assertTrueValue(is_resource($handle));
    assertSameValue(null, $guardB->acquireJob($job['id']));
    $guardA->release($handle);

    $_SESSION = [];
    $access = new RestoreIntegrationJobAccess();
    $access->register($job['id']);
    assertTrueValue($access->owns($job['id']));
    $public = $access->publicView(array_merge($job, ['password' => 'SECRET', 'sql' => 'DROP DATABASE x']));
    $json = json_encode($public, JSON_THROW_ON_ERROR);
    assertNotContainsValue('SECRET', $json);
    assertNotContainsValue('DROP DATABASE', $json);
});
