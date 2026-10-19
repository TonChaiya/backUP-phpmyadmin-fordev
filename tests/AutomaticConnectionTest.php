<?php
declare(strict_types=1);

function autoConnectionFixture(?callable $replace=null):array {
    $root=phase2TempDirectory('automatic-connection');mkdir($root.'/source');
    $store=new BackupApp\Database\ConnectionSettingsStore($root.'/private',$root.'/source',
        static function(string $path,bool $create):string {if($create&&!is_dir($path)){mkdir($path,0700,true);}return 'S-1-5-21-111-222-333-1001';},$replace);
    return [$root,$store];
}
function autoConnectionInput(string $password='synthetic-reader-password',string $revision=''):array {
    return ['host'=>'127.0.0.1','port'=>'3306','username'=>'fixture_user','password'=>$password,'settings_revision'=>$revision];
}
function autoConnectionController($store,$lock,?callable $probe=null):BackupApp\Http\Controller\BackupController {
    return new BackupApp\Http\Controller\BackupController(new BackupApp\Database\ConnectionFactory(['connect_timeout_seconds'=>1]),
        new BackupApp\Database\DatabaseRepository(),$store,$lock,$probe??static fn(array $c):array=>[
            'identity'=>['version'=>'8.4.3','port'=>3306,'account'=>'fixture_user@localhost','roles'=>'NONE','partial_revokes'=>'0',
                'server_uuid'=>BackupApp\Backup\MysqldumpCommand::SERVER_UUID],'databases'=>['autoprintdb','drugmap_db']]);
}

test('Automatic connection settings persist privately without session passwords and survive a new session',static function():void {
    [$root,$store]=autoConnectionFixture();try{
        $lock=new BackupApp\Database\FileDatabaseOperationLock($root.'/operations/global.lock');$controller=autoConnectionController($store,$lock);
        assertFalseValue($controller->verify()['connected']);$controller->save(autoConnectionInput());
        assertSameValue('synthetic-reader-password',$store->read()['password']);
        $_SESSION=[];$next=autoConnectionController($store,$lock);$status=$next->verify();
        assertTrueValue($status['connected']);assertSameValue(['autoprintdb','drugmap_db'],$status['databases']);
        assertNotContainsValue('synthetic-reader-password',json_encode($status));
        assertNotContainsValue('synthetic-reader-password',json_encode($_SESSION));
        assertNotContainsValue('synthetic-reader-password',json_encode($next->metadata()));
    }finally{phase2RemoveDirectory($root);}
});

test('Automatic connection blank save preserves password explicit clear saves empty and stale forms cannot overwrite',static function():void {
    [$root,$store]=autoConnectionFixture();try{
        $c=autoConnectionController($store,new BackupApp\Database\FileDatabaseOperationLock($root.'/global.lock'));
        $c->save(autoConnectionInput());$rev=$c->revision();$c->save(autoConnectionInput('',$rev));
        assertSameValue('synthetic-reader-password',$store->read()['password']);assertSameValue($rev,$c->revision());
        $c->save(autoConnectionInput('',$rev)+['clear_password'=>'1']);assertSameValue('',$store->read()['password']);
        assertTrueValue($c->verify()['connected']);
        try{$c->save(autoConnectionInput('synthetic-new',$rev));throw new LogicException('stale save accepted');}
        catch(RuntimeException $e){assertSameValue('connection_settings_stale',$e->getMessage());}
    }finally{phase2RemoveDirectory($root);}
});

test('Automatic connection test does not persist and failed verification removes stale connected databases',static function():void {
    [$root,$store]=autoConnectionFixture();try{
        $lock=new BackupApp\Database\FileDatabaseOperationLock($root.'/global.lock');$good=autoConnectionController($store,$lock);
        assertTrueValue($good->test(autoConnectionInput())['connected']);assertSameValue(null,$store->read());
        $good->save(autoConnectionInput());assertTrueValue($good->verify()['connected']);
        $bad=autoConnectionController($store,$lock,static function(array $c):array {throw new RuntimeException('synthetic-reader-password SQL failure');});
        $status=$bad->verify();assertFalseValue($status['connected']);assertSameValue([],$status['databases']);
        assertSameValue([], $_SESSION['databases']??[]);assertFalseValue(isset($_SESSION['connection']));
        assertNotContainsValue('synthetic-reader-password',json_encode($status));assertContainsValue('Settings',$status['error']);
    }finally{phase2RemoveDirectory($root);}
});

test('Automatic connection rejects port host role and unauthorized catalog before exposing any list',static function():void {
    [$root,$store]=autoConnectionFixture();try{
        $calls=0;$lock=new BackupApp\Database\FileDatabaseOperationLock($root.'/global.lock');
        $c=autoConnectionController($store,$lock,static function(array $c)use(&$calls):array{$calls++;throw new RuntimeException('should not probe');});
        foreach([['port'=>'3333'],['host'=>'192.0.2.1'],['username'=>'--option']] as $change){
            try{$c->save(array_replace(autoConnectionInput(),$change));throw new LogicException('unsafe settings saved');}catch(RuntimeException){}
        }
        assertSameValue(0,$calls);assertSameValue(null,$store->read());
        $good=autoConnectionController($store,$lock);$good->save(autoConnectionInput());
        $bad=autoConnectionController($store,$lock,static fn(array $c):array=>['identity'=>[], 'databases'=>['jhcisdb']]);
        assertFalseValue($bad->verify()['connected']);assertSameValue([], $_SESSION['databases']??[]);
    }finally{phase2RemoveDirectory($root);}
});

test('Automatic connection revision invalidates queued contexts and save is excluded by active operation lock',static function():void {
    [$root,$store]=autoConnectionFixture();try{
        $lock=new BackupApp\Database\FileDatabaseOperationLock($root.'/global.lock');$c=autoConnectionController($store,$lock);$c->save(autoConnectionInput());$c->verify();
        $access=new BackupApp\Backup\BackupJobAccess();$id=str_repeat('a',32);$access->register($id);$old=$c->revision();
        assertTrueValue($access->matchesCurrentConnection($id));$session=$_SESSION;
        $held=$lock->acquire('fixture-running');try{$c->save(autoConnectionInput('synthetic-new',$old));throw new LogicException('running operation changed');}
        catch(RuntimeException $e){assertSameValue('connection_operation_busy',$e->getMessage());}finally{$lock->release($held);}
        $c->save(autoConnectionInput('synthetic-new',$old));$_SESSION=$session;
        $fresh=autoConnectionController($store,$lock);$fresh->synchronize();assertFalseValue($access->matchesCurrentConnection($id));
        try{$fresh->assertRevision($old);throw new LogicException('old credentials used');}catch(RuntimeException $e){assertSameValue('connection_settings_changed',$e->getMessage());}
    }finally{phase2RemoveDirectory($root);}
});

test('Automatic connection atomic write failure preserves old settings and rejects unsafe source path',static function():void {
    [$root,$store]=autoConnectionFixture();try{
        $c=autoConnectionController($store,new BackupApp\Database\FileDatabaseOperationLock($root.'/global.lock'));$c->save(autoConnectionInput());$before=file_get_contents($root.'/private/settings.json');
        $failed=new BackupApp\Database\ConnectionSettingsStore($root.'/private',$root.'/source',static fn():string=>'S-1-5-21-111-222-333-1001',static fn():bool=>false);
        try{$failed->replace(array_replace($store->read(),['password'=>'synthetic-new']),$c->revision());throw new LogicException('atomic failure ignored');}catch(RuntimeException){}
        assertSameValue($before,file_get_contents($root.'/private/settings.json'));assertSameValue([],glob($root.'/private/*.tmp'));
        $unsafe=new BackupApp\Database\ConnectionSettingsStore($root.'/source/private',$root.'/source',static fn():string=>'S-1-5-21-111-222-333-1001');
        try{$unsafe->read();throw new LogicException('source path accepted');}catch(RuntimeException){}
    }finally{phase2RemoveDirectory($root);}
});

test('Automatic connection Settings owns credential form and disconnected Export hides stale lists and controls',static function():void {
    $data=['csrfToken'=>'fixture-csrf','connectionData'=>['host'=>'127.0.0.1','port'=>3306,'username'=>'fixture_user','password'=>'synthetic-reader-password',
        'password_saved'=>true,'revision'=>str_repeat('a',32)],'isConnected'=>false,'databases'=>['autoprintdb'],
        'connectionStatus'=>['connected'=>false,'error'=>'ไปที่ Settings เพื่อแก้ไขการเชื่อมต่อ','server'=>[]]];
    $settings=BackupApp\Support\View::render('settings',$data,'settings',['app_name'=>'Fixture']);
    foreach(['name="host"','name="port"','name="username"','name="password"','type="password"','name="clear_password"','name="settings_revision"','save_connection_settings','test_connection_settings'] as $term){assertContainsValue($term,$settings);}
    assertNotContainsValue('synthetic-reader-password',$settings);assertNotContainsValue('name="password" value=',$settings);
    $export=BackupApp\Support\View::render('backup',$data,'backup',['app_name'=>'Fixture']);
    foreach(['name="host"','name="password"','name="username"','value="autoprintdb"','data-job-mode="all"'] as $term){assertNotContainsValue($term,$export);}
    assertContainsValue('Disconnected',$export);assertContainsValue('page=settings',$export);assertContainsValue('disabled',$export);
});

test('Automatic connection changed revision blocks both native database adapters before their first connection',static function():void {
    $check=static function():void {throw new RuntimeException('connection_settings_changed');};
    $operation=new BackupApp\Backup\MysqliBackupOperation(new BackupApp\Database\ConnectionFactory(['connect_timeout_seconds'=>1]),
        new BackupApp\Database\DatabaseRepository(),new BackupApp\Backup\BackupService(sys_get_temp_dir()),
        ['host'=>'127.0.0.1','port'=>3306,'username'=>'fixture_user','password'=>'synthetic-reader-password'],['autoprintdb'],$check);
    try{$operation->start();throw new LogicException('stale reader connected');}catch(RuntimeException $e){assertSameValue('connection_settings_changed',$e->getMessage());}
    $database=new BackupApp\Import\MysqliImportDatabase([],'synthetic-target-password',$check);
    try{$database->start([]);throw new LogicException('stale import connected');}catch(RuntimeException $e){assertSameValue('connection_settings_changed',$e->getMessage());}
    $id=str_repeat('a',32);BackupApp\Import\ImportAccess::register($id,str_repeat('b',32));
    try{BackupApp\Import\ImportAccess::requireConnectionRevision($id,str_repeat('c',32));throw new LogicException('old import context accepted');}
    catch(RuntimeException $e){assertSameValue('import_connection_settings_changed',$e->getMessage());}
});

test('Automatic connection private evidence rejects wrong SID tampered binding and malformed storage',static function():void {
    [$root,$store]=autoConnectionFixture();try{
        $c=autoConnectionController($store,new BackupApp\Database\FileDatabaseOperationLock($root.'/global.lock'));$c->save(autoConnectionInput());
        $wrong=new BackupApp\Database\ConnectionSettingsStore($root.'/private',$root.'/source',static fn():string=>'S-1-5-21-111-222-333-9999');
        try{$wrong->read();throw new LogicException('wrong SID accepted');}catch(RuntimeException $e){assertSameValue('connection_settings_invalid',$e->getMessage());}
        $original=file_get_contents($root.'/private/settings.json');$record=json_decode($original,true);
        foreach([array_replace($record,['server_uuid'=>'wrong']),array_replace($record,['username'=>'--option'])] as $bad){
            file_put_contents($root.'/private/settings.json',json_encode($bad));
            try{$store->read();throw new LogicException('wrong binding accepted');}catch(RuntimeException){}
        }
        file_put_contents($root.'/private/settings.json','{malformed');assertFalseValue(autoConnectionController($store,new BackupApp\Database\FileDatabaseOperationLock($root.'/global.lock'))->verify()['connected']);
    }finally{phase2RemoveDirectory($root);}
});

test('Automatic connection requires a permitted local Host as well as a direct loopback peer',static function():void {
    $auth=new BackupApp\Support\Auth();
    foreach(['localhost','LOCALHOST:8081','127.0.0.1:8787','[::1]:8081'] as $host){assertTrueValue($auth->isAuthorized('127.0.0.1',$host));}
    foreach(['','attacker.example','localhost.attacker.example','localhost:0','localhost:65536','localhost,attacker.example','localhost.','127.0.0.1@attacker.example'] as $host){assertFalseValue($auth->isAuthorized('127.0.0.1',$host));}
    assertFalseValue($auth->isAuthorized('192.0.2.1','localhost'));
});

test('Automatic connection Windows private storage validates actual synthetic NTFS ACL and atomic overwrite',static function():void {
    if(PHP_OS_FAMILY!=='Windows'){skipTest('Windows NTFS required');}
    $root=phase2TempDirectory('automatic-native-private');
    $id=substr(hash('sha256',strtolower(str_replace('\\','/',realpath($root)))),0,32);
    $base=getenv('LOCALAPPDATA');assertTrueValue(is_string($base)&&$base!=='');
    $directory=$base.'/BackupPhpMyAdmin/ConnectionSettings/'.$id;assertFalseValue(file_exists($directory),'Synthetic ownership collision');
    try{
        $store=BackupApp\Database\ConnectionSettingsStore::forProject($root);
        $s=$store->replace(['host'=>'127.0.0.1','port'=>3306,'username'=>'fixture_user','password'=>'synthetic-offline-only'],null);
        assertSameValue('synthetic-offline-only',$store->read()['password']);
        $store->replace(array_replace($s,['password'=>'synthetic-replacement']),$s['revision']);
        assertSameValue('synthetic-replacement',$store->read()['password']);
    }finally{
        // This test owns only this exact previously absent leaf and its two fixed files.
        foreach(['settings.json','settings.lock'] as $name){if(is_file($directory.'/'.$name)){unlink($directory.'/'.$name);}}
        if(is_dir($directory)){rmdir($directory);}phase2RemoveDirectory($root);
    }
});
