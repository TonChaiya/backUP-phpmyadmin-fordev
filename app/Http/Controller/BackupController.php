<?php
declare(strict_types=1);
namespace BackupApp\Http\Controller;
use BackupApp\Backup\BackupJobAccess;
use BackupApp\Backup\MysqldumpCommand;
use BackupApp\Database\AuthorizedSourcePolicy;
use BackupApp\Database\ConnectionSettingsStore;
use BackupApp\Database\ConnectionFactory;
use BackupApp\Database\DatabaseRepository;
use BackupApp\Database\DatabaseOperationLock;
use BackupApp\Database\FileDatabaseOperationLock;
use Closure;
use RuntimeException;
use Throwable;

/** Saved reader configuration and real verification; never puts a password in Session. */
final class BackupController
{
    private Closure $probe;
    private bool $loaded=false;
    private ?array $settings=null;
    public function __construct(private ConnectionFactory $factory,private DatabaseRepository $repository,
        private ConnectionSettingsStore $store,private DatabaseOperationLock $lock,?callable $probe=null)
    {
        $this->probe=$probe!==null?Closure::fromCallable($probe):function(array $s):array {
            MysqldumpCommand::assertConnection($s);
            $db=$this->factory->make($s['host'],$s['port'],$s['username'],$s['password']);
            try{return ['identity'=>MysqldumpCommand::verifyConnection($db,$s)]+$this->repository->catalog($db);}
            finally{$db->close();}
        };
    }
    public static function forApplication(array $config,string $projectRoot):self
    {
        return new self(new ConnectionFactory($config),new DatabaseRepository(),ConnectionSettingsStore::forProject($projectRoot),
            new FileDatabaseOperationLock($config['database_operation_lock_path']));
    }
    private function load():?array
    {
        if(!$this->loaded){$this->settings=$this->store->read();$this->loaded=true;}return $this->settings;
    }
    public function revision():?string{return $this->load()['revision']??null;}
    public function metadata():array
    {
        try{$s=$this->load();}catch(Throwable){$s=null;}
        return ['host'=>$s['host']??'127.0.0.1','port'=>$s['port']??3306,'username'=>$s['username']??'root',
            'revision'=>$s['revision']??'','profile_saved'=>$s!==null,'password_saved'=>is_string($s['password']??null)&&$s['password']!==''];
    }
    public function synchronize():void
    {
        try{$s=$this->load();}catch(Throwable $e){unset($_SESSION['connection'],$_SESSION['databases'],$_SESSION['saved_connection_check']);throw $e;}
        $revision=$s['revision']??null;
        if(($_SESSION['saved_connection_revision']??null)!==$revision||!isset($_SESSION['backup_connection_context'])){
            (new BackupJobAccess())->rotateConnectionContext();unset($_SESSION['connection'],$_SESSION['databases'],$_SESSION['saved_connection_check']);
        }
        $_SESSION['saved_connection_revision']=$revision;
        if(isset($_SESSION['connection']['password'])){unset($_SESSION['connection']['password']);}
    }
    public function credentials():array
    {
        $this->synchronize();$s=$this->load();
        if($s===null){throw new RuntimeException('connection_settings_missing');}
        return array_intersect_key($s,array_flip(['host','port','username','password','server_uuid','product_version','account']));
    }
    /** Called under the shared operation lock immediately before opening MySQL. */
    public function assertRevision(?string $expected):void
    {
        $current=$this->store->read();
        if($expected===null||($current['revision']??null)!==$expected){
            throw new RuntimeException('connection_settings_changed');
        }
    }
    public function verify():array
    {
        unset($_SESSION['connection'],$_SESSION['databases'],$_SESSION['saved_connection_check']);
        $attempted=false;
        try{
            $settings=$this->credentials();$attempted=true;$result=$this->probeSettings($settings);$this->assertRevision($this->revision());
            $_SESSION['connection']=array_intersect_key($settings,array_flip(['host','port','username']));
            $_SESSION['databases']=$result['databases'];
            $_SESSION['saved_connection_check']=['revision'=>$this->revision(),'checked_at'=>$result['checked_at'],'connected'=>true];
            return $result;
        }catch(Throwable){return ['connected'=>false,'databases'=>[],'server'=>[],'checked_at'=>$attempted?gmdate('c'):null,
            'error'=>'Disconnected — ไปที่ Settings เพื่อตรวจโปรไฟล์ MySQL, server identity และสิทธิ์ของบัญชี ระบบไม่รีเซ็ตรหัสหรือเพิ่มสิทธิ์อัตโนมัติ'];}
    }
    private function probeSettings(array $settings):array
    {
        ConnectionSettingsStore::validateConnection($settings);
        $r=($this->probe)($settings);MysqldumpCommand::assertServer($r['identity']??[],$settings);
        $databases=$r['databases']??null;if(!is_array($databases)){throw new RuntimeException('connection_catalog_invalid');}
        $policy=new AuthorizedSourcePolicy();foreach($databases as $database){if(!is_string($database)||!$policy->isAuthorized($database)){throw new RuntimeException('connection_catalog_invalid');}}
        $system=$r['system_databases']??[];$unsupported=$r['unsupported_databases']??[];
        if(!is_array($system)||!is_array($unsupported)){throw new RuntimeException('connection_catalog_invalid');}
        foreach($system as $name){if(!is_string($name)||!$policy->isSystem($name)){throw new RuntimeException('connection_catalog_invalid');}}
        foreach($unsupported as $name){if(!is_string($name)||$policy->isProtected($name)||$policy->isSystem($name)||$policy->isAuthorized($name)){throw new RuntimeException('connection_catalog_invalid');}}
        return ['connected'=>true,'databases'=>$policy->filterAuthorized($databases),'checked_at'=>gmdate('c'),'error'=>'',
            'system_databases'=>array_values(array_unique($system)),'unsupported_databases'=>array_values(array_unique($unsupported)),
            'server'=>array_intersect_key($r['identity'],array_flip(['version','port','account','server_uuid']))];
    }
    private function candidate(array $input):array
    {
        $old=$this->store->read();$password=$input['password']??'';
        if(!is_string($password)||!is_string($input['host']??null)||!is_string($input['username']??null)
            ||!is_string($input['port']??null)||preg_match('/^[0-9]{1,5}$/D',$input['port'])!==1){throw new RuntimeException('connection_settings_invalid');}
        $clear=($input['clear_password']??'')==='1';if($clear&&$password!==''){throw new RuntimeException('connection_password_conflict');}
        $password=$clear||($input['replace_password']??'')==='1'?$password:($password!==''?$password:(string)($old['password']??''));
        $s=['host'=>trim($input['host']),'port'=>(int)$input['port'],'username'=>trim($input['username']),'password'=>$password];
        ConnectionSettingsStore::validateConnection($s);return $s;
    }
    public function test(array $input):array
    {
        try{return $this->probeSettings($this->candidate($input));}
        catch(Throwable){return ['connected'=>false,'databases'=>[],'server'=>[],'error'=>'ทดสอบไม่ผ่าน ตรวจโปรไฟล์ MySQL, สิทธิ์และ Private Storage; ไม่มีการบันทึกหรือเปลี่ยนสิทธิ์'];}
    }
    public function save(array $input):void
    {
        $handle=$this->lock->acquire('connection-settings-save',false);
        if($handle===null){throw new RuntimeException('connection_operation_busy');}
        try{
            $revision=$input['settings_revision']??'';if(!is_string($revision)){throw new RuntimeException('connection_settings_invalid');}
            $old=$this->store->read();$candidate=$this->candidate($input);
            $result=$this->probeSettings($candidate);$identity=$result['server'];
            $changed=$old!==null&&(($old['server_uuid']??null)!==$identity['server_uuid']||$old['host']!==$candidate['host']||$old['port']!==$candidate['port']||($old['account']??($old['username'].'@localhost'))!==$identity['account']);
            if($changed&&($input['confirm_identity_change']??'')!=='1'){throw new RuntimeException('connection_identity_confirmation_required');}
            $this->store->replace($candidate+['server_uuid'=>$identity['server_uuid'],'product_version'=>$identity['version'],'account'=>$identity['account']],$revision===''?null:$revision);
            $this->loaded=false;$this->settings=null;$this->synchronize();
        }finally{$this->lock->release($handle);}
    }
    public static function errorMessage(string $code):string
    {
        return match($code){
            'connection_operation_busy'=>'มีงาน Export/Import กำลังทำงาน กรุณารอจบก่อนเปลี่ยน Settings',
            'connection_settings_stale'=>'Settings เปลี่ยนจากอีกหน้าต่างแล้ว กรุณาโหลดหน้าใหม่ก่อนบันทึก',
            'connection_password_conflict'=>'เลือกเปลี่ยนรหัสหรือเคลียร์รหัสอย่างใดอย่างหนึ่ง',
            'connection_identity_confirmation_required'=>'ตัวตน Server/บัญชีเปลี่ยน กรุณายืนยันการเปลี่ยนใน Settings อย่างชัดเจน',
            default=>'ไปที่ Settings เพื่อตรวจ Host/Port/Username/Password และ Private Storage/SID/NTFS ACL ระบบไม่ Reset หรือเพิ่มสิทธิ์',
        };
    }
}

