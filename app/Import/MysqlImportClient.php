<?php

declare(strict_types=1);

namespace BackupApp\Import;

use BackupApp\Backup\MysqldumpProcess;
use BackupApp\Backup\TemporaryMysqlCredentials;
use RuntimeException;

final class MysqlImportClient implements ImportClient
{
    public const BINARY='C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysql.exe';
    public const SHA256='87a1539a9bfec68bfb4a66b7e5d0079418ed8c0084cb22645e9fc3145aad3797';
    public const OPTIONS=['--no-login-paths','--protocol=TCP','--host=127.0.0.1','--port=3306',
        '--default-character-set=utf8mb4','--binary-mode','--batch','--skip-system-command',
        '--local-infile=0','--skip-reconnect','--connect-timeout=5'];

    public function __construct(private readonly MysqldumpProcess $process=new MysqldumpProcess(),private readonly ?array $profile=null){}
    /** @param array<string,mixed> $job @return list<string> */
    public static function command(array $job,string $credentials):array
    {
        ImportPolicy::assertJob($job);
        if($credentials===''||preg_match('/[\x00-\x1f]/',$credentials)===1){throw new RuntimeException('import_credential_path_invalid');}
        $options=self::OPTIONS;if(($job['mode']??'')==='saved_profile'){$options=array_values(array_filter($options,static fn(string $s):bool=>!str_starts_with($s,'--host=')&&!str_starts_with($s,'--port=')));}
        return [self::BINARY,'--defaults-file='.$credentials,...$options,
            '--user='.$job['username'],'--database='.$job['target']];
    }
    public static function verifyBinary():void
    {
        if(!is_file(self::BINARY)||is_link(self::BINARY)||!hash_equals(self::SHA256,(string)hash_file('sha256',self::BINARY))){throw new RuntimeException('import_binary_identity_mismatch');}
        $out=tmpfile();$err=tmpfile();if($out===false||$err===false){throw new RuntimeException('import_probe_failed');}
        try{
            $exit=(new MysqldumpProcess())->run([self::BINARY,'--no-defaults','--no-login-paths','--version'],$out,$err,10,4096,4096);
            rewind($out);$text=stream_get_contents($out,4096);
            if($exit!==0||!str_contains((string)$text,'Ver 8.4.3 for Win64 on x86_64 (MySQL Community Server - GPL)')){throw new RuntimeException('import_binary_version_mismatch');}
        }finally{fclose($out);fclose($err);}
    }
    public function execute(array $job,ImportArtifactVerifier $artifacts,string $password):array
    {
        ImportPolicy::assertJob($job);self::verifyBinary();
        if(($job['mode']??'')==='saved_profile'){
            foreach(['host','port','username','server_uuid','account'] as $key){if(($this->profile[$key]??null)!==($job[$key]??null)){throw new RuntimeException('import_connection_settings_changed');}}
            $password=$this->profile['password'];
        }
        $storage=new TemporaryMysqlCredentials();$input=null;$out=null;$err=null;
        try{
            $options=$storage->create($password,$job['username'],$this->profile['host']??'127.0.0.1',$this->profile['port']??3306);
            $sql=$storage->stagingPath('input.sql');$artifacts->copyVerified($job['artifact'],$sql);
            $input=@fopen($sql,'rb');$out=@fopen($storage->stagingPath('stdout'),'xb+');$err=@fopen($storage->stderrPath(),'xb+');
            if($input===false||$out===false||$err===false){throw new RuntimeException('import_process_storage_failed');}
            $exit=$this->process->run(self::command($job,$options),$out,$err,3600,1048576,65536,'',null,$input);
            if($exit!==0){throw new RuntimeException('mysql_import_exit_'.$exit);}
            if((fstat($err)['size']??1)!==0){throw new RuntimeException('mysql_import_diagnostic_output');}
            if((fstat($out)['size']??1)!==0){throw new RuntimeException('mysql_import_unexpected_results');}
            return ['client'=>'mysql','version'=>'8.4.3','exit_code'=>0,'input_sha256'=>$job['artifact']['sha256']];
        }finally{
            foreach([$input,$out,$err] as $h){if(is_resource($h)){fclose($h);}}
            $storage->close();
        }
    }
}
