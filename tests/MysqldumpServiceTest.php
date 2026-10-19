<?php

declare(strict_types=1);

use BackupApp\Backup\BackupService;
use BackupApp\Backup\DumpEngine;
use BackupApp\Backup\MysqldumpCommand;
use BackupApp\Backup\TemporaryMysqlCredentials;
use BackupApp\Backup\MysqldumpEngine;
use BackupApp\Backup\MysqldumpProcess;

final class DumpFixtureResult extends mysqli_result
{
    private int $offset=0;
    public function __construct(private array $rows) {}
    public function fetch_assoc(): ?array { return $this->rows[$this->offset++] ?? null; }
}

final class DumpFixtureConnection extends mysqli
{
    public array $queries=[];
    public function __construct(public string $engine='InnoDB', public string $type='BASE TABLE') {}
    public function real_escape_string(string $string): string { return addslashes($string); }
    public function query(string $query,int $result_mode=MYSQLI_STORE_RESULT): mysqli_result|bool
    {
        $this->queries[]=$query;
        if(str_starts_with($query,'SHOW GRANTS')) {
            $rows=[['grant'=>'GRANT USAGE ON *.* TO `backup_reader`@`localhost`']];
            foreach(['autoprintdb','drugmap\\_db','drug\\_stock\\_check','prepack\\_labels'] as $name){$rows[]=['grant'=>'GRANT SELECT ON `'.$name.'`.* TO `backup_reader`@`localhost`'];}
            return new DumpFixtureResult($rows);
        }
        if(str_contains($query,'CURRENT_USER')) { return new DumpFixtureResult([[
            'account'=>'backup_reader@localhost','roles'=>'NONE','version'=>'8.4.3','port'=>3306,'server_uuid'=>MysqldumpCommand::SERVER_UUID,'partial_revokes'=>'0',
        ]]); }
        if(str_contains($query,'information_schema.TABLES')) {return new DumpFixtureResult([['TABLE_NAME'=>'items','TABLE_TYPE'=>$this->type,'ENGINE'=>$this->engine]]);}
        if(str_contains($query,'KEY_COLUMN_USAGE')) {return new DumpFixtureResult([['cross_schema'=>0]]);}
        throw new LogicException('Unexpected metadata query');
    }
}

final class DumpFixtureEngine implements DumpEngine
{
    public array $calls=[];
    public function __construct(public bool $fail=false,public bool $complete=true) {}
    public function dump(string $database,array $connection,string $temporarySql): array
    {
        $this->calls[]=$database;
        file_put_contents($temporarySql,"-- MySQL dump fixture\nCREATE TABLE items (id int);\n".($this->complete?"-- Backup completed successfully\n":""));
        if($this->fail){throw new RuntimeException('mysqldump_exit_7');}
        return ['name'=>'mysqldump','version'=>'8.4.3','exit_code'=>0,'profile'=>'innodb-tables-v1'];
    }
}

function dumpFixtureCredentials(): array {return ['host'=>'127.0.0.1','port'=>3306,'username'=>'backup_reader','password'=>'synthetic-secret'];}

test('Mysqldump service publishes exact hash and table-only scope without privileged queries',static function():void{
    $dir=phase2TempDirectory('dump-service'); $engine=new DumpFixtureEngine(); $db=new DumpFixtureConnection();
    try {
        $result=(new BackupService($dir,null,$engine))->backupDatabase($db,'autoprintdb',dumpFixtureCredentials());
        $path=$dir.'/'.$result['relative_path']; $manifest=json_decode(file_get_contents($path.'.json'),true);
        assertSameValue(hash_file('sha256',$path),$result['sha256']);
        assertSameValue(filesize($path),$manifest['bytes']);
        assertSameValue('innodb_tables_structure_and_data',$manifest['scope']);
        assertSameValue(false,$manifest['full_database_objects']);
        assertSameValue(0,$manifest['engine']['exit_code']);
        assertNotContainsValue('synthetic-secret',file_get_contents($path.'.json'));
        foreach($db->queries as $sql){foreach(['SHOW EVENTS','SHOW TRIGGERS','SHOW PROCEDURE','SHOW FUNCTION'] as $forbidden){assertNotContainsValue($forbidden,$sql);}}
    } finally{phase2RemoveDirectory($dir);}
});

test('Mysqldump history distinguishes supported backup metadata from unsupported restore format',static function():void{
    $dir=phase2TempDirectory('dump-history');
    try{
        $service=new BackupService($dir,null,new DumpFixtureEngine());
        $artifact=$service->backupDatabase(new DumpFixtureConnection(),'autoprintdb',dumpFixtureCredentials());
        $origin=['database'=>'autoprintdb','bytes'=>$artifact['bytes'],'sha256'=>$artifact['sha256']];
        $metadata=$service->mysqldumpHistoryMetadata($artifact['relative_path'],$origin);
        assertSameValue('unsupported_mysqldump_format',$metadata['restore_status']);
        assertSameValue('verified',$metadata['provenance_status']);
        assertSameValue('not_checked',$metadata['integrity_status']);
        assertSameValue('ineligible',$metadata['restore_eligibility']);
        assertSameValue('unverified_origin',$service->mysqldumpHistoryMetadata($artifact['relative_path'],null)['provenance_status']);
        $path=$dir.'/'.$artifact['relative_path'].'.json';
        file_put_contents($path,str_replace('"complete": true','"complete": false',file_get_contents($path)));
        assertSameValue(null,$service->mysqldumpHistoryMetadata($artifact['relative_path'],$origin));
    }finally{phase2RemoveDirectory($dir);}
});

test('Mysqldump service failure and incomplete dump publish nothing and preserve existing backup',static function():void{
    foreach([new DumpFixtureEngine(true),new DumpFixtureEngine(false,false)] as $engine){
        $dir=phase2TempDirectory('dump-service-fail'); file_put_contents($dir.'/old.sql','old backup');
        try{
            try{(new BackupService($dir,null,$engine))->backupDatabase(new DumpFixtureConnection(),'autoprintdb',dumpFixtureCredentials());throw new LogicException('published invalid output');}catch(RuntimeException){}
            assertSameValue('old backup',file_get_contents($dir.'/old.sql'));
            assertSameValue([],glob($dir.'/autoprintdb/*'));
        }finally{phase2RemoveDirectory($dir);}
    }
});

test('Mysqldump service rejects views and non-InnoDB before process invocation',static function():void{
    foreach([new DumpFixtureConnection('MyISAM'),new DumpFixtureConnection('', 'VIEW')] as $db){
        $dir=phase2TempDirectory('dump-unsupported');$engine=new DumpFixtureEngine();
        try{
            try{(new BackupService($dir,null,$engine))->backupDatabase($db,'autoprintdb',dumpFixtureCredentials());throw new LogicException('unsupported exported');}catch(RuntimeException){}
            assertSameValue([],$engine->calls);
        }finally{phase2RemoveDirectory($dir);}
    }
});

test('Mysqldump credential lifecycle protects synthetic file and removes exact temporary artifacts',static function():void{
    $credentials=new TemporaryMysqlCredentials(); $path=$credentials->create('synthetic-credential-only');
    try{
        assertContainsValue('synthetic-credential-only',file_get_contents($path));
        assertFalseValue(str_starts_with(strtolower($path),strtolower(dirname(__DIR__))));
    }finally{$credentials->close();}
    assertFalseValue(file_exists($path));
    assertFalseValue(is_dir(dirname($path)));
});

final class DumpSyntheticProcess extends MysqldumpProcess
{
    public ?string $credentialFile=null;
    public function __construct(private string $mode) {}
    public function run(array $command,$stdout,$stderr,float $timeout=3600,int $stdoutLimit=17_179_869_184,int $stderrLimit=65536,string $input='',?callable $cancel=null,$inputStream=null):int
    {
        $this->credentialFile=substr($command[1],strlen('--defaults-file='));
        assertTrueValue(is_file($this->credentialFile));
        assertNotContainsValue('synthetic-secret',implode(' ',$command));
        fwrite($stdout,"-- MySQL dump 10.13 Distrib 8.4.3\n-- Host: 127.0.0.1    Database: autoprintdb\nCREATE TABLE items (id int);\n");
        if($this->mode!=='truncated'){fwrite($stdout,"-- Dump completed on 2026-10-18 12:00:00\n");}
        if($this->mode==='stderr'){fwrite($stderr,'synthetic-secret raw server diagnostic');}
        if($this->mode==='timeout'){throw new RuntimeException('mysqldump_timeout');}
        return $this->mode==='nonzero'?7:0;
    }
}

test('Mysqldump engine validates footer exit stderr and removes credentials on every path',static function():void{
    foreach(['success','truncated','nonzero','stderr','timeout'] as $mode){
        $dir=phase2TempDirectory('dump-engine-'.$mode); $runner=new DumpSyntheticProcess($mode);
        try{
            touch($dir.'/out.tmp');
            try{
                $engine=(new MysqldumpEngine($runner))->dump('autoprintdb',dumpFixtureCredentials(),$dir.'/out.tmp');
                assertSameValue('success',$mode);
                assertSameValue(0,$engine['exit_code']);
                assertTrueValue(str_ends_with(trim(file_get_contents($dir.'/out.tmp')),'-- Backup completed successfully'));
            }catch(RuntimeException $e){
                assertTrueValue($mode!=='success');
                assertNotContainsValue('synthetic-secret',$e->getMessage());
                assertNotContainsValue('-- Backup completed successfully',file_get_contents($dir.'/out.tmp'));
            }
            assertTrueValue($runner->credentialFile!==null);
            assertFalseValue(file_exists($runner->credentialFile));
            assertFalseValue(is_dir(dirname($runner->credentialFile)));
        }finally{phase2RemoveDirectory($dir);}
    }
});

test('Mysqldump engine refuses a nonempty pre-existing output without changing it',static function():void{
    $dir=phase2TempDirectory('dump-no-overwrite');
    file_put_contents($dir.'/out.tmp','existing artifact');
    try{
        try{(new MysqldumpEngine(new DumpSyntheticProcess('success')))->dump('autoprintdb',dumpFixtureCredentials(),$dir.'/out.tmp');throw new LogicException('overwrote output');}catch(RuntimeException){}
        assertSameValue('existing artifact',file_get_contents($dir.'/out.tmp'));
    }finally{phase2RemoveDirectory($dir);}
});

test('Mysqldump service artifacts persist through the unchanged sequential runner',static function():void{
    $dir=phase2TempDirectory('dump-runner');
    try{
        $engine=new DumpFixtureEngine();
        $service=new BackupService($dir.'/backups',null,$engine);
        $operation=new class($service) implements \BackupApp\Backup\BackupOperation {
            public function __construct(private BackupService $service){}
            public function start():void{}
            public function backup(string $database):array{return $this->service->backupDatabase(new DumpFixtureConnection(),$database,dumpFixtureCredentials());}
            public function finish():void{}
        };
        mkdir($dir.'/backups');
        $store=new \BackupApp\Backup\BackupJobStore($dir.'/jobs');
        $guard=new \BackupApp\Backup\BackupExecutionGuard($dir.'/jobs',new \BackupApp\Database\FileDatabaseOperationLock($dir.'/operations/global.lock'));
        $runner=new \BackupApp\Backup\BackupJobRunner($store,$guard,new \BackupApp\Support\Logger($dir.'/app.log'));
        $job=$store->create(['autoprintdb','drugmap_db']);
        $done=$runner->run($job['id'],$operation,['synthetic-secret']);
        assertSameValue('succeeded',$done['status']);
        assertSameValue(['autoprintdb','drugmap_db'],$engine->calls);
        assertSameValue(['succeeded','succeeded'],array_column($done['databases'],'status'));
        foreach($done['databases'] as $item){
            $sql=$dir.'/backups/'.$item['artifact']['relative_path'];
            assertTrueValue((new \BackupApp\Backup\BackupArtifactPublisher())->verifyPublished($sql,$sql.'.json'));
        }
        assertNotContainsValue('synthetic-secret',file_get_contents($store->pathFor($job['id'])));
    }finally{phase2RemoveDirectory($dir);}
});
