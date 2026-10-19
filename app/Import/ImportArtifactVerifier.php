<?php

declare(strict_types=1);

namespace BackupApp\Import;

use BackupApp\Backup\BackupArtifactPublisher;
use BackupApp\Backup\BackupService;
use BackupApp\Backup\MysqldumpCommand;
use BackupApp\Database\AuthorizedSourcePolicy;
use BackupApp\Restore\BackupProvenanceIndex;
use RuntimeException;
use Throwable;

final class ImportArtifactVerifier
{
    public function __construct(private readonly string $root,private readonly string $jobs){}

    /** Metadata-only choices; full file verification occurs at prepare/execute.
     * @return list<array<string,mixed>> */
    public function catalog():array
    {
        $result=[];
        foreach((new BackupService($this->root))->listBackupFiles() as $file){
            try{$result[]=$this->inspect($file['relative_path']);}catch(\DomainException|RuntimeException){}
        }
        return $result;
    }
    /** @return array<string,mixed> */
    public function inspect(string $relative):array
    {
        $parts=explode('/',str_replace('\\','/',$relative));
        if(count($parts)!==2||strlen($relative)>512||$relative!==implode('/',$parts)
            ||preg_match('/^[^\x00-\x1f\x7f\/\\\\:*?"<>|]+\.sql$/uD',$parts[1])!==1||str_contains($parts[1],'..')){throw new RuntimeException('import_artifact_path_invalid');}
        (new AuthorizedSourcePolicy())->assertAuthorized($parts[0]);
        $sql=(new BackupService($this->root))->resolveBackupPath($relative);
        $path=realpath($sql.'.json');$root=realpath($this->root);
        if($path===false||$root===false||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)
            ||!is_file($path)||filesize($path)>1048576){throw new RuntimeException('import_manifest_missing');}
        $m=json_decode((string)file_get_contents($path),true);
        if(!is_array($m)||($m['database']??'')!==$parts[0]||($m['relative_path']??'')!==$relative
            ||($m['filename']??'')!==$parts[1]||($m['complete']??null)!==true
            ||($m['scope']??'')!=='innodb_tables_structure_and_data'||($m['full_database_objects']??null)!==false
            ||($m['engine']['name']??'')!=='mysqldump'||($m['engine']['profile']??'')!==MysqldumpCommand::PROFILE
            ||($m['engine']['version']??'')!==MysqldumpCommand::VERSION
            ||($m['engine']['binary_sha256']??'')!==MysqldumpCommand::SHA256
            ||!$this->supportedOptions($m['engine']['options']??[])||($m['engine']['exit_code']??null)!==0
            ||!is_int($m['bytes']??null)||$m['bytes']<=0||$m['bytes']>17_179_869_184
            ||!is_string($m['sha256']??null)||preg_match('/^[a-f0-9]{64}$/D',$m['sha256'])!==1){
            throw new RuntimeException('import_unsupported_or_untrusted_dump');
        }
        MysqldumpCommand::assertServer(is_array($m['server']??null)?$m['server']:[]);
        $origin=(new BackupProvenanceIndex($this->jobs))->find($relative);
        if(!is_array($origin)||($origin['database']??'')!==$m['database']||($origin['bytes']??null)!==$m['bytes']
            ||!hash_equals((string)($origin['sha256']??''),$m['sha256'])||filesize($sql)!==$m['bytes']){
            throw new RuntimeException('import_artifact_origin_mismatch');
        }
        return ['database'=>$m['database'],'relative_path'=>$relative,'bytes'=>$m['bytes'],'sha256'=>$m['sha256'],'server_uuid'=>$m['server']['server_uuid']];
    }
    private function supportedOptions(array $options):bool
    {
        // Historical manifests included connection flags; producer profile and native binary/hash remain required.
        $profile=[];$seen=[];
        foreach($options as $option){
            if(!is_string($option)){return false;}
            if(preg_match('/^--(host|port|user)=(.+)$/D',$option,$m)===1){
                if(isset($seen[$m[1]])){return false;}$seen[$m[1]]=$m[2];continue;
            }$profile[]=$option;
        }
        if($seen!==[]){try{MysqldumpCommand::assertConnection(['host'=>$seen['host']??'','port'=>(int)($seen['port']??0),'username'=>$seen['user']??'']);}catch(RuntimeException){return false;}}
        return $profile===MysqldumpCommand::OPTIONS;
    }
    /** @return array<string,mixed> */
    public function verify(string $relative):array
    {
        $artifact=$this->inspect($relative);
        $sql=(new BackupService($this->root))->resolveBackupPath($relative);
        if(!(new BackupArtifactPublisher())->verifyPublished($sql,$sql.'.json')){throw new RuntimeException('import_artifact_integrity_failed');}
        return $artifact;
    }
    /** Immutable, create-only copy with streaming hash verification. @param array<string,mixed> $expected */
    public function copyVerified(array $expected,string $destination):void
    {
        $current=$this->inspect($expected['relative_path']);
        if($current!==$expected){throw new RuntimeException('import_artifact_changed');}
        $source=(new BackupService($this->root))->resolveBackupPath($expected['relative_path']);
        $in=@fopen($source,'rb');if($in===false){throw new RuntimeException('import_input_unavailable');}
        $out=@fopen($destination,'xb');if($out===false){fclose($in);throw new RuntimeException('import_staging_collision');}
        try{
            $hash=hash_init('sha256');$bytes=0;
            while(!feof($in)){
                $chunk=fread($in,65536);if($chunk===false){throw new RuntimeException('import_input_read_failed');}
                $bytes+=strlen($chunk);if($bytes>$expected['bytes']){throw new RuntimeException('import_input_size_changed');}
                hash_update($hash,$chunk);
                $offset=0;while($offset<strlen($chunk)){$n=fwrite($out,substr($chunk,$offset));if(!$n){throw new RuntimeException('import_staging_write_failed');}$offset+=$n;}
            }
            if($bytes!==$expected['bytes']||!hash_equals($expected['sha256'],hash_final($hash))||!fflush($out)){
                throw new RuntimeException('import_input_hash_changed');
            }
        }catch(Throwable $e){fclose($out);$out=null;@unlink($destination);throw $e;}
        finally{fclose($in);if(is_resource($out)){fclose($out);}}
    }
}
