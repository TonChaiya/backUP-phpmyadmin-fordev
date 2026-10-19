<?php
declare(strict_types=1);
use BackupApp\Support\View;
use BackupApp\Http\Router;

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
