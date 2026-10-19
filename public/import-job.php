<?php

declare(strict_types=1);

use BackupApp\Import\ImportAccess;
use BackupApp\Import\ImportArtifactVerifier;
use BackupApp\Import\ImportJobStore;
use BackupApp\Import\ImportRunner;
use BackupApp\Import\ImportResultPresentation;
use BackupApp\Import\MysqliImportDatabase;
use BackupApp\Import\MysqlImportClient;
use BackupApp\Database\FileDatabaseOperationLock;
use BackupApp\Http\Controller\SettingsController;
use BackupApp\Support\Auth;
use BackupApp\Support\BackupPathPolicy;
use BackupApp\Support\Security;

$config=require dirname(__DIR__).'/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
function importJson(array $payload,int $status=200):never
{
    if(isset($payload['error'])&&is_string($payload['error'])){$payload['message']=ImportResultPresentation::message($payload['error']);}
    if($status===419){header('HTTP/1.1 419 Authentication Timeout');}else{http_response_code($status);}
    echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}
function importJobView(ImportJobStore $store,array $job,string $reviewDirectory):array
{
    try{$store->requireOwnership($job);$job['provisioning_ready']=true;}catch(RuntimeException){$job['provisioning_ready']=false;}
    $job['source_reader_connected']=is_array($_SESSION['connection']??null);
    $job['presentation']=ImportResultPresentation::forJob($job,ImportResultPresentation::loadReview($reviewDirectory,$job));
    return $job;
}
if(!(new Auth())->isAuthorized((string)($_SERVER['REMOTE_ADDR']??''))){importJson(['ok'=>false,'error'=>'loopback_only'],403);}
$isGet=($_SERVER['REQUEST_METHOD']??'GET')==='GET';
$csrf=$isGet?($_SERVER['HTTP_X_CSRF_TOKEN']??null):($_POST['csrf_token']??null);
if(!is_string($csrf)||!Security::validateCsrf($csrf)){importJson(['ok'=>false,'error'=>'csrf_invalid'],419);}
try{
    $store=new ImportJobStore($config['restore_job_path'].'/local-import',$config['restore_integration_ownership_path'].'/local-import');
    $policy=new BackupPathPolicy($config['allowed_backup_roots'],dirname(__DIR__));
    $root=(new SettingsController($policy))->activePath($config['backup_path']);
    $artifacts=new ImportArtifactVerifier($root,$config['job_path']);
    $action=$isGet?(string)($_GET['action']??''):(string)($_POST['action']??'');
    if($action==='status'&&$isGet){
        $id=is_string($_GET['job_id']??null)?$_GET['job_id']:'';ImportAccess::requireOwned($id);
        importJson(['ok'=>true,'job'=>importJobView($store,$store->recover($id),$config['restore_job_path'].'/local-import')]);
    }
    if($isGet){importJson(['ok'=>false,'error'=>'method_not_allowed'],405);}
    if(isset($_POST['target'])||isset($_POST['username'])||isset($_POST['host'])||isset($_POST['port'])){throw new RuntimeException('import_browser_scope_override_rejected');}
    if($action==='prepare'){
        $relative=is_string($_POST['artifact']??null)?$_POST['artifact']:'';
        $job=$store->create($artifacts->verify($relative));ImportAccess::register($job['id']);
        importJson(['ok'=>true,'job'=>importJobView($store,$job,$config['restore_job_path'].'/local-import')],201);
    }
    if($action==='execute'){
        $id=is_string($_POST['job_id']??null)?$_POST['job_id']:'';ImportAccess::requireOwned($id);
        if(($_POST['confirmation']??'')!=='IMPORT'){throw new RuntimeException('import_explicit_confirmation_required');}
        $job=$store->read($id);$store->requireOwnership($job);
        $password=is_string($_POST['import_password']??null)?$_POST['import_password']:'';
        if($password===''){throw new RuntimeException('import_credential_required');}
        $reader=$_SESSION['connection']??null;
        if(!is_array($reader)){throw new RuntimeException('import_connect_export_reader_first');}
        $runner=new ImportRunner($store,new FileDatabaseOperationLock($config['database_operation_lock_path']),$artifacts);
        session_write_close();ignore_user_abort(true);@set_time_limit(0);
        $job=$runner->run($id,new MysqliImportDatabase($reader,$password),new MysqlImportClient(),$password);
        $password=null;importJson(['ok'=>true,'job'=>importJobView($store,$job,$config['restore_job_path'].'/local-import')]);
    }
    importJson(['ok'=>false,'error'=>'unknown_action'],400);
}catch(Throwable $e){
    $code=$e->getMessage();if(preg_match('/^(?:import|source|mysql_import|mysqldump)_[a-z0-9_]+$/D',$code)!==1){$code='import_operation_failed';}
    $status=$code==='import_job_not_owned'?404:409;
    importJson(['ok'=>false,'error'=>$code],$status);
}
