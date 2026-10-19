<?php
declare(strict_types=1);

// Refactoring guard: broken import declarations or removed runtime dependencies must fail.
test('Cleanup retained application declarations and explicit dependencies autoload without execution', static function():void {
    $root=dirname(__DIR__);$files=[];
    foreach(['app','public','views','tools'] as $folder){
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder,FilesystemIterator::SKIP_DOTS));
        foreach($iterator as $file){
            assertFalseValue($file->isLink(),'Unexpected source link');
            if($file->isFile()&&$file->getExtension()==='php'){$files[]=$file->getPathname();}
        }
    }
    $symbols=[];$references=[];
    foreach($files as $file){
        $text=(string)file_get_contents($file);
        if(preg_match('/namespace\s+(BackupApp\\\\[A-Za-z0-9_\\\\]+);/',$text,$ns)
            &&preg_match('/^(?:final |abstract )?(?:class|interface|enum)\s+(\w+)/m',$text,$name)){
            $symbols[]=$ns[1].'\\'.$name[1];
        }
        preg_match_all('/^use\s+(BackupApp\\\\[A-Za-z0-9_\\\\]+);/m',$text,$imports);
        foreach($imports[1] as $class){$references[$class]=$file;}
        foreach(token_get_all($text) as $token){
            if(is_array($token)&&$token[0]===T_NAME_FULLY_QUALIFIED&&str_starts_with($token[1],'\\BackupApp\\')){
                $references[ltrim($token[1],'\\')]=$file;
            }
        }
    }
    foreach(array_unique(array_merge($symbols,array_keys($references))) as $class){
        assertTrueValue(class_exists($class)||interface_exists($class)||enum_exists($class),'Missing runtime dependency '.$class);
    }
    assertTrueValue(count($symbols)>35,'Unexpectedly incomplete application inventory');
    foreach(['app/Backup','app/Import','app/Database','app/Http','app/Support','app/Restore'] as $folder){assertTrueValue(is_dir($root.'/'.$folder));}
    assertSameValue(['BackupProvenanceIndex.php','RestoreArtifactValidator.php'],array_map('basename',glob($root.'/app/Restore/*.php')));
    assertFalseValue(is_dir($root.'/app/Restore/Integration'),'Inactive integration tree retained');
});

test('Cleanup active entrypoints render isolated routes without MySQL or project runtime writes', static function():void {
    $root=dirname(__DIR__);$fixture=phase2TempDirectory('cleanup-routes');
    try{
        foreach(['app','public','views'] as $folder){
            $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder,FilesystemIterator::SKIP_DOTS));
            foreach($iterator as $file){
                if(!$file->isFile()){continue;}
                assertFalseValue($file->isLink());
                $relative=substr($file->getPathname(),strlen($root)+1);$target=$fixture.'/'.$relative;
                if(!is_dir(dirname($target))){mkdir(dirname($target),0700,true);}copy($file->getPathname(),$target);
            }
        }
        mkdir($fixture.'/config');mkdir($fixture.'/storage');copy($root.'/config/app.php',$fixture.'/config/app.php');
        $cases=[['index.php','dashboard',200,'Dashboard'],['index.php','backup',200,'data-backup-jobs'],
            ['index.php','import',200,'data-local-import'],['index.php','history',200,'History'],
            ['index.php','settings',200,'Settings'],['index.php','unknown',404,'404'],
            ['job.php','',405,'ไม่รองรับคำขอนี้'],['import-job.php','',419,'csrf_invalid'],['download.php','',404,'Backup file is not available.']];
        foreach($cases as [$entry,$page,$expected,$marker]){
            // Disabling mysqli_init makes any accidental DB factory call fail, never connect.
            $script='$_SERVER["REQUEST_METHOD"]="GET";$_SERVER["REMOTE_ADDR"]="127.0.0.1";$_SERVER["HTTP_HOST"]="localhost";$_GET["page"]='.var_export($page,true).';'
                .'register_shutdown_function(static function(){fwrite(STDERR,"STATUS:".http_response_code());});require '.var_export($fixture.'/public/'.$entry,true).';';
            $process=proc_open([PHP_BINARY,'-d','disable_functions=mysqli_init','-r',$script],[1=>['pipe','w'],2=>['pipe','w']],$pipes,$fixture);
            assertTrueValue(is_resource($process));$html=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
            assertSameValue(0,proc_close($process),$entry.' child failed');
            assertSameValue('STATUS:'.$expected,$errors,$entry.' unexpected diagnostic/status');assertContainsValue($marker,$html);
            if($entry==='index.php'&&$expected===200){assertSameValue(1,substr_count($html,'<h1'));assertNotContainsValue('page=restore',$html);}
        }
    }finally{phase2RemoveDirectory($fixture);}
});

test('Cleanup retained History inspector preserves legacy metadata without promoting SQL integrity', static function():void {
    $root=phase2TempDirectory('cleanup-history');
    try{
        [$relative,$manifest]=localImportFixture($root);
        $manifest['created_at']='2026-10-17T00:00:00+07:00';
        $manifest['restore_order']=BackupApp\Restore\RestoreArtifactValidator::RESTORE_ORDER;
        $manifest['stats']=array_fill_keys(['tables','views','triggers','procedures','functions','events','foreign_keys',
            'auto_increment_tables','myisam_lock_sections','rows'],0);
        $sql=$root.'/backups/'.$relative;file_put_contents($sql.'.json',json_encode($manifest));
        $inspector=new BackupApp\Restore\RestoreArtifactValidator($root.'/backups',new BackupApp\Restore\BackupProvenanceIndex($root.'/jobs'));
        $result=$inspector->inspect($relative);
        assertSameValue('candidate',$result['status']);assertFalseValue($result['integrity_verified']);assertFalseValue($result['eligible']);
        $contents=file_get_contents($sql);file_put_contents($sql,str_repeat('x',strlen($contents)));
        assertSameValue('candidate',$inspector->inspect($relative)['status'],'History must not hash SQL or imply content integrity');
        mkdir($root.'/empty-jobs');
        $unverified=new BackupApp\Restore\RestoreArtifactValidator($root.'/backups',new BackupApp\Restore\BackupProvenanceIndex($root.'/empty-jobs'));
        assertSameValue('unverified_origin',$unverified->inspect($relative)['status']);
        $manifest['bytes']=(string)$manifest['bytes'];file_put_contents($sql.'.json',json_encode($manifest));
        assertSameValue('invalid_metadata',$inspector->inspect($relative)['status']);
    }finally{phase2RemoveDirectory($root);}
});
