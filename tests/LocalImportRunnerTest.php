<?php

declare(strict_types=1);

use BackupApp\Import\ImportPolicy;
use BackupApp\Import\ImportJobStore;
use BackupApp\Import\ImportArtifactVerifier;
use BackupApp\Import\ImportRunner;
use BackupApp\Import\ImportDatabase;
use BackupApp\Import\ImportClient;
use BackupApp\Import\MysqlImportClient;
use BackupApp\Import\MysqliImportDatabase;
use BackupApp\Backup\MysqldumpCommand;
use BackupApp\Backup\MysqldumpProcess;
use BackupApp\Database\FileDatabaseOperationLock;

final class LocalImportFakeDatabase implements ImportDatabase
{
    public int $starts=0;public bool $closed=false;
    public array $snapshot=['tables'=>[['TABLE_NAME'=>'t']],'columns'=>[],'indexes'=>[],'foreign_keys'=>[],'rows'=>['t'=>'2']];
    public function start(array $job):void{$this->starts++;}
    public function sourceSnapshot(string $source):array{return $this->snapshot;}
    public function targetSnapshot(string $target):array{return $this->snapshot;}
    public function close():void{$this->closed=true;}
}
final class LocalImportFakeClient implements ImportClient
{
    public int $calls=0;
    public function __construct(private readonly bool $fail=false){}
    public function execute(array $job,ImportArtifactVerifier $artifacts,string $password):array
    {$this->calls++;if($this->fail){throw new RuntimeException('raw diagnostic synthetic-password SQL');}return ['exit_code'=>0];}
}

function localImportOwned(ImportJobStore $store,array $job):void
{
    $store->recordOwnership($job['id'],array_intersect_key($job,array_flip(['id','target','username']))+
        ['server_uuid'=>MysqldumpCommand::SERVER_UUID,'created_new'=>true,'account_created_new'=>true,'created_at'=>gmdate('c')]);
}

test('Local import runner requires ownership and shared lock before any database or client operation',static function():void{
    $root=phase2TempDirectory('import-gates');
    try{
        [$relative]=localImportFixture($root);$v=new ImportArtifactVerifier($root.'/backups',$root.'/jobs');
        $store=new ImportJobStore($root.'/imports');$job=$store->create($v->verify($relative));
        $lock=new FileDatabaseOperationLock($root.'/shared/global.lock');$runner=new ImportRunner($store,$lock,$v);
        $db=new LocalImportFakeDatabase();$client=new LocalImportFakeClient();
        try{$runner->run($job['id'],$db,$client,'synthetic-password');throw new LogicException('missing ownership accepted');}catch(RuntimeException $e){assertSameValue('import_provisioning_required',$e->getMessage());}
        assertSameValue(0,$db->starts);assertSameValue(0,$client->calls);
        localImportOwned($store,$job);$held=$lock->acquire('fixture-export');
        try{$runner->run($job['id'],$db,$client,'synthetic-password');throw new LogicException('shared lock ignored');}catch(RuntimeException $e){assertSameValue('import_database_operation_busy',$e->getMessage());}finally{$lock->release($held);}
        assertSameValue(0,$db->starts);assertSameValue('awaiting_provisioning',$store->read($job['id'])['status']);
    }finally{phase2RemoveDirectory($root);}
});

test('Local import runner persists real result contract and refuses retry after success or failure',static function():void{
    foreach([false,true] as $fail){
        $root=phase2TempDirectory('import-lifecycle');
        try{
            [$relative]=localImportFixture($root);$v=new ImportArtifactVerifier($root.'/backups',$root.'/jobs');
            $store=new ImportJobStore($root.'/imports');$job=$store->create($v->verify($relative));localImportOwned($store,$job);
            $runner=new ImportRunner($store,new FileDatabaseOperationLock($root.'/shared/global.lock'),$v);
            $db=new LocalImportFakeDatabase();$client=new LocalImportFakeClient($fail);
            $done=$runner->run($job['id'],$db,$client,'synthetic-password');
            assertSameValue($fail?'failed':'succeeded',$done['status']);assertTrueValue($db->closed);assertSameValue(1,$client->calls);
            assertNotContainsValue('synthetic-password',file_get_contents($store->path($job['id'])));
            if($fail){assertSameValue('import_operation_failed',$done['error']);}
            try{$runner->run($job['id'],$db,$client,'synthetic-password');throw new LogicException('retry accepted');}catch(RuntimeException $e){assertSameValue('import_already_attempted_no_retry',$e->getMessage());}
        }finally{phase2RemoveDirectory($root);}
    }
});

test('Local import status recovery marks orphaned work interrupted without retrying SQL',static function():void{
    $root=phase2TempDirectory('import-interrupted');
    try{
        $store=new ImportJobStore($root);$job=$store->create(['database'=>'autoprintdb','relative_path'=>'autoprintdb/f.sql','sha256'=>str_repeat('a',64),'bytes'=>10]);
        $store->update($job['id'],['status'=>'running']);$claim=$store->claim($job['id']);
        try{assertSameValue('running',$store->recover($job['id'])['status']);}finally{fclose($claim);}
        $recovered=$store->recover($job['id']);assertSameValue('interrupted',$recovered['status']);
        assertSameValue('import_process_interrupted_reconcile_target',$recovered['error']);
        assertSameValue($job['target'],$recovered['target']);
    }finally{phase2RemoveDirectory($root);}
});

test('Local import rejects ownership mismatch and source or restored count mismatch',static function():void{
    $a=['tables'=>[['TABLE_NAME'=>'t']],'rows'=>['t'=>'1']];$b=$a;$b['rows']['t']='2';
    foreach([[$a,$b,$a],[$a,$a,$b]] as [$before,$after,$target]){
        try{MysqliImportDatabase::compare($before,$after,$target);throw new LogicException('mismatch passed');}catch(RuntimeException){}
    }
    assertTrueValue(MysqliImportDatabase::compare($a,$a,$a)['schema_and_row_counts_match']);
    $root=phase2TempDirectory('import-owner');
    try{
        $store=new ImportJobStore($root);$job=$store->create(['database'=>'autoprintdb','relative_path'=>'autoprintdb/f.sql','sha256'=>str_repeat('a',64),'bytes'=>10]);
        try{$store->recordOwnership($job['id'],['id'=>$job['id'],'target'=>'autoprintdb','username'=>$job['username']]);throw new LogicException('wrong owner accepted');}catch(RuntimeException){}
    }finally{phase2RemoveDirectory($root);}
});

test('Local import official mysql accepts pinned fixed options offline and restricts client capabilities',static function():void{
    MysqlImportClient::verifyBinary();$job=ImportPolicy::names();
    $args=MysqlImportClient::command($job,'C:\\private\\client.cnf');
    assertSameValue(MysqlImportClient::BINARY,$args[0]);assertContainsValue('--database='.$job['target'],implode(' ',$args));
    foreach(['--binary-mode','--skip-system-command','--local-infile=0','--skip-reconnect'] as $flag){assertTrueValue(in_array($flag,$args,true));}
    $out=tmpfile();$err=tmpfile();
    try{
        $exit=(new MysqldumpProcess())->run([MysqlImportClient::BINARY,'--no-defaults',...MysqlImportClient::OPTIONS,'--help'],$out,$err,10,262144,65536);
        assertSameValue(0,$exit);assertSameValue(0,fstat($err)['size']);
    }finally{fclose($out);fclose($err);}
});

test('Local import session ownership cannot be forged or inherited from export jobs',static function():void{
    $id=str_repeat('a',32);$_SESSION['backup_job_ids']=[$id];
    try{\BackupApp\Import\ImportAccess::requireOwned($id);throw new LogicException('export ownership accepted');}catch(RuntimeException){}
    \BackupApp\Import\ImportAccess::register($id);\BackupApp\Import\ImportAccess::requireOwned($id);
    try{\BackupApp\Import\ImportAccess::requireOwned(str_repeat('b',32));throw new LogicException('foreign job accepted');}catch(RuntimeException){}
});

final class LocalNativeClientFakeProcess extends MysqldumpProcess
{
    public ?string $privateFile=null;
    public function __construct(private string $mode){}
    public function run(array $command,$stdout,$stderr,float $timeout=3600,int $stdoutLimit=17_179_869_184,int $stderrLimit=65536,string $input='',?callable $cancel=null,$inputStream=null):int
    {
        assertTrueValue(is_resource($inputStream));
        assertContainsValue('--database=local_import_',implode(' ',$command));
        assertNotContainsValue('synthetic-password',implode(' ',$command));
        assertTrueValue(strlen(stream_get_contents($inputStream))>64);
        $this->privateFile=substr($command[1],strlen('--defaults-file='));
        if($this->mode==='stderr'){fwrite($stderr,'synthetic-password raw SQL diagnostic');}
        if($this->mode==='stdout'){fwrite($stdout,'synthetic-password unexpected query result');}
        if($this->mode==='timeout'){throw new RuntimeException('mysqldump_timeout');}
        return $this->mode==='exit'?2:0;
    }
}

test('Local import native client stages verified input checks exit and never leaks raw diagnostics',static function():void{
    $root=phase2TempDirectory('import-native-fake');
    try{
        [$relative]=localImportFixture($root);$v=new ImportArtifactVerifier($root.'/backups',$root.'/jobs');
        $job=ImportPolicy::names()+['artifact'=>$v->verify($relative)];
        foreach(['success','exit','stderr','stdout','timeout'] as $mode){
            $process=new LocalNativeClientFakeProcess($mode);
            $caught=null;$result=null;
            try{
                $result=(new MysqlImportClient($process))->execute($job,$v,'synthetic-password');
            }catch(RuntimeException $e){$caught=$e;}
            if($mode==='success'){assertSameValue(null,$caught);assertSameValue(0,$result['exit_code']);}
            else{assertTrueValue($caught instanceof RuntimeException,'Unexpectedly accepted '.$mode);assertNotContainsValue('synthetic-password',$caught->getMessage());assertNotContainsValue('raw SQL',$caught->getMessage());}
            assertTrueValue($process->privateFile!==null);assertFalseValue(is_dir(dirname($process->privateFile)));
        }
        assertSameValue($job['artifact']['sha256'],hash_file('sha256',$root.'/backups/'.$relative));
    }finally{phase2RemoveDirectory($root);}
});
