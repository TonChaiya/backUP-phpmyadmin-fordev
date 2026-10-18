<?php

declare(strict_types=1);

use BackupApp\Restore\Integration\Validation\PostRestoreValidator;
use BackupApp\Restore\Integration\Validation\RowCanonicalizer;
use BackupApp\Restore\Integration\Validation\RowMultisetComparator;
use BackupApp\Restore\Integration\Validation\SchemaComparator;
use BackupApp\Restore\Integration\Sql\CreateTableEvidenceParser;

test('RestoreIntegrationValidation canonical rows distinguish null empty binary and duplicate multiplicity', function (): void {
    $canonicalizer = new RowCanonicalizer();
    assertFalseValue($canonicalizer->canonicalize(['v' => null], ['v' => 'varchar']) === $canonicalizer->canonicalize(['v' => ''], ['v' => 'varchar']));
    assertFalseValue($canonicalizer->canonicalize(['v' => "\x00\xff"], ['v' => 'binary']) === $canonicalizer->canonicalize(['v' => 'AP8='], ['v' => 'varchar']));

    $comparator = new RowMultisetComparator($canonicalizer);
    $equal = $comparator->compare(
        [['id' => '1'], ['id' => '1'], ['id' => '2']],
        [['id' => '2'], ['id' => '1'], ['id' => '1']],
        ['id' => 'int']
    );
    assertTrueValue($equal['equal']);
    $different = $comparator->compare([['id' => '1'], ['id' => '1']], [['id' => '1']], ['id' => 'int']);
    assertFalseValue($different['equal']);
    $thrown = false;
    try { $canonicalizer->canonicalize([], ['v' => 'varchar']); } catch (DomainException) { $thrown = true; }
    assertTrueValue($thrown, 'Missing columns must not be treated as NULL');
});

test('RestoreIntegrationValidation schema comparison catches ordered structural differences', function (): void {
    $expected = ['tables' => ['items' => ['engine' => 'InnoDB', 'columns' => [['name' => 'id', 'type' => 'int', 'nullable' => false]]]]];
    $actual = $expected;
    assertTrueValue((new SchemaComparator())->compare($expected, $actual)['equal']);
    $actual['tables']['items']['columns'][0]['nullable'] = true;
    assertFalseValue((new SchemaComparator())->compare($expected, $actual)['equal']);
});

test('RestoreIntegrationValidation preserves whitespace inside schema string literals', function (): void {
    $parser = new CreateTableEvidenceParser();
    $twoSpaces = $parser->parse("CREATE TABLE `items` (`v` varchar(20) DEFAULT 'a  b') ENGINE=InnoDB");
    $oneSpace = $parser->parse("CREATE TABLE `items` (`v` varchar(20) DEFAULT 'a b') ENGINE=InnoDB");
    assertFalseValue($twoSpaces['definition'] === $oneSpace['definition']);
    assertContainsValue("default 'a  b'", $twoSpaces['definition']['columns'][0]['attributes']);
    $upperEnum = $parser->parse("CREATE TABLE `items` (`v` enum('A','a') NOT NULL) ENGINE=InnoDB");
    $lowerEnum = $parser->parse("CREATE TABLE `items` (`v` enum('a','a') NOT NULL) ENGINE=InnoDB");
    assertFalseValue($upperEnum['definition'] === $lowerEnum['definition']);
});

test('RestoreIntegrationValidation verifies lossless typed row evidence including duplicates and binary bytes', function (): void {
    $sql = integrationSql(
        "CREATE TABLE `items` (`id` decimal(30,10) NOT NULL, `value` blob NULL, `created` datetime(6), `note` varchar(80)) ENGINE=InnoDB;\n"
        . "INSERT INTO `items` (`id`, `value`, `created`, `note`) VALUES ('12345678901234567890.1234567890', NULL, '2026-10-17 12:34:56.123456', 'O\\'Brien; special'),"
        . "('2.0000000000', '', '2026-10-17 00:00:00.000000', ''),('3.0000000000', 'ไทย\\0\\n\\Z\\\\', '2026-10-17 12:34:56.123456', 'ไทย');\n"
        . "INSERT INTO `items` (`id`, `value`, `created`, `note`) VALUES ('3.0000000000', 'ไทย\\0\\n\\Z\\\\', '2026-10-17 12:34:56.123456', 'ไทย');\nUNLOCK TABLES;"
    );
    $plan = (new BackupApp\Restore\Integration\Sql\RestorePlanCompiler())->compile($sql, 'autoprintdb');
    $connection = new FakeRestoreConnection();
    $connection->schema = [
        'objects' => $plan->schemaEvidence(),
        'types' => ['items' => ['id' => 'decimal(30,10)', 'value' => 'longblob', 'created' => 'datetime(6)', 'note' => 'varchar(80)']],
    ];
    $binary = "ไทย\0\n" . chr(26) . "\\";
    $connection->rowsByTable['items'] = [
        ['id' => '3.0000000000', 'value' => $binary, 'created' => '2026-10-17 12:34:56.123456', 'note' => 'ไทย'],
        ['id' => '12345678901234567890.1234567890', 'value' => null, 'created' => '2026-10-17 12:34:56.123456', 'note' => "O'Brien; special"],
        ['id' => '3.0000000000', 'value' => $binary, 'created' => '2026-10-17 12:34:56.123456', 'note' => 'ไทย'],
        ['id' => '2.0000000000', 'value' => '', 'created' => '2026-10-17 00:00:00.000000', 'note' => ''],
    ];
    $report = (new PostRestoreValidator())->validate($plan, $connection);
    assertSameValue('pass', $report->areas()['data']['status']);
    assertTrueValue($report->mandatoryPassed());
    assertNotContainsValue("\x00", json_encode($report->publicView(), JSON_THROW_ON_ERROR));

    $connection->rowsByTable['items'][0]['value'] = 'changed';
    $mismatch = (new PostRestoreValidator())->validate($plan, $connection);
    assertSameValue('fail', $mismatch->areas()['data']['status']);
    assertFalseValue($mismatch->mandatoryPassed());

    $connection->rowsByTable['items'][0]['value'] = $binary;
    $connection->schema['objects']['tables']['items']['engine'] = 'MEMORY';
    assertSameValue('fail', (new PostRestoreValidator())->validate($plan, $connection)->areas()['schema']['status']);
    $connection->schema['objects'] = $plan->schemaEvidence();
    $connection->schema['objects']['tables']['items']['columns'][0]['type'] = 'varchar(255)';
    assertSameValue('fail', (new PostRestoreValidator())->validate($plan, $connection)->areas()['schema']['status']);
    $connection->schema['objects'] = $plan->schemaEvidence();
    $connection->schema['objects']['tables']['items']['columns'][0]['nullable'] = true;
    assertSameValue('fail', (new PostRestoreValidator())->validate($plan, $connection)->areas()['schema']['status']);
    $connection->schema['objects'] = $plan->schemaEvidence();
    $connection->schema['objects']['triggers']['unexpected'] = [];
    assertSameValue('fail', (new PostRestoreValidator())->validate($plan, $connection)->areas()['schema']['status']);
});

test('RestoreIntegrationValidation keeps large duplicate multisets bounded and deterministic', function (): void {
    $tuples = [];
    $rows = [];
    for ($i = 0; $i < 4200; $i++) {
        $value = (string) ($i % 113);
        $tuples[] = "('{$value}')";
        $rows[] = ['v' => $value];
    }
    $sql = integrationSql("CREATE TABLE `bulk` (`v` varchar(10)) ENGINE=InnoDB;\nINSERT INTO `bulk` (`v`) VALUES " . implode(',', $tuples) . ";\nUNLOCK TABLES;");
    $plan = (new BackupApp\Restore\Integration\Sql\RestorePlanCompiler())->compile($sql, 'autoprintdb');
    $connection = new FakeRestoreConnection();
    $connection->schema = ['objects' => $plan->schemaEvidence()];
    $connection->rowsByTable['bulk'] = array_reverse($rows);
    $temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'row-evidence-' . bin2hex(random_bytes(5));
    mkdir($temporaryDirectory, 0700, true);
    $report = (new PostRestoreValidator(evidenceChunkHashes: 97, temporaryDirectory: $temporaryDirectory))->validate($plan, $connection);
    assertSameValue('pass', $report->areas()['data']['status']);
    assertSameValue(4200, $plan->dataEvidence()['bulk']->rowCount());
    assertSameValue([], array_values(array_diff(scandir($temporaryDirectory) ?: [], ['.', '..'])));
});
