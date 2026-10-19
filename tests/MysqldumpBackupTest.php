<?php

declare(strict_types=1);

use BackupApp\Backup\MysqldumpCommand;
use BackupApp\Backup\MysqldumpProcess;
use BackupApp\Backup\TemporaryMysqlCredentials;

test('Mysqldump command pins executable and excludes privileged objects', static function (): void {
    $args = (new MysqldumpCommand())->arguments('autoprintdb', 'C:\\private\\client.cnf');
    assertSameValue(MysqldumpCommand::BINARY, $args[0]);
    assertSameValue('--defaults-file=C:\\private\\client.cnf', $args[1]);
    assertSameValue('autoprintdb', end($args));
    foreach (['--no-login-paths','--single-transaction','--quick','--skip-lock-tables', '--no-tablespaces', '--set-gtid-purged=OFF', '--skip-triggers', '--skip-events', '--skip-routines', '--hex-blob'] as $option) {
        assertTrueValue(in_array($option, $args, true));
    }
    assertNotContainsValue('--password', implode(' ', $args));
    assertNotContainsValue('--all-databases', implode(' ', $args));
});

test('Mysqldump installed binary accepts every profile option without connecting', static function (): void {
    $out=tmpfile();$err=tmpfile();
    try {
        (new MysqldumpCommand())->verifyBinary();
        $exit=(new MysqldumpProcess())->run([MysqldumpCommand::BINARY,'--no-defaults',...MysqldumpCommand::OPTIONS,'--help'],$out,$err,10,262144,65536);
        assertSameValue(0,$exit,'Installed mysqldump rejected the fixed options profile');
        assertSameValue(0,fstat($err)['size']);
    }finally{fclose($out);fclose($err);}
});

test('Mysqldump rejects unauthorized sources before command construction', static function (): void {
    foreach (['jhcisdb', 'unknown', '--all-databases', 'autoprintdb '] as $source) {
        try {
            (new MysqldumpCommand())->arguments($source, 'C:\\private\\client.cnf');
            throw new RuntimeException('unexpected accepted source');
        } catch (DomainException) {}
    }
});

test('Mysqldump connection policy rejects endpoint and runtime identity drift', static function (): void {
    foreach ([['127.0.0.1',3333,'backup_reader'], ['localhost',3306,'backup_reader'], ['remote',3306,'backup_reader'], ['127.0.0.1',3306,'root']] as [$host,$port,$username]) {
        try {
            MysqldumpCommand::assertConnection(compact('host','port','username'));
            throw new LogicException('drift accepted');
        } catch (RuntimeException) {}
    }
    MysqldumpCommand::assertConnection(['host'=>'127.0.0.1','port'=>3306,'username'=>'backup_reader']);
});

test('Mysqldump server identity rejects UUID version account role and port mismatch', static function (): void {
    $valid=['version'=>'8.4.3','server_uuid'=>MysqldumpCommand::SERVER_UUID,'port'=>3306,'account'=>'backup_reader@localhost','roles'=>'NONE','partial_revokes'=>'0'];
    MysqldumpCommand::assertServer($valid);
    foreach(['version'=>'8.4.4','server_uuid'=>'other','port'=>3333,'account'=>'root@localhost','roles'=>'active-role','partial_revokes'=>'1'] as $field=>$value){
        try{MysqldumpCommand::assertServer(array_replace($valid,[$field=>$value]));throw new LogicException('identity drift accepted');}catch(RuntimeException){}
    }
});

test('Mysqldump reader grants require exact SELECT-only escaped schema scope', static function (): void {
    $grants=['GRANT USAGE ON *.* TO `backup_reader`@`localhost`'];
    foreach(['autoprintdb','drugmap\\_db','drug\\_stock\\_check','prepack\\_labels'] as $db){$grants[]='GRANT SELECT ON `'.$db.'`.* TO `backup_reader`@`localhost`';}
    MysqldumpCommand::assertGrants($grants);
    foreach([
        [...$grants,'GRANT EVENT ON `autoprintdb`.* TO `backup_reader`@`localhost`'],
        array_slice($grants,0,4),
        array_replace($grants,[2=>'GRANT SELECT ON `drugmap_db`.* TO `backup_reader`@`localhost`']),
    ] as $unsafe){
        try{MysqldumpCommand::assertGrants($unsafe);throw new LogicException('broad grants accepted');}catch(RuntimeException){}
    }
});

test('Mysql option credentials preserve special characters without option injection', static function (): void {
    $password = "synthetic\"\\\n\r\t#; [mysqldump]\nall-databases=1";
    $encoded = TemporaryMysqlCredentials::optionFile($password);
    assertSameValue(3, count(explode("\n", trim($encoded))));
    assertContainsValue('password="synthetic\\"\\\\\\n\\r\\t', $encoded);
    assertContainsValue('user=backup_reader', $encoded);
    assertNotContainsValue("\nall-databases", $encoded);
});

test('Mysqldump process streams large stdout and stderr without pipe deadlock', static function (): void {
    $dir=phase2TempDirectory('dump-process');
    $out=fopen($dir.'/out','xb+'); $err=fopen($dir.'/err','xb+');
    try {
        $result=(new MysqldumpProcess())->run([PHP_BINARY,'-r','for($i=0;$i<256;$i++){fwrite(STDOUT,str_repeat("x",8192));fwrite(STDERR,str_repeat("y",4096));}'], $out,$err,10,3_000_000,2_000_000);
        assertSameValue(0,$result);
        assertSameValue(2_097_152,fstat($out)['size']);
        assertSameValue(1_048_576,fstat($err)['size']);
    } finally {fclose($out);fclose($err);phase2RemoveDirectory($dir);}
});

test('Mysqldump process preserves nonzero exit and bounds timeout and stderr', static function (): void {
    $dir=phase2TempDirectory('dump-process-failure');
    try {
        foreach (['exit'=>'exit(7);', 'timeout'=>'sleep(5);', 'overflow'=>'fwrite(STDERR,str_repeat("x",131072));sleep(2);'] as $case=>$code) {
            $out=fopen($dir.'/'.$case.'out','xb+'); $err=fopen($dir.'/'.$case.'err','xb+');
            try {
                $result=(new MysqldumpProcess())->run([PHP_BINARY,'-r',$code],$out,$err,$case==='timeout'?0.1:5,1000,65536);
                assertSameValue('exit',$case);
                assertSameValue(7,$result);
            } catch (RuntimeException $e) {
                assertTrueValue(in_array($case,['timeout','overflow'],true));
                assertTrueValue(in_array($e->getMessage(),['mysqldump_timeout','mysqldump_output_limit'],true));
            } finally {fclose($out);fclose($err);}
        }
    } finally {phase2RemoveDirectory($dir);}
});

test('Mysqldump process cancellation terminates child and returns safe error', static function (): void {
    $out=tmpfile();$err=tmpfile();
    try{
        try{(new MysqldumpProcess())->run([PHP_BINARY,'-r','sleep(5);'],$out,$err,10,4096,4096,'',static fn():bool=>true);throw new LogicException('cancel ignored');}
        catch(RuntimeException $e){assertSameValue('mysqldump_cancelled',$e->getMessage());}
    }finally{fclose($out);fclose($err);}
});
