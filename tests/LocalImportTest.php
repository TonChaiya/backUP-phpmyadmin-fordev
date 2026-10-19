<?php

declare(strict_types=1);

use BackupApp\Import\ImportPolicy;
use BackupApp\Import\ImportJobStore;
use BackupApp\Support\Auth;

test('Local manager permits only direct loopback even with configured auth and forged headers',static function():void{
    $_SESSION['auth']=['authenticated'=>true,'username'=>'admin'];
    $_SERVER['HTTP_X_FORWARDED_FOR']='127.0.0.1';$_SERVER['HTTP_FORWARDED']='for=127.0.0.1';
    $auth=new Auth(['username'=>'admin','password_hash'=>'configured']);
    assertFalseValue($auth->isConfigured());
    foreach(['127.0.0.1','::1'] as $peer){assertTrueValue($auth->isAuthorized($peer));}
    foreach(['','localhost','::ffff:127.0.0.1','192.168.1.1','203.0.113.1'] as $peer){assertFalseValue($auth->isAuthorized($peer));}
    unset($_SERVER['HTTP_X_FORWARDED_FOR'],$_SERVER['HTTP_FORWARDED']);
});

test('Local import target and account are generated and reject arbitrary or protected scope',static function():void{
    $a=ImportPolicy::names();$b=ImportPolicy::names();
    assertTrueValue($a['target']!==$b['target']);
    ImportPolicy::assertNames($a['target'],$a['username'],$a['id']);
    foreach(['jhcisdb','autoprintdb','local_import_20261019_aaaaaaaaaaaaaaaa','--database=mysql'] as $target){
        try{ImportPolicy::assertNames($target,$a['username'],$a['id']);throw new LogicException('target accepted');}catch(RuntimeException){}
    }
});

test('Local import grants are exact scoped and reject wildcard broad role or destructive privileges',static function():void{
    $names=ImportPolicy::names();$grants=ImportPolicy::expectedGrants($names['target'],$names['username']);
    ImportPolicy::assertGrants($grants,$names['target'],$names['username']);
    foreach([
        [...$grants,'GRANT DROP ON *.* TO `'.$names['username'].'`@`localhost`'],
        array_map(static fn(string $s):string=>str_replace('\\_','_',$s),$grants),
        array_map(static fn(string $s):string=>str_replace('SELECT, INSERT, CREATE, REFERENCES','SELECT, INSERT, CREATE, DROP',$s),$grants),
    ] as $unsafe){try{ImportPolicy::assertGrants($unsafe,$names['target'],$names['username']);throw new LogicException('unsafe grants accepted');}catch(RuntimeException){}}
});

test('Local import job persists generated scope and never stores credentials',static function():void{
    $dir=phase2TempDirectory('local-import-store');
    try{
        $store=new ImportJobStore($dir);
        $job=$store->create(['database'=>'autoprintdb','relative_path'=>'autoprintdb/fixture.sql','sha256'=>str_repeat('a',64),'bytes'=>64]);
        assertSameValue('awaiting_provisioning',$store->read($job['id'])['status']);
        assertSameValue($job['target'],$store->read($job['id'])['target']);
        assertNotContainsValue('password',file_get_contents($store->path($job['id'])));
        try{$store->read('../foreign');throw new LogicException('path accepted');}catch(RuntimeException){}
        $failed=$store->update($job['id'],['status'=>'failed','error'=>'safe_code']);
        assertSameValue('failed',$failed['status']);
        try{$store->update($job['id'],['target'=>'autoprintdb']);throw new LogicException('immutable scope changed');}catch(RuntimeException){}
    }finally{phase2RemoveDirectory($dir);}
});

test('Local import credential options reject root and support only generated import accounts',static function():void{
    $user='local_import_'.str_repeat('a',16);
    $file=\BackupApp\Backup\TemporaryMysqlCredentials::optionFile('synthetic-secret',$user);
    assertContainsValue('user='.$user,$file);
    foreach(['root','restore_runner',"local_import_aaaaaaaaaaaaaaaa\npassword=evil"] as $bad){
        try{\BackupApp\Backup\TemporaryMysqlCredentials::optionFile('synthetic-secret',$bad);throw new LogicException('arbitrary credential role accepted');}catch(RuntimeException){}
    }
});

test('Local import streams SQL file through stdin without pipe deadlock or loading it as arguments',static function():void{
    $dir=phase2TempDirectory('import-stream');$input=fopen($dir.'/input','xb+');$out=tmpfile();$err=tmpfile();
    try{
        for($i=0;$i<128;$i++){fwrite($input,str_repeat('x',8192));}rewind($input);
        $exit=(new \BackupApp\Backup\MysqldumpProcess())->run([PHP_BINARY,'-r','echo strlen(stream_get_contents(STDIN));'],$out,$err,10,4096,4096,'',null,$input);
        rewind($out);assertSameValue(0,$exit);assertSameValue('1048576',stream_get_contents($out));
    }finally{fclose($input);fclose($out);fclose($err);phase2RemoveDirectory($dir);}
});
