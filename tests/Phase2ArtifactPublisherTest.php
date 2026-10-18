<?php

declare(strict_types=1);

use BackupApp\Backup\BackupArtifactPublisher;

function phase2Manifest(string $filename, string $relativePath, string $contents): array
{
    return [
        'database' => 'fixture_db',
        'filename' => $filename,
        'relative_path' => $relativePath,
        'created_at' => date('c'),
        'bytes' => strlen($contents),
        'sha256' => hash('sha256', $contents),
        'complete' => true,
        'stats' => [],
    ];
}

test('Phase2ArtifactPublisher publishes and verifies SQL manifest pair', function (): void {
    $path = phase2TempDirectory('publisher-success');

    try {
        $contents = "CREATE TABLE fixture (id INT);\n-- Backup completed successfully\n";
        $temporary = $path . DIRECTORY_SEPARATOR . 'fixture.sql.tmp';
        $final = $path . DIRECTORY_SEPARATOR . 'fixture.sql';
        file_put_contents($temporary, $contents);
        $manifest = phase2Manifest('fixture.sql', 'fixture_db/fixture.sql', $contents);
        $publisher = new BackupArtifactPublisher();
        $result = $publisher->publish($temporary, $final, $manifest, $manifest['sha256']);

        assertFalseValue(is_file($temporary));
        assertTrueValue(is_file($final));
        assertTrueValue(is_file($final . '.json'));
        assertSameValue($manifest['sha256'], $result['sha256']);
        assertTrueValue($publisher->verifyPublished($final, $final . '.json'));
    } finally {
        phase2RemoveDirectory($path);
    }
});

test('Phase2ArtifactPublisher rejects incomplete or checksum-mismatched SQL and cleans current temp', function (): void {
    foreach (['missing-marker', 'trailing-data', 'wrong-hash'] as $case) {
        $path = phase2TempDirectory('publisher-' . $case);

        try {
            $contents = match ($case) {
                'missing-marker' => "SELECT 1;\n",
                'trailing-data' => "-- Backup completed successfully\nSELECT 2;\n",
                default => "SELECT 1;\n-- Backup completed successfully\n",
            };
            $temporary = $path . DIRECTORY_SEPARATOR . 'fixture.sql.tmp';
            $final = $path . DIRECTORY_SEPARATOR . 'fixture.sql';
            file_put_contents($temporary, $contents);
            $manifest = phase2Manifest('fixture.sql', 'fixture_db/fixture.sql', $contents);
            $expected = $case === 'wrong-hash' ? str_repeat('0', 64) : $manifest['sha256'];
            $failed = false;

            try {
                (new BackupArtifactPublisher())->publish($temporary, $final, $manifest, $expected);
            } catch (RuntimeException) {
                $failed = true;
            }

            assertTrueValue($failed);
            assertFalseValue(is_file($temporary));
            assertFalseValue(is_file($final));
            assertFalseValue(is_file($final . '.json'));
        } finally {
            phase2RemoveDirectory($path);
        }
    }
});

test('Phase2ArtifactPublisher leaves a pre-existing foreign manifest temp untouched', function (): void {
    $path = phase2TempDirectory('publisher-foreign-temp');

    try {
        $contents = "SELECT 1;\n-- Backup completed successfully\n";
        $temporary = $path . DIRECTORY_SEPARATOR . 'fixture.sql.tmp';
        $final = $path . DIRECTORY_SEPARATOR . 'fixture.sql';
        $foreignTemporaryManifest = $final . '.json.tmp';
        file_put_contents($temporary, $contents);
        file_put_contents($foreignTemporaryManifest, 'FOREIGN TEMP');
        $manifest = phase2Manifest('fixture.sql', 'fixture_db/fixture.sql', $contents);
        $failed = false;

        try {
            (new BackupArtifactPublisher())->publish($temporary, $final, $manifest, $manifest['sha256']);
        } catch (RuntimeException) {
            $failed = true;
        }

        assertTrueValue($failed);
        assertSameValue('FOREIGN TEMP', file_get_contents($foreignTemporaryManifest));
        assertFalseValue(is_file($temporary));
    } finally {
        phase2RemoveDirectory($path);
    }
});

test('Phase2ArtifactPublisher never overwrites an existing successful pair', function (): void {
    $path = phase2TempDirectory('publisher-existing');

    try {
        $contents = "SELECT 1;\n-- Backup completed successfully\n";
        $temporary = $path . DIRECTORY_SEPARATOR . 'fixture.sql.tmp';
        $final = $path . DIRECTORY_SEPARATOR . 'fixture.sql';
        file_put_contents($temporary, $contents);
        file_put_contents($final, 'OLD SQL');
        file_put_contents($final . '.json', 'OLD MANIFEST');
        $manifest = phase2Manifest('fixture.sql', 'fixture_db/fixture.sql', $contents);
        $failed = false;

        try {
            (new BackupArtifactPublisher())->publish($temporary, $final, $manifest, $manifest['sha256']);
        } catch (RuntimeException) {
            $failed = true;
        }

        assertTrueValue($failed);
        assertSameValue('OLD SQL', file_get_contents($final));
        assertSameValue('OLD MANIFEST', file_get_contents($final . '.json'));
        assertFalseValue(is_file($temporary));
    } finally {
        phase2RemoveDirectory($path);
    }
});

test('Phase2ArtifactPublisher rolls back only newly published SQL when manifest move fails', function (): void {
    $path = phase2TempDirectory('publisher-rollback');

    try {
        $contents = "SELECT 1;\n-- Backup completed successfully\n";
        $temporary = $path . DIRECTORY_SEPARATOR . 'fixture.sql.tmp';
        $final = $path . DIRECTORY_SEPARATOR . 'fixture.sql';
        file_put_contents($temporary, $contents);
        $manifest = phase2Manifest('fixture.sql', 'fixture_db/fixture.sql', $contents);
        $moves = 0;
        $sources = [];
        $publisher = new BackupArtifactPublisher(static function (string $source, string $target) use (&$moves, &$sources): bool {
            $moves++;
            $sources[] = basename($source);

            return $moves === 1 ? rename($source, $target) : false;
        });
        $failed = false;

        try {
            $publisher->publish($temporary, $final, $manifest, $manifest['sha256']);
        } catch (RuntimeException) {
            $failed = true;
        }

        assertTrueValue($failed);
        assertFalseValue(is_file($temporary));
        assertFalseValue(is_file($final));
        assertFalseValue(is_file($final . '.json'));
        assertFalseValue(is_file($final . '.json.tmp'));
        assertSameValue(['fixture.sql.json.tmp', 'fixture.sql.tmp'], $sources);
    } finally {
        phase2RemoveDirectory($path);
    }
});
