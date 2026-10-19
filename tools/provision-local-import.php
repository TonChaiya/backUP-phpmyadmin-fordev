<?php

declare(strict_types=1);

// CLI only. Default is plan-only, no database connection. --apply requires human authorization.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
spl_autoload_register(static function(string $class):void{
    $prefix='BackupApp\\';if(!str_starts_with($class,$prefix)){return;}
    $path=dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($path)){require $path;}
});
use BackupApp\Import\ImportJobStore;
use BackupApp\Import\ImportPolicy;
use BackupApp\Backup\MysqldumpCommand;
use BackupApp\Backup\MysqldumpProcess;
use BackupApp\Support\Config;
use BackupApp\Support\RuntimeDataBootstrap;

$id=$argv[1]??'';$apply=($argv[2]??'')==='--apply';
$config=Config::load(dirname(__DIR__).'/config/app.php',dirname(__DIR__).'/config/local.php');
$config=(new RuntimeDataBootstrap())->prepare($config,dirname(__DIR__));
$store=new ImportJobStore($config['restore_job_path'].'/local-import',$config['restore_integration_ownership_path'].'/local-import');
try{
    $job=$store->read($id);ImportPolicy::assertNames($job['target'],$job['username'],$id);
    if($job['status']!=='awaiting_provisioning'){throw new RuntimeException('import_job_not_provisionable');}
    if(!$apply){
        echo json_encode(['mode'=>'PLAN_ONLY_NO_CONNECTION','id'=>$id,'endpoint'=>'127.0.0.1:3306',
            'server_uuid'=>MysqldumpCommand::SERVER_UUID,'create_database'=>$job['target'],
            'create_account'=>$job['username'].'@localhost','exact_grants'=>ImportPolicy::expectedGrants($job['target'],$job['username']),
            'cleanup'=>'none; retain target and account','authorization_required'=>true],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;exit;
    }
    // Administrative password arrives via stdin only; never arguments/environment/config.
    $adminPassword=rtrim((string)fgets(STDIN,4097),"\r\n");
    mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
    $admin=new mysqli('127.0.0.1','root',$adminPassword,null,3306);$adminPassword=null;
    $facts=$admin->query('SELECT @@version AS version, @@port AS port, @@server_uuid AS server_uuid, @@global.partial_revokes AS partial_revokes')->fetch_assoc();
    if(($facts['version']??'')!=='8.4.3'||(int)($facts['port']??0)!==3306
        ||($facts['server_uuid']??'')!==MysqldumpCommand::SERVER_UUID||(string)($facts['partial_revokes']??'')!=='0'){
        throw new RuntimeException('import_server_mismatch');
    }
    $target=$job['target'];$username=$job['username'];
    $collision=$admin->query("SELECT COUNT(*) AS n FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '{$target}'")->fetch_assoc();
    $account=$admin->query("SELECT COUNT(*) AS n FROM mysql.user WHERE User = '{$username}'")->fetch_assoc();
    if((int)$collision['n']!==0||(int)$account['n']!==0||is_file($store->ownershipPath($id))){throw new RuntimeException('import_target_or_account_collision');}
    // Exact generated names only. No IF NOT EXISTS, DROP, wildcard cleanup, or source access.
    $admin->query('CREATE DATABASE `'.$target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
    $events=$job['events'];$events[]=['at'=>gmdate('c'),'code'=>'exact_new_database_created','server_uuid'=>MysqldumpCommand::SERVER_UUID,'target'=>$target];
    $store->update($id,['events'=>$events]);
    $password=rtrim(strtr(base64_encode(random_bytes(36)),'+/','-_'),'=');
    $admin->query("CREATE USER '{$username}'@'localhost' IDENTIFIED BY '{$password}'");
    $grant=ImportPolicy::expectedGrants($target,$username)[1];$admin->query($grant);
    $r=$admin->query("SHOW GRANTS FOR '{$username}'@'localhost'");$actual=[];while($row=$r->fetch_row()){$actual[]=$row[0];}
    ImportPolicy::assertGrants($actual,$target,$username);$admin->close();
    $reader=new mysqli('127.0.0.1',$username,$password,null,3306);ImportPolicy::verifyConnection($reader,$job);$reader->close();
    $store->recordOwnership($id,['id'=>$id,'target'=>$target,'username'=>$username,'server_uuid'=>MysqldumpCommand::SERVER_UUID,
        'created_new'=>true,'account_created_new'=>true,'created_at'=>gmdate('c')]);
    $events[]=['at'=>gmdate('c'),'code'=>'exact_account_grants_and_creation_evidence_verified'];$store->update($id,['events'=>$events]);
    $out=tmpfile();$err=tmpfile();
    try{
        $exit=(new MysqldumpProcess())->run(['C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe','-NoLogo','-NoProfile','-NonInteractive','-Command',
            '$ErrorActionPreference="Stop"; try { $p=[Console]::In.ReadToEnd(); Set-Clipboard -Value $p; exit 0 } catch { exit 1 }'],
            $out,$err,10,4096,4096,$password);
        if($exit!==0){throw new RuntimeException('import_credential_clipboard_delivery_failed');}
    }finally{fclose($out);fclose($err);$password=null;}
    echo json_encode(['provisioned'=>true,'target'=>$target,'account'=>$username.'@localhost','password_delivery'=>'Windows Clipboard only','no_import_executed'=>true]),PHP_EOL;
}catch(Throwable $e){
    // Do not emit DB diagnostics, statement text, passwords or stack arguments.
    $code=$e->getMessage();if(preg_match('/^import_[a-z0-9_]+$/D',$code)!==1){$code='import_provisioning_failed_reconciliation_required';}
    fwrite(STDERR,$code.PHP_EOL);exit(1);
}
