<?php
declare(strict_types=1);
use BackupApp\Support\View;
use BackupApp\Http\Router;

// Keep routing/rendering real; replace only the external connection boundary.
function importRouteFixture(bool $connected): string
{
    $root=dirname(__DIR__);$fixture=phase2TempDirectory('import-route');
    try {
        foreach(['app','public','views'] as $folder){
            $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder,FilesystemIterator::SKIP_DOTS));
            foreach($iterator as $file){
                assertFalseValue($file->isLink());if(!$file->isFile()){continue;}
                $target=$fixture.'/'.substr($file->getPathname(),strlen($root)+1);
                if(!is_dir(dirname($target))){mkdir(dirname($target),0700,true);}copy($file->getPathname(),$target);
            }
        }
        mkdir($fixture.'/config');mkdir($fixture.'/storage');copy($root.'/config/app.php',$fixture.'/config/app.php');
        localImportFixture($fixture.'/storage');
        $script='namespace BackupApp\\Http\\Controller { final class BackupController {
            public static function forApplication(array $config,string $root):self{return new self();}
            public function metadata():array{return [];}
            public function verify():array{return ["connected"=>'.var_export($connected,true).',"databases"=>[],"server"=>[],"error"=>"","checked_at"=>null];}
        }} namespace { $_SERVER["REQUEST_METHOD"]="GET";$_SERVER["REMOTE_ADDR"]="127.0.0.1";
            $_SERVER["HTTP_HOST"]="localhost";$_GET["page"]="import";
            register_shutdown_function(static function(){fwrite(STDERR,"STATUS:".http_response_code());});
            require '.var_export($fixture.'/public/index.php',true).'; }';
        $process=proc_open([PHP_BINARY,'-d','disable_functions=mysqli_init','-r',$script],
            [1=>['pipe','w'],2=>['pipe','w']],$pipes,$fixture);
        assertTrueValue(is_resource($process));$html=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);assertSameValue(0,proc_close($process));assertSameValue('STATUS:200',$errors);
        return $html;
    } finally { phase2RemoveDirectory($fixture); }
}

test('Import route enables preparation for a connected profile with a trusted artifact',static function():void{
    $html=importRouteFixture(true);
    assertContainsValue('autoprintdb/fixture.sql',$html);
    assertSameValue(1,preg_match('/<button class="primary-action" type="submit">/',$html));
    assertNotContainsValue('Disconnected',$html);
    assertContainsValue('data-import-snapshot-data>NOT VERIFIED',$html);
    assertContainsValue('data-import-execute action="import-job.php" method="post" class="section-form" hidden',$html);
});

test('Import route disables preparation for a disconnected profile despite a trusted artifact',static function():void{
    $html=importRouteFixture(false);
    assertContainsValue('autoprintdb/fixture.sql',$html);
    assertContainsValue('<button class="primary-action" type="submit" disabled>',$html);
    assertContainsValue('Disconnected',$html);
    assertContainsValue('data-import-execute action="import-job.php" method="post" class="section-form" hidden',$html);
});

test('Local import UI preserves design and shows generated target and explicit confirmation only',static function():void{
    $html=View::render('import',['csrfToken'=>'token','pageTitle'=>'Import','heading'=>'Import',
        'importArtifacts'=>[['database'=>'autoprintdb','relative_path'=>'autoprintdb/example.sql','bytes'=>100]],
        'activeImportJobId'=>'','pageScript'=>'assets/local-import.js'],'import',['app_name'=>'Local manager']);
    foreach(['Dashboard','Export','Import','History','Settings','data-local-import','name="confirmation"','data-import-prepare','data-import-execute','mysql.exe'] as $needle){assertContainsValue($needle,$html);}
    assertNotContainsValue('name="import_password"',$html);
    assertNotContainsValue('name="target"',$html);assertNotContainsValue('name="username"',$html);
    assertNotContainsValue('Restore Lab',$html);assertNotContainsValue('Dedicated Apache',$html);
    assertSameValue(1,substr_count($html,'<h1'));
    assertContainsValue('src="assets/local-import.js"',$html);
    assertSameValue('import',Router::resolve('import')['name']);
    assertSameValue(404,Router::resolve('restore')['status']);
    assertSameValue(404,Router::resolve('restore-integration')['status']);
});

test('Local import endpoint retains explicit access CSRF ownership and no browser scope override',static function():void{
    $file=(string)file_get_contents(dirname(__DIR__).'/public/import-job.php');
    foreach(['isAuthorized','validateCsrf','requireOwned','recordOwnership',"!=='IMPORT'",'import_browser_scope_override_rejected'] as $term){
        assertContainsValue($term,$file);
    }
    assertNotContainsValue('CREATE USER',$file);assertNotContainsValue('CREATE DATABASE',$file);assertNotContainsValue("'root'",$file);
    $js=(string)file_get_contents(dirname(__DIR__).'/public/assets/local-import.js');
    assertContainsValue('textContent',$js);assertNotContainsValue('innerHTML',$js);assertNotContainsValue('percent',$js);
});
