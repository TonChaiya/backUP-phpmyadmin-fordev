<?php
declare(strict_types=1);

function effectiveSchemaFixture():array {
    return ['tables'=>[['TABLE_NAME'=>'t','ENGINE'=>'InnoDB','TABLE_COLLATION'=>'utf8mb4_unicode_ci']],
        'columns'=>[['TABLE_NAME'=>'t','COLUMN_NAME'=>'v','COLUMN_TYPE'=>'varchar(255)','IS_NULLABLE'=>'NO','COLUMN_DEFAULT'=>null,
            'CHARACTER_SET_NAME'=>'utf8mb4','COLLATION_NAME'=>'utf8mb4_unicode_ci','GENERATION_EXPRESSION'=>'']],
        'indexes'=>[],'foreign_keys'=>[],'referential_actions'=>[],'checks'=>[],'rows'=>['t'=>'1'],
        'ddl'=>['t'=>"CREATE TABLE `t` (\n  `v` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"]];
}

test('Effective import comparer accepts observed redundant charset only with equal metadata',static function():void {
    $a=effectiveSchemaFixture();$b=$a;$b['ddl']['t']=str_replace('varchar(255) COLLATE','varchar(255) CHARACTER SET utf8mb4 COLLATE',$a['ddl']['t']);
    $result=BackupApp\Import\MysqliImportDatabase::compare($a,$a,$b);
    assertTrueValue($result['schema_and_row_counts_match']);
    assertSameValue('equivalent_explicit_charset',$result['ddl_evidence']['t']['differences'][0]['reason']);
    assertSameValue('not_verified',$result['data_value_equality']);
    assertTrueValue(BackupApp\Import\MysqliImportDatabase::compare($b,$b,$a)['schema_and_row_counts_match']);
});

test('Interactive acceptance CLI rejects unauthorized and malformed input before database access without secret output',static function():void {
    $cases=[['verify-existing-import.php','{"authorization":"wrong","source_admin_password":"synthetic-do-not-leak","password":"synthetic-do-not-leak"}','acceptance_input_invalid'],
        ['verify-existing-import.php','{synthetic-do-not-leak','acceptance_inspection_failed']];
    foreach($cases as [$file,$input,$expectedError]){
        $p=proc_open([PHP_BINARY,dirname(__DIR__).'/tools/'.$file],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),null,['bypass_shell'=>true]);
        assertTrueValue(is_resource($p));fwrite($pipes[0],$input);fclose($pipes[0]);
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        assertSameValue(1,proc_close($p));assertSameValue('',$out);assertNotContainsValue('synthetic-do-not-leak',$err);
        $result=json_decode($err,true);assertTrueValue(is_array($result));
        assertSameValue(null,$result['database_error_number']);
        assertSameValue('input',$result['stage']);assertSameValue($expectedError,$result['error']);
    }
});

test('Effective import comparer still rejects structural metadata drift unexplained DDL and source change',static function():void {
    $a=effectiveSchemaFixture();
    $bad=[];$b=$a;$b['columns'][0]['COLUMN_DEFAULT']='different';$bad[]=$b;
    $b=$a;$b['indexes'][]=['INDEX_NAME'=>'new_index'];$bad[]=$b;
    $b=$a;$b['checks'][]=['CHECK_CLAUSE'=>'v IS NOT NULL'];$bad[]=$b;
    $b=$a;$b['ddl']['t']=str_replace('NOT NULL','NULL',$b['ddl']['t']);$bad[]=$b;
    $b=$a;$b['ddl']['t'].="\nPARTITION BY HASH (1)";$bad[]=$b;
    foreach($bad as $b){try{BackupApp\Import\MysqliImportDatabase::compare($a,$a,$b);throw new LogicException('schema difference accepted');}catch(RuntimeException){}}
    try{BackupApp\Import\MysqliImportDatabase::compare($a,$bad[0],$a);throw new LogicException('source drift accepted');}
    catch(RuntimeException $e){assertSameValue('import_source_changed_during_verification',$e->getMessage());}
});
