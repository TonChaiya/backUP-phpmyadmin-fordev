<?php
declare(strict_types=1);
namespace BackupApp\Import;
use RuntimeException;
final class ImportAccess
{
    public static function register(string $id,?string $revision=null):void
    {
        $ids=is_array($_SESSION['local_import_ids']??null)?$_SESSION['local_import_ids']:[];
        $ids[]=$id;$_SESSION['local_import_ids']=array_slice(array_values(array_unique($ids)),-20);
        $_SESSION['local_import_active']=$id;
        $_SESSION['local_import_connection_revisions'][$id]=$revision;
    }
    public static function requireOwned(string $id):void
    {
        if(preg_match('/^[a-f0-9]{32}$/D',$id)!==1||!in_array($id,$_SESSION['local_import_ids']??[],true)){
            throw new RuntimeException('import_job_not_owned');
        }
    }
    public static function requireConnectionRevision(string $id,?string $revision):void
    {
        self::requireOwned($id);
        if($revision===null||($_SESSION['local_import_connection_revisions'][$id]??null)!==$revision){
            throw new RuntimeException('import_connection_settings_changed');
        }
    }
}
