<?php

declare(strict_types=1);

use BackupApp\Backup\BackupJobStore;
use BackupApp\Restore\BackupProvenanceIndex;
use BackupApp\Restore\RestoreArtifactValidator;

/** @return array{root: string, jobs: string, relative: string, sql: string, manifest: array<string, mixed>} */
function phase3ArtifactFixture(string $suffix, bool $withProvenance = true): array
{
    $root = phase2TempDirectory('phase3-artifact-' . $suffix);
    $backupRoot = $root . DIRECTORY_SEPARATOR . 'backups';
    $jobRoot = $root . DIRECTORY_SEPARATOR . 'jobs';
    $folder = $backupRoot . DIRECTORY_SEPARATOR . 'safe_db';
    mkdir($folder, 0750, true);
    mkdir($jobRoot, 0750, true);
    $relative = 'safe_db/fixture.sql';
    $sqlPath = $folder . DIRECTORY_SEPARATOR . 'fixture.sql';
    $sql = "-- Backup database: `safe_db`\n"
        . "CREATE DATABASE IF NOT EXISTS `safe_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
        . "USE `safe_db`;\n"
        . "CREATE TABLE `items` (`id` int NOT NULL) ENGINE=InnoDB;\n"
        . "INSERT INTO `items` (`id`) VALUES ('1');\n"
        . "-- Backup completed successfully\n";
    file_put_contents($sqlPath, $sql);
    $manifest = [
        'database' => 'safe_db',
        'filename' => 'fixture.sql',
        'relative_path' => $relative,
        'created_at' => '2026-10-17T00:00:00+07:00',
        'bytes' => strlen($sql),
        'sha256' => hash('sha256', $sql),
        'complete' => true,
        'stats' => [
            'tables' => 1,
            'views' => 0,
            'triggers' => 0,
            'procedures' => 0,
            'functions' => 0,
            'events' => 0,
            'foreign_keys' => 0,
            'auto_increment_tables' => 0,
            'myisam_lock_sections' => 0,
            'rows' => 1,
        ],
        'restore_order' => RestoreArtifactValidator::RESTORE_ORDER,
    ];
    file_put_contents($sqlPath . '.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    if ($withProvenance) {
        $store = new BackupJobStore($jobRoot);
        $job = $store->create(['safe_db']);
        $store->update($job['id'], static function (array $state) use ($manifest): array {
            $state['status'] = 'succeeded';
            $state['databases'][0]['status'] = 'succeeded';
            $state['databases'][0]['artifact'] = [
                'filename' => $manifest['filename'],
                'relative_path' => $manifest['relative_path'],
                'bytes' => $manifest['bytes'],
                'sha256' => $manifest['sha256'],
                'manifest_path' => $manifest['relative_path'] . '.json',
            ];
            return $state;
        });
    }

    return ['root' => $backupRoot, 'jobs' => $jobRoot, 'relative' => $relative, 'sql' => $sqlPath, 'manifest' => $manifest];
}

test('Phase3ArtifactValidation quick inspection never trusts or hashes SQL content', function (): void {
    $fixture = phase3ArtifactFixture('quick');
    try {
        $validator = new RestoreArtifactValidator($fixture['root'], new BackupProvenanceIndex($fixture['jobs']));
        $before = $validator->inspect($fixture['relative']);
        assertSameValue('candidate', $before['status']);
        assertFalseValue($before['integrity_verified']);

        $contents = (string) file_get_contents($fixture['sql']);
        file_put_contents($fixture['sql'], str_replace("VALUES ('1')", "VALUES ('9')", $contents));
        $quick = $validator->inspect($fixture['relative']);
        assertSameValue('candidate', $quick['status'], 'Same-size content change must not trigger hashing in History');
        assertFalseValue($quick['integrity_verified']);

        $full = $validator->preflight($fixture['relative']);
        assertFalseValue($full['eligible']);
        assertSameValue('integrity_mismatch', $full['status']);
    } finally {
        phase2RemoveDirectory(dirname($fixture['root']));
    }
});

test('Phase3ArtifactValidation requires exact Phase2 succeeded provenance without backfill', function (): void {
    $fixture = phase3ArtifactFixture('legacy', false);
    try {
        $validator = new RestoreArtifactValidator($fixture['root'], new BackupProvenanceIndex($fixture['jobs']));
        assertSameValue('unverified_origin', $validator->inspect($fixture['relative'])['status']);
        $full = $validator->preflight($fixture['relative']);
        assertFalseValue($full['eligible']);
        assertSameValue('unverified_origin', $full['status']);
        assertSameValue([], glob($fixture['jobs'] . DIRECTORY_SEPARATOR . '*.jsonl') ?: [], 'Validator must not backfill provenance');
    } finally {
        phase2RemoveDirectory(dirname($fixture['root']));
    }
});

test('Phase3ArtifactValidation fully verifies current artifact and rejects missing final marker', function (): void {
    $fixture = phase3ArtifactFixture('full');
    try {
        $validator = new RestoreArtifactValidator($fixture['root'], new BackupProvenanceIndex($fixture['jobs']));
        $valid = $validator->preflight($fixture['relative']);
        assertTrueValue($valid['eligible']);
        assertSameValue('verified', $valid['status']);
        assertTrueValue($valid['integrity_verified']);
        assertSameValue('safe_db', $valid['database']);

        $sql = str_replace("-- Backup completed successfully\n", "-- incomplete backup marker padding\n", (string) file_get_contents($fixture['sql']));
        file_put_contents($fixture['sql'], $sql);
        $manifest = $fixture['manifest'];
        $manifest['bytes'] = strlen($sql);
        $manifest['sha256'] = hash('sha256', $sql);
        file_put_contents($fixture['sql'] . '.json', json_encode($manifest));
        $invalid = $validator->preflight($fixture['relative']);
        assertFalseValue($invalid['eligible']);
        assertSameValue('completion_marker_missing', $invalid['status']);
    } finally {
        phase2RemoveDirectory(dirname($fixture['root']));
    }
});

test('Phase3ArtifactValidation rejects coercive or incomplete manifest schemas', function (): void {
    $fixture = phase3ArtifactFixture('manifest-schema');
    try {
        $manifest = $fixture['manifest'];
        $manifest['bytes'] = (string) $manifest['bytes'];
        $manifest['created_at'] = 'tomorrow';
        $manifest['stats']['rows'] = '1';
        file_put_contents($fixture['sql'] . '.json', json_encode($manifest));
        $result = (new RestoreArtifactValidator($fixture['root'], new BackupProvenanceIndex($fixture['jobs'])))->preflight($fixture['relative']);
        assertFalseValue($result['eligible']);
        assertSameValue('invalid_metadata', $result['status']);
    } finally {
        phase2RemoveDirectory(dirname($fixture['root']));
    }
});
