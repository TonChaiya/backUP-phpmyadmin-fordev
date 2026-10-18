<?php

declare(strict_types=1);

use BackupApp\Restore\RestoreSafetyGate;
use BackupApp\Restore\RestoreTargetValidator;
use BackupApp\Restore\StructuralSqlParser;

function phase3Sql(string $body): string
{
    return "-- Backup database: `safe_db`\n"
        . "/*!40101 SET NAMES utf8mb4 */;\n"
        . "CREATE DATABASE IF NOT EXISTS `safe_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
        . "USE `safe_db`;\n"
        . $body . "\n"
        . "-- Backup completed successfully\n";
}

test('Phase3SqlSafety accepts only the supported single-schema dump envelope', function (): void {
    $parser = new StructuralSqlParser();
    $result = $parser->validate(phase3Sql("CREATE TABLE `items` (`id` int NOT NULL) ENGINE=InnoDB;\nINSERT INTO `items` (`id`) VALUES ('1');"), 'safe_db');
    assertTrueValue($result['supported']);
    assertSameValue('safe_db', $result['database']);
    assertSameValue([], $result['findings']);
});

test('Phase3SqlSafety handles generated delimiters and does not treat data strings as commands', function (): void {
    $body = "INSERT INTO `items` (`text`) VALUES ('DEFINER OUTFILE PREPARE /*!50000 harmless */');\n"
        . "CREATE OR REPLACE VIEW `placeholder` AS SELECT 1 AS `id`;";
    $result = (new StructuralSqlParser())->validate(phase3Sql($body), 'safe_db');
    assertTrueValue($result['supported']);
});

test('Phase3SqlSafety fails closed for scope, dynamic, definer, file, and unknown constructs', function (): void {
    $parser = new StructuralSqlParser();
    $cases = [
        'qualified' => 'SELECT * FROM `other`.`items`;',
        'dynamic' => "PREPARE stmt FROM 'SELECT 1';",
        'definer' => 'CREATE DEFINER=`root`@`localhost` VIEW `v` AS SELECT 1;',
        'drop-db' => 'DROP DATABASE `safe_db`;',
        'outfile' => "SELECT 1 INTO OUTFILE 'x';",
        'unknown' => 'ANALYZE TABLE `items`;',
        'mismatch-use' => 'USE `other`;',
        'comment-qualified' => 'CREATE VIEW `v` AS SELECT * FROM `other`/**/.`items`;',
        'mixed-qualified' => 'CREATE VIEW `v` AS SELECT * FROM other.`items`;',
        'comment-drop-db' => "DELIMITER $$\nCREATE PROCEDURE `p`() BEGIN DROP/**/DATABASE `safe_db`; END$$\nDELIMITER ;",
        'embedded-executable-comment' => "DELIMITER $$\nCREATE PROCEDURE `p`() BEGIN DROP/*!50000 */DATABASE `safe_db`; END$$\nDELIMITER ;",
        'malicious-version-set' => '/*!50000 SET @x=1; DROP DATABASE `safe_db` */;',
        'unsafe-version-global' => "/*!50000 SET GLOBAL general_log_file='/tmp/owned' */;",
        'mariadb-executable-comment' => "DELIMITER $$\nCREATE PROCEDURE `p`() BEGIN /*M! DROP DATABASE other */ SELECT 1; END$$\nDELIMITER ;",
        'hidden-second-statement' => 'CREATE TABLE `ok` (`id` int); USE `other`;',
        'hidden-after-comment' => "CREATE TABLE `ok` (`id` int); /* trailing */\nUSE `other`;",
        'global-set' => "SET GLOBAL general_log_file='/tmp/owned';",
        'persist-set' => 'SET PERSIST general_log=ON;',
        'discard-tablespace' => 'ALTER TABLE `items` DISCARD TABLESPACE;',
        'data-directory' => "CREATE TABLE `items` (`id` int) DATA DIRECTORY='/tmp/x';",
        'federated-connection' => "CREATE TABLE `items` (`id` int) ENGINE=FEDERATED CONNECTION='mysql://u:p@host/db/t';",
        'load-file-view' => "CREATE OR REPLACE VIEW `v` AS SELECT LOAD_FILE('/etc/passwd') AS `payload`;",
        'stored-program-not-provable' => "DELIMITER $$\nCREATE PROCEDURE `p`() BEGIN SELECT 1; END$$\nDELIMITER ;",
        'real-view-not-provable' => 'CREATE OR REPLACE VIEW `v` AS SELECT `id` FROM `items`;',
        'duplicate-engine' => "CREATE TABLE `t` (`id` int) ENGINE=InnoDB ENGINE=SPIDER COMMENT='wrapper mysql';",
        'engine-looking-column' => 'CREATE TABLE `t` (`ENGINE=InnoDB` int);',
        'engine-looking-column-spider' => "CREATE TABLE `t` (`ENGINE=InnoDB` int) ENGINE SPIDER COMMENT='wrapper mysql';",
        'insert-tail' => 'INSERT INTO `items` (`id`) VALUES (1) ON DUPLICATE KEY UPDATE `id`=SLEEP(3600);',
    ];
    foreach ($cases as $name => $statement) {
        $result = $parser->validate(phase3Sql($statement), 'safe_db');
        assertFalseValue($result['supported'], "{$name} must be rejected");
        assertTrueValue($result['findings'] !== [], "{$name} requires a finding");
    }
});

test('Phase3SqlSafety file pass hashes the exact bytes it structurally validates', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'phase3-parser-');
    assertTrueValue(is_string($path));
    try {
        $sql = phase3Sql("CREATE TABLE `items` (`id` int NOT NULL) ENGINE=InnoDB;");
        file_put_contents($path, $sql);
        $result = (new StructuralSqlParser())->validateFile($path, 'safe_db');
        assertTrueValue($result['supported']);
        assertSameValue(strlen($sql), $result['content_bytes']);
        assertSameValue(hash('sha256', $sql), $result['content_sha256']);
    } finally {
        @unlink($path);
    }
});

test('Phase3SqlSafety rejects malformed delimiter state', function (): void {
    $result = (new StructuralSqlParser())->validate(phase3Sql("DELIMITER $$\nCREATE PROCEDURE `p`() BEGIN SELECT 1; END;"), 'safe_db');
    assertFalseValue($result['supported']);
    assertTrueValue(in_array('unterminated_delimiter', $result['findings'], true));
});

test('Phase3SqlSafety target policy rejects source, system, jhcisdb, and unsafe identifiers', function (): void {
    $validator = new RestoreTargetValidator(['mysql', 'sys', 'information_schema', 'performance_schema']);
    assertTrueValue($validator->validate('restore_fixture', 'safe_db')['valid']);
    foreach (['safe_db', 'MYSQL', 'jhcisdb', 'bad-name', '', str_repeat('a', 65)] as $target) {
        assertFalseValue($validator->validate($target, 'safe_db')['valid'], "{$target} must be rejected");
    }
});

test('Phase3SqlSafety runtime gate is hard closed with all attestations not verified', function (): void {
    $result = (new RestoreSafetyGate())->evaluate();
    assertFalseValue($result['open']);
    assertSameValue('not_verified', $result['checks']['isolated_instance']);
    assertSameValue('not_verified', $result['checks']['dedicated_credentials']);
    assertSameValue('not_verified', $result['checks']['target_privileges']);
    assertSameValue('not_verified', $result['checks']['integration_test']);
});
