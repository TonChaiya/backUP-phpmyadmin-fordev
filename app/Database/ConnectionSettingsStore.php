<?php
declare(strict_types=1);
namespace BackupApp\Database;
use BackupApp\Backup\MysqldumpCommand;
use BackupApp\Support\WindowsPrivateDirectory;
use Closure;
use RuntimeException;

/** Private atomic plaintext, no claim of encryption or protection against the same Windows SID. */
final class ConnectionSettingsStore
{
    private Closure $privateCheck;
    private Closure $replaceFile;
    public function __construct(private readonly string $directory,private readonly string $sourceRoot,?callable $privateCheck=null,?callable $replaceFile=null)
    {
        $guard=new WindowsPrivateDirectory();$this->privateCheck=$privateCheck!==null?Closure::fromCallable($privateCheck):$guard->verify(...);
        $this->replaceFile=$replaceFile!==null?Closure::fromCallable($replaceFile):static fn(string $a,string $b):bool=>@rename($a,$b);
    }
    public static function forProject(string $root):self
    {
        $base=getenv('LOCALAPPDATA');$path=is_string($base)&&$base!==''?rtrim($base,'\\/').'/BackupPhpMyAdmin/ConnectionSettings/'
            .substr(hash('sha256',strtolower(str_replace('\\','/',realpath($root)?:$root))),0,32):'';
        return new self($path,$root);
    }
    public function read():?array
    {
        $this->assertPath();if(!is_dir($this->directory)){return null;}
        $sid=($this->privateCheck)($this->directory,false);$lock=$this->lock(LOCK_SH);
        try{return $this->readLocked($sid);}finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public function replace(array $connection,?string $expectedRevision):array
    {
        $this->assertPath();self::validateConnection($connection);$sid=($this->privateCheck)($this->directory,true);$lock=$this->lock(LOCK_EX);$temp=null;
        try{
            $old=$this->readLocked($sid);if(($old['revision']??null)!==$expectedRevision){throw new RuntimeException('connection_settings_stale');}
            $fields=array_intersect_key($connection,array_flip(['host','port','username','password','server_uuid','product_version','account']));
            if($old!==null&&array_intersect_key($old,$fields)===$fields){return $old;}
            $record=$fields+['version'=>2,'sid'=>$sid,'revision'=>bin2hex(random_bytes(16)),'server_uuid'=>MysqldumpCommand::SERVER_UUID,
                'product_version'=>MysqldumpCommand::VERSION,'updated_at'=>gmdate('c')];
            $payload=json_encode($record,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
            if(strlen($payload)>16384){throw new RuntimeException('connection_settings_oversized');}
            $temp=$this->directory.'/settings-'.bin2hex(random_bytes(16)).'.tmp';
            $h=@fopen($temp,'xb');if($h===false){throw new RuntimeException('connection_settings_write_failed');}
            try{if(fwrite($h,$payload)!==strlen($payload)||!fflush($h)||!fsync($h)){throw new RuntimeException('connection_settings_write_failed');}}finally{fclose($h);}
            ($this->privateCheck)($this->directory,false);
            if(!(($this->replaceFile)($temp,$this->directory.'/settings.json'))){throw new RuntimeException('connection_settings_write_failed');}
            $temp=null;return $record;
        }finally{if($temp!==null&&is_file($temp)){@unlink($temp);}flock($lock,LOCK_UN);fclose($lock);}
    }
    public static function validateConnection(array $s):void
    {
        MysqldumpCommand::assertConnection($s);
        if(!is_string($s['password']??null)||strlen($s['password'])>4096||str_contains($s['password'],"\0")){throw new RuntimeException('connection_settings_invalid');}
    }
    private function readLocked(string $sid):?array
    {
        $file=$this->directory.'/settings.json';if(!file_exists($file)){return null;}
        if(is_link($file)||!is_file($file)||filesize($file)>16384){throw new RuntimeException('connection_settings_invalid');}
        $text=@file_get_contents($file,false,null,0,16385);$s=is_string($text)?json_decode($text,true,16):null;
        if(!is_array($s)||!in_array($s['version']??null,[1,2],true)||($s['sid']??null)!==$sid||!is_string($s['server_uuid']??null)||preg_match('/^[a-f0-9-]{36}$/iD',$s['server_uuid'])!==1
            ||($s['product_version']??null)!==MysqldumpCommand::VERSION||!is_string($s['revision']??null)||preg_match('/^[a-f0-9]{32}$/D',$s['revision'])!==1){throw new RuntimeException('connection_settings_invalid');}
        self::validateConnection($s);return $s;
    }
    private function lock(int $mode)
    {
        $h=@fopen($this->directory.'/settings.lock','c+b');if($h===false||!flock($h,$mode)){if(is_resource($h)){fclose($h);}throw new RuntimeException('connection_settings_unavailable');}return $h;
    }
    private function assertPath():void
    {
        $path=str_replace('\\','/',$this->directory);
        if(preg_match('/^[A-Za-z]:\//D',$path)!==1||preg_match('#(?:^|/)\.\.?(/|$)#',$path)||preg_match('/[\x00-\x1f]/',$path)){throw new RuntimeException('connection_private_storage_unavailable');}
        // Resolve the nearest existing ancestor, including Windows short-name aliases.
        $ancestor=$this->directory;$suffix=[];
        while(!file_exists($ancestor)&&!is_link($ancestor)){
            $parent=dirname($ancestor);if($parent===$ancestor){throw new RuntimeException('connection_private_storage_unsafe');}
            array_unshift($suffix,basename($ancestor));$ancestor=$parent;
        }
        $resolved=realpath($ancestor);if($resolved===false||is_link($ancestor)){throw new RuntimeException('connection_private_storage_unsafe');}
        $path=rtrim(str_replace('\\','/',$resolved),'/').($suffix!==[]?'/'.implode('/',$suffix):'');
        foreach([$this->sourceRoot,(string)($_SERVER['DOCUMENT_ROOT']??$this->sourceRoot)] as $root){
            $root=rtrim(str_replace('\\','/',realpath($root)?:$root),'/');if($root!==''&&(strcasecmp(rtrim($path,'/'),$root)===0||str_starts_with(strtolower($path),strtolower($root.'/')))){throw new RuntimeException('connection_private_storage_unsafe');}
        }
        if(is_link($this->directory)){throw new RuntimeException('connection_private_storage_unsafe');}
    }
}
