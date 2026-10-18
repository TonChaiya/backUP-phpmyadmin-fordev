<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Sql;

use BackupApp\Restore\Integration\Sql\Statement\AlterTable;
use BackupApp\Restore\Integration\Sql\Statement\CreatePlaceholderView;
use BackupApp\Restore\Integration\Sql\Statement\CreateTable;
use BackupApp\Restore\Integration\Sql\Statement\DropObject;
use BackupApp\Restore\Integration\Sql\Statement\InsertRows;
use BackupApp\Restore\Integration\Sql\Statement\LockTables;
use BackupApp\Restore\Integration\Sql\Statement\SessionDirective;
use BackupApp\Restore\Integration\Sql\Statement\UnlockTables;
use DomainException;
use BackupApp\Restore\StructuralSqlParser;

final class ExecutableStatementPolicy
{
    private const ALLOWED = [
        SessionDirective::class,
        CreateTable::class,
        CreatePlaceholderView::class,
        DropObject::class,
        InsertRows::class,
        AlterTable::class,
        LockTables::class,
        UnlockTables::class,
    ];

    public static function assertAllowed(ExecutableRestoreStatement $statement): void
    {
        self::assertAllowedSql($statement, $statement->renderForTarget());
    }

    public static function assertAllowedSql(ExecutableRestoreStatement $statement, string $capturedSql): void
    {
        if (!in_array($statement::class, self::ALLOWED, true)) {
            throw new DomainException('unrecognized_typed_statement_class');
        }
        $sql = trim($capturedSql);
        $shape = match ($statement::class) {
            SessionDirective::class => '/^\/\*![0-9]{5}\s+SET\b/i',
            CreateTable::class => '/^CREATE\s+TABLE\s+`/i',
            CreatePlaceholderView::class => '/^CREATE\s+OR\s+REPLACE\s+VIEW\s+`/i',
            DropObject::class => '/^DROP\s+(?:TABLE|VIEW|TRIGGER|PROCEDURE|FUNCTION|EVENT)\s+IF\s+EXISTS\s+`/i',
            InsertRows::class => '/^INSERT\s+INTO\s+`/i',
            AlterTable::class => '/^ALTER\s+TABLE\s+`/i',
            LockTables::class => '/^LOCK\s+TABLES\s+`/i',
            UnlockTables::class => '/^UNLOCK\s+TABLES$/i',
            default => '/(?!)^/',
        };
        if (preg_match($shape, $sql) !== 1) { throw new DomainException('typed_statement_payload_mismatch'); }
        $envelope = "CREATE DATABASE IF NOT EXISTS `autoprintdb` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
            . "USE `autoprintdb`;\n" . $sql . ";\n";
        $validation = (new StructuralSqlParser())->validate($envelope, 'autoprintdb');
        if (($validation['supported'] ?? false) !== true) { throw new DomainException('typed_statement_payload_unsafe'); }
        if ($statement instanceof InsertRows) {
            $parsed = (new InsertRowsParser())->parse($sql);
            if ($parsed->table() !== $statement->table() || $parsed->columns() !== $statement->columns()
                || count($parsed->rows()) !== $statement->rowCount()) {
                throw new DomainException('typed_insert_metadata_mismatch');
            }
        }
    }
}
