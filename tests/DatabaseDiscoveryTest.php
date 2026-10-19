<?php
declare(strict_types=1);

final class DiscoveryRows extends mysqli_result {
    private int $index=0;public function __construct(private array $rows){}
    public function fetch_row():?array{return isset($this->rows[$this->index])?[$this->rows[$this->index++]]:null;}
}
final class DiscoveryConnection extends mysqli {
    public array $queries=[];public function __construct(private array $names){}
    public function query(string $sql,int $mode=MYSQLI_STORE_RESULT):mysqli_result|bool{
        $this->queries[]=$sql;
        if(!str_contains($sql,'information_schema.SCHEMATA')||!str_contains($sql,"LOWER(SCHEMA_NAME) <> 'jhcisdb'")){throw new LogicException('Protected catalog predicate missing');}
        return new DiscoveryRows($this->names);
    }
}
test('Discovery lists account visible ordinary databases separately from system schemas without hardcoded test sources',static function():void{
    $db=new DiscoveryConnection(['project_alpha','information_schema','mysql','sys','performance_schema','新しいDB','client data','--odd','project_alpha']);
    $catalog=(new BackupApp\Database\DatabaseRepository())->catalog($db);
    assertSameValue(['project_alpha','新しいDB','client data','--odd'],$catalog['databases']);
    assertSameValue(['information_schema','mysql','sys','performance_schema'],$catalog['system_databases']);
    assertSameValue(1,count($db->queries));assertNotContainsValue('SHOW DATABASES',$db->queries[0]);
    assertNotContainsValue('autoprintdb',$db->queries[0]);
});
test('Discovery Select All only uses eligible account results and explicit selections cannot forge visibility',static function():void{
    $access=new BackupApp\Backup\BackupJobAccess();
    $known=['project_alpha','mysql','sys','jhcisdb','client data','project_beta'];
    assertSameValue(['project_alpha','client data','project_beta'],$access->select([],true,$known));
    assertSameValue(['project_beta'],$access->select(['project_beta','project_beta'],false,$known));
    foreach(['jhcisdb','JHCISDB','mysql','information_schema','not_visible','../escape'] as $name){
        try{$access->select([$name],false,$known);throw new LogicException('Unsafe selection accepted');}catch(BackupApp\Backup\BackupJobRequestException){}
    }
});
test('Discovery queued selections bind to server verified session scope rather than mutable job names',static function():void{
    $access=new BackupApp\Backup\BackupJobAccess();$access->rotateConnectionContext();$id=str_repeat('d',32);
    $access->register($id,['project_alpha']);$access->assertSelection($id,['project_alpha']);
    foreach([['project_beta'],['jhcisdb'],['project_alpha','project_beta']] as $changed){
        try{$access->assertSelection($id,$changed);throw new LogicException('Job selection redirected');}catch(BackupApp\Backup\BackupJobRequestException){}
    }
});
test('Discovery native argv requires exact selected scope and safely terminates options',static function():void{
    $command=new BackupApp\Backup\MysqldumpCommand();
    $args=$command->arguments('--odd','C:\\private\\options.cnf',['--odd']);
    assertSameValue(['--','--odd'],array_slice($args,-2));
    foreach(['jhcisdb','mysql','not_selected'] as $name){
        try{$command->arguments($name,'C:\\private\\options.cnf',['project_alpha']);throw new LogicException('Unselected native scope');}catch(DomainException|RuntimeException){}
    }
});

test('Discovery excludes unexpected protected results and keeps unsafe artifact names display only',static function():void{
    $catalog=(new BackupApp\Database\DatabaseRepository())->catalog(new DiscoveryConnection(['client.v2','bad/name','CON','../escape']));
    assertSameValue(['client.v2'],$catalog['databases']);assertSameValue(['bad/name','CON','../escape'],$catalog['unsupported_databases']);
    foreach(['jhcisdb','JHCISDB'] as $name){
        try{(new BackupApp\Database\DatabaseRepository())->catalog(new DiscoveryConnection([$name]));throw new LogicException('Protected result leaked');}catch(DomainException){}
    }
});

test('Discovery preserves connection status and separates catalog sections through fake saved profile verification',static function():void{
    [$root,$store]=autoConnectionFixture();try{
        $calls=0;$probe=static function(array $s)use(&$calls):array{$calls++;return ['identity'=>['version'=>'8.4.3','port'=>3306,'server_uuid'=>BackupApp\Backup\MysqldumpCommand::SERVER_UUID,'account'=>'fixture_user@localhost','roles'=>'NONE'],
            'databases'=>['client_project'],'system_databases'=>['mysql','sys'],'unsupported_databases'=>['bad/name']];};
        $controller=autoConnectionController($store,new BackupApp\Database\FileDatabaseOperationLock($root.'/lock'),$probe);
        $controller->save(autoConnectionInput());$status=$controller->verify();assertTrueValue($status['connected']);assertSameValue(['client_project'],$status['databases']);assertSameValue(['mysql','sys'],$status['system_databases']);
        $html=BackupApp\Support\View::render('backup',['csrfToken'=>'fixture','isConnected'=>true,'databases'=>$status['databases'],
            'systemDatabases'=>$status['system_databases'],'unsupportedDatabases'=>$status['unsupported_databases'],'connectionData'=>[]],'backup',['app_name'=>'Fixture']);
        assertContainsValue('value="client_project"',$html);assertContainsValue('System schemas',$html);assertNotContainsValue('value="mysql"',$html);assertContainsValue('bad/name',$html);
    }finally{phase2RemoveDirectory($root);}
});

test('Discovery scoped service verifies visibility before table metadata and no fake dump runs for an inaccessible name',static function():void{
    $root=phase2TempDirectory('discovery-service');try{
        $db=new class extends mysqli {
            public array $queries=[];public function __construct(){}
            public function real_escape_string(string $s):string{return addslashes($s);}
            public function query(string $sql,int $mode=MYSQLI_STORE_RESULT):mysqli_result|bool{
                $this->queries[]=$sql;
                if(str_contains($sql,'information_schema.SCHEMATA')){return new DumpFixtureResult([]);}
                if(str_contains($sql,'CURRENT_USER')){return new DumpFixtureResult([['version'=>'8.4.3','port'=>3306,'server_uuid'=>BackupApp\Backup\MysqldumpCommand::SERVER_UUID,'account'=>'fixture_user@localhost','roles'=>'NONE']]);}
                throw new LogicException('Unexpected metadata outside selected catalog');
            }
        };
        $engine=new DumpFixtureEngine();
        try{(new BackupApp\Backup\BackupService($root,null,$engine))->backupDatabase($db,'project_missing',dumpFixtureCredentials()+['unused'=>true]);throw new LogicException('Missing selection accepted');}catch(DomainException){}
        assertSameValue([],$db->queries);assertSameValue([],$engine->calls);
        $credentials=array_replace(dumpFixtureCredentials(),['selected_databases'=>['project_missing']]);
        try{(new BackupApp\Backup\BackupService($root,null,$engine))->backupDatabase($db,'project_missing',$credentials);throw new LogicException('Invisible source exported');}catch(DomainException $e){assertSameValue('source_not_visible_to_account',$e->getMessage());}
        assertSameValue([],$engine->calls);assertFalseValue(is_dir($root.'/project_missing'));
        assertFalseValue((bool)array_filter($db->queries,static fn($q)=>str_contains($q,'information_schema.TABLES')));
    }finally{phase2RemoveDirectory($root);}
});

test('Discovery non-test database artifacts retain trusted import eligibility including Unicode and spaces',static function():void{
    $root=phase2TempDirectory('discovery-artifact');try{
        [$relative]=localImportFixture($root,'ข้อมูล client');
        $verifier=new BackupApp\Import\ImportArtifactVerifier($root.'/backups',$root.'/jobs');
        assertSameValue('ข้อมูล client',$verifier->verify($relative)['database']);
        foreach(['jhcisdb','JHCISDB','mysql','sys'] as $source){
            try{$verifier->inspect($source.'/fixture.sql');throw new LogicException('Protected artifact eligible');}catch(DomainException){}
        }
    }finally{phase2RemoveDirectory($root);}
});
