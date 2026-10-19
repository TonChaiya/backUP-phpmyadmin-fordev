<?php

declare(strict_types=1);

namespace BackupApp\Import;

use BackupApp\Backup\MysqldumpCommand;
use mysqli;
use RuntimeException;

final class ImportPolicy
{
    /** @return array{id:string,target:string,username:string} */
    public static function names(): array
    {
        $id=bin2hex(random_bytes(16));
        return ['id'=>$id,'target'=>'local_import_'.gmdate('Ymd').'_'.substr($id,0,16),
            'username'=>'local_import_'.substr($id,16,16)];
    }

    public static function assertNames(string $target,string $username,string $id):void
    {
        if(preg_match('/^[a-f0-9]{32}$/D',$id)!==1
            || preg_match('/^local_import_[0-9]{8}_'.substr($id,0,16).'$/D',$target)!==1
            || $username!=='local_import_'.substr($id,16,16)){
            throw new RuntimeException('import_target_scope_invalid');
        }
    }

    /** @return list<string> */
    public static function expectedGrants(string $target,string $username):array
    {
        if(preg_match('/^local_import_[0-9]{8}_[a-f0-9]{16}$/D',$target)!==1
            ||preg_match('/^local_import_[a-f0-9]{16}$/D',$username)!==1){throw new RuntimeException('import_scope_invalid');}
        $schema=str_replace('_','\\_',$target);
        $account='`'.$username.'`@`localhost`';
        return ['GRANT USAGE ON *.* TO '.$account,
            'GRANT SELECT, INSERT, CREATE, REFERENCES ON `'.$schema.'`.* TO '.$account];
    }

    /** @param list<string> $actual */
    public static function assertGrants(array $actual,string $target,string $username):void
    {
        $expected=self::expectedGrants($target,$username);sort($actual);sort($expected);
        if($actual!==$expected){throw new RuntimeException('import_privilege_scope_mismatch');}
    }

    /** @param array<string,mixed> $facts */
    public static function assertIdentity(array $facts,string $username):void
    {
        if(($facts['version']??'')!==MysqldumpCommand::VERSION
            ||($facts['server_uuid']??'')!==MysqldumpCommand::SERVER_UUID
            ||(int)($facts['port']??0)!==3306||($facts['account']??'')!==$username.'@localhost'
            ||($facts['roles']??'')!=='NONE'||(string)($facts['partial_revokes']??'')!=='0'){
            throw new RuntimeException('import_server_or_account_mismatch');
        }
    }

    /** @param array<string,mixed> $job */
    public static function verifyConnection(mysqli $connection,array $job):void
    {
        self::assertNames($job['target'],$job['username'],$job['id']);
        $facts=$connection->query('SELECT @@version AS version, @@port AS port, @@server_uuid AS server_uuid, CURRENT_USER() AS account, CURRENT_ROLE() AS roles, @@global.partial_revokes AS partial_revokes')->fetch_assoc();
        self::assertIdentity(is_array($facts)?$facts:[],$job['username']);
        $result=$connection->query('SHOW GRANTS FOR CURRENT_USER()');$grants=[];
        while($row=$result->fetch_row()){$grants[]=(string)$row[0];}
        self::assertGrants($grants,$job['target'],$job['username']);
    }
}
