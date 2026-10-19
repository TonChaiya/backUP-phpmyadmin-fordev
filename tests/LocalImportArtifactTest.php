<?php

declare(strict_types=1);

use BackupApp\Import\ImportArtifactVerifier;
use BackupApp\Backup\MysqldumpCommand;

function localImportFixture(string $root):array
{
    mkdir($root.'/backups');mkdir($root.'/backups/autoprintdb');
    $relative='autoprintdb/fixture.sql';
    $sql="-- MySQL dump 10.13 Distrib 8.4.3\n-- Host: 127.0.0.1    Database: autoprintdb\nCREATE TABLE t(id int);\n-- Dump completed on 2026-10-19\n-- Backup completed successfully\n";
    file_put_contents($root.'/backups/'.$relative,$sql);
    $manifest=['database'=>'autoprintdb','filename'=>'fixture.sql','relative_path'=>$relative,'bytes'=>strlen($sql),'sha256'=>hash('sha256',$sql),'complete'=>true,'scope'=>'innodb_tables_structure_and_data','full_database_objects'=>false,
        'engine'=>['name'=>'mysqldump','version'=>'8.4.3','binary_sha256'=>MysqldumpCommand::SHA256,'profile'=>MysqldumpCommand::PROFILE,'options'=>MysqldumpCommand::OPTIONS,'exit_code'=>0],
        'server'=>['server_uuid'=>MysqldumpCommand::SERVER_UUID,'account'=>'backup_reader@localhost','version'=>'8.4.3','port'=>3306,'roles'=>'NONE','partial_revokes'=>'0']];
    file_put_contents($root.'/backups/'.$relative.'.json',json_encode($manifest));
    $store=new \BackupApp\Backup\BackupJobStore($root.'/jobs');$job=$store->create(['autoprintdb']);
    $store->update($job['id'],static function(array $state)use($manifest):array{
        $state['status']='succeeded';$state['databases'][0]['status']='succeeded';$state['databases'][0]['artifact']=$manifest;return $state;
    });
    return [$relative,$manifest];
}

test('Local import accepts exact native producer provenance and verifies complete bytes',static function():void{
    $root=phase2TempDirectory('import-artifact');
    try{
        [$relative,$manifest]=localImportFixture($root);
        $v=new ImportArtifactVerifier($root.'/backups',$root.'/jobs');
        $artifact=$v->verify($relative);
        assertSameValue($manifest['sha256'],$artifact['sha256']);
        assertSameValue('autoprintdb',$artifact['database']);
        $copy=$root.'/copy.sql';$v->copyVerified($artifact,$copy);
        assertSameValue(hash_file('sha256',$root.'/backups/'.$relative),hash_file('sha256',$copy));
        try{$v->copyVerified($artifact,$copy);throw new LogicException('overwrote staged input');}catch(RuntimeException){}
    }finally{phase2RemoveDirectory($root);}
});

test('Local import rejects unknown profiles source directives configuration and unproven origins',static function():void{
    $root=phase2TempDirectory('import-profile');
    try{
        [$relative,$manifest]=localImportFixture($root);$v=new ImportArtifactVerifier($root.'/backups',$root.'/jobs');
        foreach(['options','producer','source','origin'] as $case){
            $bad=$manifest;
            if($case==='options'){$bad['engine']['options'][]='--databases';}
            if($case==='producer'){$bad['engine']['binary_sha256']=str_repeat('0',64);}
            if($case==='source'){$bad['database']='jhcisdb';}
            if($case==='origin'){$bad['sha256']=str_repeat('b',64);}
            file_put_contents($root.'/backups/'.$relative.'.json',json_encode($bad));
            try{$v->verify($relative);throw new LogicException('unsafe artifact accepted');}catch(RuntimeException|DomainException){}
        }
    }finally{phase2RemoveDirectory($root);}
});

test('Local import rejects tamper truncation path traversal and copy time changes',static function():void{
    $root=phase2TempDirectory('import-tamper');
    try{
        [$relative,$manifest]=localImportFixture($root);$v=new ImportArtifactVerifier($root.'/backups',$root.'/jobs');
        $artifact=$v->verify($relative);
        file_put_contents($root.'/backups/'.$relative,str_repeat('x',$manifest['bytes']));
        foreach([$relative,'../foreign.sql','unknown/file.sql','autoprintdb/file.sql:alternate.sql','autoprintdb/file.json'] as $path){
            try{$v->verify($path);throw new LogicException('unsafe bytes accepted');}catch(RuntimeException|DomainException){}
        }
        try{$v->copyVerified($artifact,$root.'/copy.sql');throw new LogicException('changed input copied');}catch(RuntimeException){}
        assertFalseValue(is_file($root.'/copy.sql'));
    }finally{phase2RemoveDirectory($root);}
});
