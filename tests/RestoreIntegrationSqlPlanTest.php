<?php

declare(strict_types=1);

use BackupApp\Restore\Integration\Sql\ExecutableRestoreStatement;
use BackupApp\Restore\Integration\Sql\RestorePlanCompiler;
use BackupApp\Restore\Integration\Sql\Statement\SourceDatabaseDeclaration;
use BackupApp\Restore\Integration\Sql\Statement\SourceDatabaseSelection;
use BackupApp\Restore\Sql\SqlStatementLexer;

function integrationSql(string $body): string
{
    return "-- Backup database: `autoprintdb`\n"
        . "/*!40101 SET NAMES utf8mb4 */;\n"
        . "CREATE DATABASE IF NOT EXISTS `autoprintdb` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
        . "USE `autoprintdb`;\n"
        . $body . "\n"
        . "-- Backup completed successfully\n";
}

test('RestoreIntegrationSqlPlan lexer keeps quoted semicolons inside one statement', function (): void {
    $items = iterator_to_array((new SqlStatementLexer())->stream(
        "INSERT INTO `items` (`value`) VALUES ('a;b');\nUNLOCK TABLES;"
    ));

    assertSameValue(2, count($items));
    assertContainsValue("'a;b'", $items[0]->sql());
    assertSameValue('UNLOCK TABLES', trim($items[1]->sql()));
});

test('RestoreIntegrationSqlPlan compiles source database declarations as non executable metadata', function (): void {
    $sql = integrationSql(
        "CREATE TABLE `items` (`id` int NOT NULL, `value` varchar(30)) ENGINE=InnoDB;\n"
        . "INSERT INTO `items` (`id`, `value`) VALUES ('1', 'safe');\n"
        . "ALTER TABLE `items` DISABLE KEYS;\nLOCK TABLES `items` WRITE;\nUNLOCK TABLES;"
    );
    $plan = (new RestorePlanCompiler())->compile($sql, 'autoprintdb');

    assertSameValue('autoprintdb', $plan->sourceDatabase());
    assertSameValue(strlen($sql), $plan->contentBytes());
    assertSameValue(hash('sha256', $sql), $plan->contentSha256());
    assertTrueValue($plan->items()[1] instanceof SourceDatabaseDeclaration);
    assertTrueValue($plan->items()[2] instanceof SourceDatabaseSelection);
    assertFalseValue($plan->items()[1] instanceof ExecutableRestoreStatement);
    assertFalseValue($plan->items()[2] instanceof ExecutableRestoreStatement);
    assertSameValue(['items'], $plan->objectInventory()['tables']);
});

test('RestoreIntegrationSqlPlan fails closed without returning a partial plan', function (): void {
    $compiler = new RestorePlanCompiler();
    $cases = [
        integrationSql('SELECT * FROM `other`.`secret`;'),
        integrationSql("PREPARE stmt FROM 'SELECT 1';"),
        integrationSql('CREATE DEFINER=`root`@`localhost` VIEW `v` AS SELECT 1;'),
        integrationSql('ANALYZE TABLE `items`;'),
        integrationSql('DROP DATABASE `autoprintdb`;'),
        integrationSql('CREATE TABLE `items` (`id` int, CONSTRAINT `fk` FOREIGN KEY (`id`) REFERENCES `old`.`secret` (`id`)) ENGINE=InnoDB;'),
        str_replace('USE `autoprintdb`;', 'USE `drugmap_db`;', integrationSql('UNLOCK TABLES;')),
    ];

    foreach ($cases as $sql) {
        $thrown = false;
        try {
            $compiler->compile($sql, 'autoprintdb');
        } catch (DomainException) {
            $thrown = true;
        }
        assertTrueValue($thrown, 'Unsafe SQL must be rejected');
    }
});

test('RestoreIntegrationSqlPlan rejects custom delimiter stored programs', function (): void {
    $sql = integrationSql("DELIMITER $$\nCREATE PROCEDURE `p`() BEGIN SELECT 1; END$$\nDELIMITER ;");
    $thrown = false;
    try {
        (new RestorePlanCompiler())->compile($sql, 'autoprintdb');
    } catch (DomainException) {
        $thrown = true;
    }
    assertTrueValue($thrown);
});

test('RestoreIntegrationSqlPlan compileFile streams across small chunks and rejects truncation and oversized statements', function (): void {
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'restore-stream-' . bin2hex(random_bytes(5));
    mkdir($directory, 0700, true);
    $path = $directory . DIRECTORY_SEPARATOR . 'artifact.sql';
    $sql = integrationSql(
        "CREATE TABLE `items` (`id` int NOT NULL, `value` blob NULL) ENGINE=InnoDB;\n"
        . "INSERT INTO `items` (`id`, `value`) VALUES ('1', 'chunk;boundary\\nไทย'),('2', NULL);\nUNLOCK TABLES;"
    );
    file_put_contents($path, $sql);
    $lexer = new SqlStatementLexer(1024, 17);
    $plan = (new RestorePlanCompiler($lexer, new BackupApp\Restore\StructuralSqlParser($lexer)))->compileFile($path, 'autoprintdb');
    assertSameValue(strlen($sql), $plan->contentBytes());
    assertSameValue(hash('sha256', $sql), $plan->contentSha256());
    assertSameValue(2, $plan->dataEvidence()['items']->rowCount());

    file_put_contents($path, substr($sql, 0, -5));
    $insert = array_values(array_filter($plan->items(), static fn ($item): bool => $item instanceof BackupApp\Restore\Integration\Sql\Statement\InsertRows))[0];
    assertContainsValue('chunk;boundary', $insert->renderForTarget(), 'File-backed typed payload must be independent of later artifact changes');
    $thrown = false;
    try { (new RestorePlanCompiler($lexer, new BackupApp\Restore\StructuralSqlParser($lexer)))->compileFile($path, 'autoprintdb'); }
    catch (DomainException $exception) { $thrown = true; }
    assertTrueValue($thrown, 'Truncated artifact must fail closed');

    file_put_contents($path, integrationSql("CREATE TABLE `items` (`id` int) ENGINE=InnoDB;\nINSERT INTO `items` (`id`) VALUES ('" . str_repeat('x', 1100) . "');"));
    $thrown = false;
    try { (new RestorePlanCompiler($lexer, new BackupApp\Restore\StructuralSqlParser($lexer)))->compileFile($path, 'autoprintdb'); }
    catch (DomainException $exception) { $thrown = str_contains($exception->getMessage(), 'statement_too_large'); }
    assertTrueValue($thrown, 'Oversized statement must be rejected');
});

test('RestoreIntegrationSqlPlan enforces statement limit inside quoted and commented regions', function (): void {
    foreach (["INSERT INTO `t` (`v`) VALUES ('" . str_repeat('x', 80) . "');", '/*' . str_repeat('x', 80) . '*/;'] as $sql) {
        $thrown = false;
        try { iterator_to_array((new SqlStatementLexer(20, 7))->stream($sql)); }
        catch (DomainException $exception) { $thrown = $exception->getMessage() === 'statement_too_large'; }
        assertTrueValue($thrown);
    }
});
