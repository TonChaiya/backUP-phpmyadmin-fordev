<?php
declare(strict_types=1);

test('Generic profile intentionally saves empty password without probing unsaved defaults',static function():void{
    [$root,$store]=autoConnectionFixture();try{
        $calls=0;$probe=static function(array $s)use(&$calls):array{$calls++;return ['identity'=>['version'=>'8.4.3','port'=>$s['port'],'account'=>$s['username'].'@localhost','roles'=>'NONE','partial_revokes'=>'0','server_uuid'=>BackupApp\Backup\MysqldumpCommand::SERVER_UUID],'databases'=>['autoprintdb']];};
        $lock=new BackupApp\Database\FileDatabaseOperationLock($root.'/global.lock');
        $c=autoConnectionController($store,$lock,$probe);$status=$c->verify();assertFalseValue($status['connected']);assertSameValue(null,$status['checked_at']);assertSameValue(0,$calls);
        $input=['host'=>'127.0.0.1','port'=>'3306','username'=>'root','password'=>'','settings_revision'=>''];
        assertTrueValue($c->test($input)['connected']);assertSameValue(null,$store->read());
        $c->save($input);assertSameValue('',$store->read()['password']);assertTrueValue($c->metadata()['profile_saved']);
        $_SESSION=[];$fresh=autoConnectionController($store,$lock,$probe);assertTrueValue($fresh->verify()['connected']);
        assertNotContainsValue('password',json_encode($_SESSION));
    }finally{phase2RemoveDirectory($root);}
});

test('Generic native credentials support empty passwords and reject username option injection',static function():void{
    $text=BackupApp\Backup\TemporaryMysqlCredentials::optionFile('','root');assertContainsValue('password=""',$text);
    foreach(["root\npassword=bad",'--all-databases',''] as $username){try{BackupApp\Backup\TemporaryMysqlCredentials::optionFile('',$username);throw new LogicException('unsafe username');}catch(RuntimeException){}}
    BackupApp\Backup\MysqldumpCommand::assertConnection(['host'=>'localhost','port'=>3306,'username'=>'operator']);
});

test('Generic saved profile import requires new exact target and provable create insert select references grants',static function():void{
    $job=BackupApp\Import\ImportPolicy::names()+['mode'=>'saved_profile','server_uuid'=>BackupApp\Backup\MysqldumpCommand::SERVER_UUID];$job['username']='root';
    BackupApp\Import\ImportPolicy::assertJob($job);
    BackupApp\Import\ImportPolicy::assertProfileGrants(['GRANT ALL PRIVILEGES ON *.* TO `root`@`localhost` WITH GRANT OPTION'],$job['target']);
    try{BackupApp\Import\ImportPolicy::assertProfileGrants(['GRANT SELECT ON *.* TO `root`@`localhost`'],$job['target']);throw new LogicException('missing target grants');}catch(RuntimeException $e){assertSameValue('import_insufficient_target_privileges',$e->getMessage());}
    $job['target']='autoprintdb';try{BackupApp\Import\ImportPolicy::assertJob($job);throw new LogicException('source target');}catch(RuntimeException){}
});

test('Generic profile changes require confirmation and reject server drift without replacing saved credentials',static function():void{
    [$root,$store]=autoConnectionFixture();try{
        $lock=new BackupApp\Database\FileDatabaseOperationLock($root.'/global.lock');$uuid=BackupApp\Backup\MysqldumpCommand::SERVER_UUID;
        $probe=static function(array $s)use(&$uuid):array{return ['identity'=>['version'=>'8.4.3','port'=>$s['port'],'account'=>$s['username'].'@localhost','roles'=>'NONE','server_uuid'=>$uuid],'databases'=>['autoprintdb']];};
        $c=autoConnectionController($store,$lock,$probe);$input=['host'=>'127.0.0.1','port'=>'3306','username'=>'root','password'=>'','settings_revision'=>''];$c->save($input);$old=$store->read();
        $uuid='aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';assertFalseValue($c->verify()['connected']);
        $input['settings_revision']=$old['revision'];
        try{$c->save($input);throw new LogicException('identity silently replaced');}catch(RuntimeException $e){assertSameValue('connection_identity_confirmation_required',$e->getMessage());}
        assertSameValue($old,$store->read());$c->save($input+['confirm_identity_change'=>'1']);assertSameValue($uuid,$store->read()['server_uuid']);assertTrueValue($c->verify()['connected']);
    }finally{phase2RemoveDirectory($root);}
});

final class GenericImportRows extends mysqli_result {
    private int $i=0;public function __construct(private array $rows){}
    public function fetch_assoc():?array{return $this->rows[$this->i++]??null;}
    public function fetch_row():?array{$r=$this->fetch_assoc();return $r===null?null:array_values($r);}
}
final class GenericImportConnection extends mysqli {
    public array $queries=[];public function __construct(private bool $collision=false,private bool $privileges=true){}
    public function real_escape_string(string $s):string{return addslashes($s);}
    public function query(string $sql,int $mode=MYSQLI_STORE_RESULT):mysqli_result|bool{
        $this->queries[]=$sql;
        if(str_starts_with($sql,'SHOW GRANTS')){return new GenericImportRows([['g'=>$this->privileges?'GRANT ALL PRIVILEGES ON *.* TO `root`@`localhost` WITH GRANT OPTION':'GRANT SELECT ON *.* TO `root`@`localhost`']]);}
        if(str_contains($sql,'CURRENT_USER')){return new GenericImportRows([['version'=>'8.4.3','port'=>3306,'account'=>'root@localhost','server_uuid'=>BackupApp\Backup\MysqldumpCommand::SERVER_UUID,'roles'=>'NONE','partial_revokes'=>'0']]);}
        if(str_contains($sql,'information_schema.SCHEMATA')){return new GenericImportRows($this->collision?[['SCHEMA_NAME'=>'existing']]:[]);}
        if(str_starts_with($sql,'CREATE DATABASE `local_import_')){return true;}
        throw new LogicException('Unexpected scoped SQL');
    }
    public function close():bool{return true;}
}
test('Generic import adapter refuses collisions and insufficient privileges before create and records only exact new ownership',static function():void{
    // Fail before constructing an adapter if the fake-only factory seam is absent.
    assertTrueValue((new ReflectionMethod(BackupApp\Import\MysqliImportDatabase::class,'__construct'))->getNumberOfParameters()>=5);
    if(function_exists('mysqli_init')){throw new RuntimeException('Offline adapter test requires PHP -d disable_functions=mysqli_init');}
    foreach([[true,true],[false,false],[false,true]] as [$collision,$privileges]){
        $db=new GenericImportConnection($collision,$privileges);$record=null;
        $profile=['host'=>'127.0.0.1','port'=>3306,'username'=>'root','password'=>'','server_uuid'=>BackupApp\Backup\MysqldumpCommand::SERVER_UUID,'account'=>'root@localhost'];
        $job=BackupApp\Import\ImportPolicy::names()+['mode'=>'saved_profile','artifact'=>['database'=>'autoprintdb']];$job=array_replace($job,$profile);unset($job['password']);
        $adapter=new BackupApp\Import\MysqliImportDatabase($profile,'',null,static function($id,$r)use(&$record):void{$record=$r;},static fn()=>$db);
        try{$adapter->start($job);assertFalseValue($collision||!$privileges);}catch(RuntimeException $e){assertSameValue($collision?'import_target_already_exists':'import_insufficient_target_privileges',$e->getMessage());}
        $creates=array_values(array_filter($db->queries,static fn($s)=>str_starts_with($s,'CREATE DATABASE')));
        assertSameValue($collision||!$privileges?0:1,count($creates));
        if($creates!==[]){assertContainsValue('`'.$job['target'].'`',$creates[0]);assertSameValue($job['target'],$record['target']);assertSameValue(false,$record['account_created_new']);}
        assertNotContainsValue('jhcisdb',implode(' ',$db->queries));$adapter->close();
    }
});

test('Generic saved profile runner validates artifact and ownership with no separate account or password persistence',static function():void{
    $root=phase2TempDirectory('generic-profile-runner');try{
        [$relative]=localImportFixture($root);$artifacts=new BackupApp\Import\ImportArtifactVerifier($root.'/backups',$root.'/jobs');
        $profile=['host'=>'127.0.0.1','port'=>3306,'username'=>'root','password'=>'synthetic-profile-only','server_uuid'=>BackupApp\Backup\MysqldumpCommand::SERVER_UUID,'account'=>'root@localhost'];
        $store=new BackupApp\Import\ImportJobStore($root.'/imports');$artifact=$artifacts->verify($relative);
        $job=$store->create($artifact,$profile);assertSameValue('root',$store->read($job['id'])['username']);
        assertNotContainsValue('synthetic-profile-only',file_get_contents($store->path($job['id'])));
        $database=new class($store) implements BackupApp\Import\ImportDatabase {
            public function __construct(private $store){}
            public function start(array $job):void{$this->store->recordOwnership($job['id'],['id'=>$job['id'],'target'=>$job['target'],'username'=>$job['username'],'server_uuid'=>$job['server_uuid'],'created_new'=>true,'account_created_new'=>false,'created_at'=>gmdate('c')]);}
            public function sourceSnapshot(string $s):array{return ['tables'=>[],'rows'=>[]];}
            public function targetSnapshot(string $s):array{return ['tables'=>[],'rows'=>[]];}
            public function close():void{}
        };
        $done=(new BackupApp\Import\ImportRunner($store,new BackupApp\Database\FileDatabaseOperationLock($root.'/global.lock'),$artifacts))->run($job['id'],$database,new LocalImportFakeClient(),'');
        assertSameValue('succeeded',$done['status']);assertSameValue('not_verified',$done['verification']['data_value_equality']);
        $foreign=array_replace($profile,['server_uuid'=>'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee']);
        try{$store->create($artifact,$foreign);throw new LogicException('foreign artifact accepted');}catch(RuntimeException $e){assertSameValue('import_artifact_server_mismatch',$e->getMessage());}
    }finally{phase2RemoveDirectory($root);}
});
