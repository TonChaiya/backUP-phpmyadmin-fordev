<?php
declare(strict_types=1);

function presentationJob():array {
    return ['id'=>str_repeat('a',32),'target'=>'local_import_20261018_'.str_repeat('a',16),
        'status'=>'failed','error'=>'import_target_schema_or_rows_mismatch','verification'=>null,
        'artifact'=>['database'=>'autoprintdb','relative_path'=>'autoprintdb/example.sql','bytes'=>100,'sha256'=>str_repeat('b',64)]];
}
function presentationReview(array $job):array {
    return ['job_id'=>$job['id'],'source'=>$job['artifact']['database'],'target'=>$job['target'],'artifact'=>$job['artifact'],
        'accepted_at'=>'2026-10-19T02:51:02+00:00','effective_metadata_equal'=>true,'current_source_target_equivalence'=>'PASS',
        'backup_snapshot_value_equivalence'=>'NOT_VERIFIED','ddl'=>['t'=>['equivalent'=>true]],
        'data'=>['t'=>['equal'=>true,'rows'=>2,'target_rows'=>2,'source_sha256'=>str_repeat('c',64),'target_sha256'=>str_repeat('c',64),
            'method'=>'exact_sorted_lossless_row_bytes_with_multiplicity']]];
}

test('Local presentation separates execution schema live values and unverified snapshot without rewriting failure',static function():void {
    $job=presentationJob();$original=$job;$review=presentationReview($job);
    $p=BackupApp\Import\ImportResultPresentation::forJob($job,$review);
    assertSameValue('completed',$p['execution']);assertSameValue('verified',$p['schema']);
    assertSameValue('verified',$p['live_data']);assertSameValue('not_verified',$p['snapshot_data']);
    assertSameValue('partial',$p['verification']);assertSameValue('failed',$p['recorded_status']);assertSameValue($original,$job);
    $review['backup_snapshot_value_equivalence']='PASS';
    assertSameValue('not_verified',BackupApp\Import\ImportResultPresentation::forJob($job,$review)['snapshot_data']);
    assertSameValue('failed',BackupApp\Import\ImportResultPresentation::forJob($job)['verification']);
});

test('Local presentation never upgrades native success row counts into exact data fidelity',static function():void {
    $job=presentationJob();$job['status']='succeeded';$job['error']=null;
    $job['verification']=['schema_and_row_counts_match'=>true,'data_value_equality'=>'not_verified','client'=>['exit_code'=>0]];
    $p=BackupApp\Import\ImportResultPresentation::forJob($job);
    assertSameValue('completed',$p['execution']);assertSameValue('verified',$p['schema']);
    assertSameValue('not_verified',$p['live_data']);assertSameValue('not_verified',$p['snapshot_data']);assertSameValue('partial',$p['verification']);
    $job['status']='running';$job['verification']=null;assertSameValue('pending',BackupApp\Import\ImportResultPresentation::forJob($job)['verification']);
    $job['status']='failed';$job['error']='import_operation_failed';assertSameValue('failed',BackupApp\Import\ImportResultPresentation::forJob($job)['verification']);
});

test('Local presentation rejects unrelated incomplete or mismatching review evidence',static function():void {
    $job=presentationJob();$good=presentationReview($job);$bad=[];
    $r=$good;$r['job_id']=str_repeat('d',32);$bad[]=$r;
    $r=$good;$r['target']='another_target';$bad[]=$r;
    $r=$good;$r['artifact']['sha256']=str_repeat('d',64);$bad[]=$r;
    foreach($bad as $r){$p=BackupApp\Import\ImportResultPresentation::forJob($job,$r);assertSameValue('not_verified',$p['schema']);assertSameValue('not_verified',$p['live_data']);}
    $r=$good;$r['data']['t']['equal']=false;
    assertSameValue('not_verified',BackupApp\Import\ImportResultPresentation::forJob($job,$r)['live_data']);
    $r=$good;$r['data']=[];assertSameValue('not_verified',BackupApp\Import\ImportResultPresentation::forJob($job,$r)['live_data']);
    $r=$good;$r['ddl']['t']['equivalent']=false;
    assertSameValue('not_verified',BackupApp\Import\ImportResultPresentation::forJob($job,$r)['schema']);
    $r=$good;$r['ddl']=[];
    assertSameValue('not_verified',BackupApp\Import\ImportResultPresentation::forJob($job,$r)['schema']);
});

test('Local presentation loads only bounded exact job review files and supplies safe credential guidance',static function():void {
    $root=phase2TempDirectory('presentation');$job=presentationJob();
    try{
        file_put_contents($root.'/'.$job['id'].'.acceptance-1.json',json_encode(presentationReview($job)));
        file_put_contents($root.'/'.$job['id'].'.acceptance-2.json','{broken');
        $r=BackupApp\Import\ImportResultPresentation::loadReview($root,$job);assertSameValue($job['id'],$r['job_id']);
        foreach(['import_credential_required','import_connect_export_reader_first','import_provisioning_required','import_operation_failed','unexpected synthetic-secret SQL traceback'] as $code){
            $message=BackupApp\Import\ImportResultPresentation::message($code);assertNotContainsValue('synthetic-secret',$message);assertNotContainsValue('traceback',$message);assertNotContainsValue('SQLSTATE',$message);
        }
        assertContainsValue('รหัสผ่าน',BackupApp\Import\ImportResultPresentation::message('import_credential_required'));
        assertContainsValue('Export',BackupApp\Import\ImportResultPresentation::message('import_connect_export_reader_first'));
    }finally{phase2RemoveDirectory($root);}
});

test('Local presentation views expose distinct verification scopes and actionable credential boundaries',static function():void {
    $common=['csrfToken'=>'token','pageTitle'=>'Import','heading'=>'Import'];
    $html=BackupApp\Support\View::render('import',$common+['importArtifacts'=>[]],'import',['app_name'=>'Local manager']);
    foreach(['data-import-execution','data-import-schema','data-import-live-data','data-import-snapshot-data','data-import-verification','Snapshot','ไม่เขียนทับ','รหัสผ่านเว็บไซต์'] as $needle){assertContainsValue($needle,$html);}
    $html=BackupApp\Support\View::render('backup',$common+['databases'=>[],'connectionData'=>[]],'backup',['app_name'=>'Local manager']);
    foreach(['InnoDB','base table','Views','Triggers','Stored procedures/functions','Events','Non-InnoDB','รหัสผ่านเว็บไซต์'] as $needle){assertContainsValue($needle,$html);}
    $html=BackupApp\Support\View::render('settings',$common,'settings',['app_name'=>'Local manager']);
    assertContainsValue('ไม่รีเซ็ตรหัสผ่าน',$html);
    $html=BackupApp\Support\View::render('history',$common+['backupFiles'=>[]],'history',['app_name'=>'Local manager']);
    assertContainsValue('ไม่ใช่ผลยืนยัน Import',$html);assertContainsValue('SHA-256',$html);
});
