<?php
declare(strict_types=1);

// Fixed existing acceptance target only. No SQL execution/import/provisioning or job mutation.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(ini_set('memory_limit','128M')===false){fwrite(STDERR,"acceptance_memory_limit_unavailable\n");exit(1);}
set_time_limit(120);
require __DIR__.'/verification/LocalImportAcceptance.php';
spl_autoload_register(static function(string $class):void{
    if(!str_starts_with($class,'BackupApp\\')){return;}
    $path=dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,10)).'.php';if(is_file($path)){require $path;}
});
$source=null;$target=null;$lockHandle=null;$lock=null;$stage='input';$exitCode=0;
try{
    $raw=stream_get_contents(STDIN,16385);
    if($raw===false||strlen($raw)>16384){throw new RuntimeException('acceptance_input_invalid');}
    $input=json_decode($raw,true,16,JSON_THROW_ON_ERROR);$raw=null;
    if(!is_array($input)||!is_string($input['source_admin_password']??null)
        ||($input['authorization']??'')!=='existing_target_read_only_acceptance'){throw new RuntimeException('acceptance_input_invalid');}
    $config=BackupApp\Support\Config::load(dirname(__DIR__).'/config/app.php',dirname(__DIR__).'/config/local.php');
    $config=(new BackupApp\Support\RuntimeDataBootstrap())->prepare($config,dirname(__DIR__));
    $store=new BackupApp\Import\ImportJobStore($config['restore_job_path'].'/local-import',$config['restore_integration_ownership_path'].'/local-import');
    $job=$store->read('b2197f1be82de698bd6678a09e1507c7');$store->requireOwnership($job);
    if($job['target']!=='local_import_20261018_b2197f1be82de698'||$job['artifact']['database']!=='autoprintdb'||$job['status']!=='failed'){
        throw new RuntimeException('acceptance_exact_failed_job_required');
    }
    $stage='artifact';$artifacts=new BackupApp\Import\ImportArtifactVerifier($config['backup_path'],$config['job_path']);
    if($artifacts->verify($job['artifact']['relative_path'])!==$job['artifact']){throw new RuntimeException('acceptance_artifact_changed');}
    $lock=new BackupApp\Database\FileDatabaseOperationLock($config['database_operation_lock_path']);
    $lockHandle=$lock->acquire('verify-'.$job['id'],false);if($lockHandle===null){throw new RuntimeException('acceptance_database_operation_busy');}
    $factory=new BackupApp\Database\ConnectionFactory(['connect_timeout_seconds'=>5]);
    // The interactively authenticated administrator is used for this one read-only inspection,
    // never routine Import execution. Do not recover passwords from application/browser sessions.
    $stage='source_connect';$source=$factory->make('127.0.0.1',3306,'root',$input['source_admin_password']);
    $identity=$source->query('SELECT @@version AS version, @@port AS port, @@server_uuid AS server_uuid, CURRENT_USER() AS account, CURRENT_ROLE() AS roles, @@global.partial_revokes AS partial_revokes')->fetch_assoc();
    if(($identity['version']??'')!=='8.4.3'||(int)($identity['port']??0)!==3306||($identity['server_uuid']??'')!==BackupApp\Backup\MysqldumpCommand::SERVER_UUID
        ||($identity['account']??'')!=='root@localhost'||($identity['roles']??'')!=='NONE'||(string)($identity['partial_revokes']??'')!=='0'){
        throw new RuntimeException('acceptance_administrator_identity_mismatch');
    }
    // Both inspection connections use the explicitly interactive administrator, READ ONLY below.
    // This is not the native Import runner and does not bypass its dedicated-account gate.
    $stage='target_connect';$target=$factory->make('127.0.0.1',3306,'root',$input['source_admin_password']);
    $targetIdentity=$target->query('SELECT @@version AS version, @@port AS port, @@server_uuid AS server_uuid, CURRENT_USER() AS account, CURRENT_ROLE() AS roles, @@global.partial_revokes AS partial_revokes')->fetch_assoc();
    if($targetIdentity!==$identity){throw new RuntimeException('acceptance_inspection_identity_mismatch');}
    $actualGrants=[];$r=$target->query("SHOW GRANTS FOR 'local_import_bd6678a09e1507c7'@'localhost'");
    while($grant=$r->fetch_row()){$actualGrants[]=$grant[0];}
    BackupApp\Import\ImportPolicy::assertGrants($actualGrants,$job['target'],$job['username']);
    $stage='readonly_transactions';foreach([$source,$target] as $db){
        $db->query("SET SESSION time_zone = '+00:00'");
        $db->query('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->query('SET SESSION TRANSACTION READ ONLY');
        $db->query('SET SESSION max_execution_time = 30000');
        $db->query('START TRANSACTION WITH CONSISTENT SNAPSHOT');
    }
    $adapter=new BackupApp\Import\MysqliImportDatabase([],'');$input=null;
    $snapshot=new ReflectionMethod($adapter,'snapshot');
    $stage='snapshots';$a=$snapshot->invoke($adapter,$source,'autoprintdb');$b=$snapshot->invoke($adapter,$target,$job['target']);
    $schemaNames=['autoprintdb',$job['target']];$extras=[];$types=[];$databaseDefaults=[];
    foreach([$source,$target] as $side=>$db){
        $schema=$db->real_escape_string($schemaNames[$side]);
        $databaseDefaults[$side]=$db->query("SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '{$schema}'")->fetch_assoc();
        foreach([
            'constraints'=>"SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE, ENFORCED FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '{$schema}' ORDER BY TABLE_NAME, CONSTRAINT_NAME",
            'partitions'=>LocalImportAcceptance::partitionQuery($schemaNames[$side]),
            'column_details'=>"SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, DATA_TYPE, COLUMN_TYPE, NUMERIC_PRECISION, NUMERIC_SCALE, DATETIME_PRECISION, CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_COMMENT, SRS_ID FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '{$schema}' ORDER BY TABLE_NAME, ORDINAL_POSITION",
        ] as $key=>$sql){$stage='metadata_'.$side.'_'.$key;$extras[$side][$key]=$db->query($sql)->fetch_all(MYSQLI_ASSOC);}
        foreach($extras[$side]['column_details'] as $column){$types[$side][$column['TABLE_NAME']][]=$column;}
    }
    if($extras[0]!==$extras[1]){throw new RuntimeException('acceptance_effective_extra_metadata_mismatch');}
    $noDdlA=$a;$noDdlB=$b;unset($noDdlA['ddl'],$noDdlB['ddl']);
    if($noDdlA!==$noDdlB){throw new RuntimeException('acceptance_effective_schema_or_counts_mismatch');}
    $report=['accepted_at'=>gmdate('c'),'job_id'=>$job['id'],'source'=>'autoprintdb','target'=>$job['target'],
        'identity'=>$identity,'inspection_role'=>'interactively_authenticated_administrator_read_only_not_import_execution',
        'import_account_grants_unchanged'=>true,'artifact'=>$job['artifact'],'read_only'=>true,'job_status_unchanged'=>$job['status'],
        'database_defaults'=>['source'=>$databaseDefaults[0],'target'=>$databaseDefaults[1]],
        'effective_metadata_equal'=>true,'effective_metadata_sha256'=>hash('sha256',serialize([$noDdlA,$extras[0]])),
        'ddl'=>[],'data'=>[],'limits'=>['max_rows_per_table'=>10000,'max_encoded_bytes_per_side_table'=>8388608,'php_memory_limit'=>'128M'],
        'snapshot_policy'=>'separate_server_enforced_read_only_repeatable_read_snapshots','backup_relation'=>'unchanged_verified_artifact_imported_once; direct_comparison_uses_current_source_snapshot'];
    foreach($a['tables'] as $table){
        $name=$table['TABLE_NAME'];$columns=array_values(array_filter($a['columns'],static fn(array $c):bool=>$c['TABLE_NAME']===$name));
        $targetColumns=array_values(array_filter($b['columns'],static fn(array $c):bool=>$c['TABLE_NAME']===$name));
        $stage='ddl_evidence';$report['ddl'][$name]=LocalImportAcceptance::ddlEvidence($a['ddl'][$name],$b['ddl'][$name],$columns,$targetColumns);
    }
    foreach($a['tables'] as $table){
        $name=$table['TABLE_NAME'];
        $expressions=[];
        foreach($types[0][$name] as $column){
            $stage='data_type_'.$column['DATA_TYPE'];
            array_push($expressions,...LocalImportAcceptance::valueExpressions($column['COLUMN_NAME'],$column['DATA_TYPE']));
        }
        // Row-size guard executes before fetching values. HEX prevents connection-charset conversion.
        $read=static function(mysqli $db,string $schema)use($expressions,$name):Generator{
            $qualified='`'.str_replace('`','``',$schema).'`.`'.str_replace('`','``',$name).'`';
            $sizes=array_map(static fn(string $expr):string=>'COALESCE(OCTET_LENGTH('.$expr.'),0)',$expressions);
            $max=$db->query('SELECT MAX('.implode('+',$sizes).') AS n FROM '.$qualified)->fetch_assoc();
            if((int)($max['n']??0)>2097152){throw new RuntimeException('acceptance_row_size_bound');}
            $result=$db->query('SELECT '.implode(',',$expressions).' FROM '.$qualified,MYSQLI_USE_RESULT);
            try{while($row=$result->fetch_row()){
                foreach($row as &$value){if($value!==null){$value=hex2bin($value);if($value===false){throw new RuntimeException('acceptance_invalid_hex');}}}unset($value);
                yield $row;
            }}finally{$result->free();}
        };
        $stage='exact_data';$report['data'][$name]=LocalImportAcceptance::rows($read($source,'autoprintdb'),$read($target,$job['target']));
        if(!$report['data'][$name]['equal']){throw new RuntimeException('acceptance_exact_data_mismatch');}
    }
    // Detect measurable concurrent DDL outside MVCC before accepting the inspection.
    if($snapshot->invoke($adapter,$source,'autoprintdb')!==$a||$snapshot->invoke($adapter,$target,$job['target'])!==$b){
        throw new RuntimeException('acceptance_schema_changed_during_inspection');
    }
    $report['current_source_target_equivalence']='PASS';
    $report['backup_snapshot_value_equivalence']='NOT_VERIFIED';
    $report['acceptance']='PARTIAL_BACKUP_SNAPSHOT_VALUE_EVIDENCE_MISSING';
    $report['limitations']=['No independent SQL-backup value decoding or historical source data digest exists.',
        'Database defaults are reported separately; dump profile does not export CREATE DATABASE.',
        'Not a new Import execution; original failed job retained. Unsupported types/oversized tables fail closed.'];
    $json=json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
    $reportPath=$config['restore_job_path'].'/local-import/'.$job['id'].'.acceptance-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
    $h=fopen($reportPath,'xb');if($h===false){throw new RuntimeException('acceptance_evidence_write_failed');}
    try{if(fwrite($h,$json)!==strlen($json)||!fflush($h)){throw new RuntimeException('acceptance_evidence_write_failed');}}finally{fclose($h);}
    echo $json;
}catch(Throwable $e){
    $code=$e->getMessage();if(preg_match('/^acceptance_[a-z0-9_]+$/D',$code)!==1){$code='acceptance_inspection_failed';}
    $failure=['acceptance'=>'PARTIAL','checked_at'=>gmdate('c'),'error'=>$code,'stage'=>$stage,'database_error_number'=>$e instanceof mysqli_sql_exception?$e->getCode():null];
    if(isset($report)){$failure['effective_metadata_equal']=$report['effective_metadata_equal'];$failure['ddl']=$report['ddl'];$failure['completed_data_comparisons']=$report['data'];}
    if(isset($config['restore_job_path'],$job['id'])){
        $failurePath=$config['restore_job_path'].'/local-import/'.$job['id'].'.acceptance-failed-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
        $failureHandle=@fopen($failurePath,'xb');
        if(is_resource($failureHandle)){fwrite($failureHandle,json_encode($failure,JSON_PRETTY_PRINT).PHP_EOL);fclose($failureHandle);}
    }
    fwrite(STDERR,json_encode($failure).PHP_EOL);$exitCode=1;
}finally{
    foreach([$source,$target] as $db){if($db instanceof mysqli){try{$db->rollback();$db->close();}catch(Throwable){}}}
    if($lockHandle!==null&&$lock!==null){$lock->release($lockHandle);}
}
exit($exitCode);
