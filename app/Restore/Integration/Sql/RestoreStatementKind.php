<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Sql;

enum RestoreStatementKind: string
{
    case SESSION_DIRECTIVE = 'session_directive';
    case CREATE_TABLE = 'create_table';
    case DROP_OBJECT = 'drop_object';
    case INSERT_ROWS = 'insert_rows';
    case ALTER_TABLE = 'alter_table';
    case LOCK_TABLES = 'lock_tables';
    case UNLOCK_TABLES = 'unlock_tables';
    case CREATE_PLACEHOLDER_VIEW = 'create_placeholder_view';
}
