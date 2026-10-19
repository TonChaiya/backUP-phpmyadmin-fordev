<?php
declare(strict_types=1);

function acceptanceHelpers(): void {
    $path=dirname(__DIR__).'/tools/verification/LocalImportAcceptance.php';
    if (is_file($path)) { require_once $path; }
}

test('Read-only acceptance partition query executes against MySQL 8.4 documented column fixture',static function():void {
    acceptanceHelpers();
    $db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec("ATTACH DATABASE ':memory:' AS information_schema");
    $db->exec('CREATE TABLE information_schema.PARTITIONS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, PARTITION_NAME TEXT, SUBPARTITION_NAME TEXT, PARTITION_ORDINAL_POSITION INTEGER, SUBPARTITION_ORDINAL_POSITION INTEGER, PARTITION_METHOD TEXT, SUBPARTITION_METHOD TEXT, PARTITION_EXPRESSION TEXT, SUBPARTITION_EXPRESSION TEXT, PARTITION_DESCRIPTION TEXT, TABLESPACE_NAME TEXT, PARTITION_COMMENT TEXT)');
    $db->exec("INSERT INTO information_schema.PARTITIONS (TABLE_SCHEMA, TABLE_NAME) VALUES ('autoprintdb','t')");
    $result=$db->query(LocalImportAcceptance::partitionQuery('autoprintdb'))->fetchAll(PDO::FETCH_ASSOC);
    assertSameValue(1,count($result));assertSameValue('t',$result[0]['TABLE_NAME']);
    assertSameValue([], $db->query(LocalImportAcceptance::partitionQuery('local_import_20261018_b2197f1be82de698'))->fetchAll(PDO::FETCH_ASSOC));
    try{LocalImportAcceptance::partitionQuery('not_authorized');throw new LogicException('schema scope accepted');}catch(RuntimeException $e){assertSameValue('acceptance_schema_scope_invalid',$e->getMessage());}
});

test('Read-only acceptance explains only metadata-proven explicit charset DDL differences', static function():void {
    acceptanceHelpers();
    $columns=[['COLUMN_NAME'=>'v','COLUMN_TYPE'=>'varchar(255)','CHARACTER_SET_NAME'=>'utf8mb4','COLLATION_NAME'=>'utf8mb4_unicode_ci']];
    $a="CREATE TABLE `t` (\n  `v` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $b="CREATE TABLE `t` (\n  `v` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $result=LocalImportAcceptance::ddlEvidence($a,$b,$columns,$columns);
    assertSameValue('equivalent_explicit_charset',$result['differences'][0]['reason']);
    assertSameValue('v',$result['differences'][0]['column']);
    foreach ([str_replace('NOT NULL','NULL',$b),str_replace('varchar(255)','varchar(256)',$b),$b."\nPARTITION BY HASH (1)"] as $bad) {
        try { LocalImportAcceptance::ddlEvidence($a,$bad,$columns,$columns); throw new LogicException('unexplained DDL accepted'); }
        catch(RuntimeException $e){assertSameValue('acceptance_unexplained_ddl',$e->getMessage());}
    }
    $wrong=$columns; $wrong[0]['COLLATION_NAME']='utf8mb4_0900_ai_ci';
    try {LocalImportAcceptance::ddlEvidence($a,$b,$columns,$wrong);throw new LogicException('unequal metadata accepted');}
    catch(RuntimeException $e){assertSameValue('acceptance_column_metadata_mismatch',$e->getMessage());}
});

test('Read-only acceptance compares exact row multisets preserving null bytes precision and duplicates', static function():void {
    acceptanceHelpers();
    $rows=[[null,'',"\x00\xff","ภาษาไทย\n'\\",'99999999999999999999.123456789','2026-10-19 01:02:03.123456'],['','','','','0','0000-00-00'],['','','','','0','0000-00-00']];
    $result=LocalImportAcceptance::rows($rows,array_reverse($rows));
    assertTrueValue($result['equal']); assertSameValue(3,$result['rows']);
    foreach ([array_slice($rows,0,2),[['','',"\x00\xff","ภาษาไทย\n'\\",'99999999999999999999.123456789','2026-10-19 01:02:03.123456'],$rows[1],$rows[2]],[$rows[0],$rows[1],['','','','','1','0000-00-00']]] as $bad) {
        assertFalseValue(LocalImportAcceptance::rows($rows,$bad)['equal']);
    }
});

test('Read-only acceptance refuses unsupported value conversions and bounded-memory overflow', static function():void {
    acceptanceHelpers();
    foreach(['float','double','json','geometry'] as $type){
        try {LocalImportAcceptance::assertDataType($type);throw new LogicException('unsupported type accepted');}
        catch(RuntimeException $e){assertSameValue('acceptance_unsupported_data_type',$e->getMessage());}
    }
    foreach(['int','bigint','decimal','binary','blob','varchar','timestamp','datetime','date','time','bit'] as $type){LocalImportAcceptance::assertDataType($type);}
    try {LocalImportAcceptance::rows([[str_repeat('x',200)]],[],100,100);throw new LogicException('byte bound ignored');}
    catch(RuntimeException $e){assertSameValue('acceptance_memory_bound',$e->getMessage());}
    try {LocalImportAcceptance::rows([['x'],['y']],[],1000,1);throw new LogicException('row bound ignored');}
    catch(RuntimeException $e){assertSameValue('acceptance_memory_bound',$e->getMessage());}
});

test('Read-only acceptance enum set comparison retains both text and exact ordinal without null ambiguity',static function():void {
    acceptanceHelpers();
    assertSameValue(['HEX(CAST(`state` AS BINARY))','HEX(CAST(CAST(`state` AS UNSIGNED) AS BINARY))'],LocalImportAcceptance::valueExpressions('state','enum'));
    assertSameValue(['HEX(CAST(`a``b` AS BINARY))','HEX(CAST(CAST(`a``b` AS UNSIGNED) AS BINARY))'],LocalImportAcceptance::valueExpressions('a`b','set'));
    assertSameValue(['HEX(CAST(`v` AS BINARY))'],LocalImportAcceptance::valueExpressions('v','decimal'));
    assertFalseValue(LocalImportAcceptance::rows([['','0']],[['','1']])['equal']);
    assertFalseValue(LocalImportAcceptance::rows([[null,null]],[['','0']])['equal']);
    assertFalseValue(LocalImportAcceptance::rows([['a,b','18446744073709551615']],[['a,b','18446744073709551614']])['equal']);
});
