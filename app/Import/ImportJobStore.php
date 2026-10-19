<?php

declare(strict_types=1);

namespace BackupApp\Import;

use BackupApp\Backup\MysqldumpCommand;
use BackupApp\Database\AuthorizedSourcePolicy;
use RuntimeException;

final class ImportJobStore
{
    private string $ownership;
    public function __construct(private readonly string $root,?string $ownership=null)
    {
        $this->ownership=$ownership??$root.'/ownership';
        foreach([$root,$this->ownership] as $path){if(!is_dir($path)&&!@mkdir($path,0750,true)){throw new RuntimeException('import_storage_unavailable');}}
    }
    public function path(string $id):string
    {
        if(preg_match('/^[a-f0-9]{32}$/D',$id)!==1){throw new RuntimeException('import_job_invalid');}
        return $this->root.DIRECTORY_SEPARATOR.$id.'.jsonl';
    }
    /** @param array<string,mixed> $artifact @return array<string,mixed> */
    public function create(array $artifact,?array $profile=null):array
    {
        (new AuthorizedSourcePolicy())->assertAuthorized((string)($artifact['database']??''));
        $names=ImportPolicy::names();
        if($profile!==null){MysqldumpCommand::assertConnection($profile);if(($artifact['server_uuid']??null)!==($profile['server_uuid']??null)){throw new RuntimeException('import_artifact_server_mismatch');}$names['username']=$profile['username'];}
        $job=$names+['status'=>'awaiting_provisioning','created_at'=>gmdate('c'),'started_at'=>null,'finished_at'=>null,
            'artifact'=>array_intersect_key($artifact,array_flip(['database','relative_path','sha256','bytes','server_uuid'])),
            'error'=>null,'events'=>[['at'=>gmdate('c'),'code'=>'artifact_verified_target_generated']],'verification'=>null];
        if($profile!==null){$job+=['mode'=>'saved_profile','host'=>$profile['host'],'port'=>$profile['port'],'server_uuid'=>$profile['server_uuid'],'account'=>$profile['account']];}
        $h=@fopen($this->path($job['id']),'xb');if($h===false){throw new RuntimeException('import_job_collision');}
        try{$this->append($h,$job);}finally{fclose($h);}return $job;
    }
    /** @return array<string,mixed> */
    public function read(string $id):array
    {
        $h=@fopen($this->path($id),'rb');if($h===false){throw new RuntimeException('import_job_missing');}
        try{if(!flock($h,LOCK_SH)){throw new RuntimeException('import_job_busy');}$job=$this->last($h);}finally{fclose($h);}
        if(($job['id']??null)!==$id){throw new RuntimeException('import_job_invalid');}ImportPolicy::assertJob($job);
        return $job;
    }
    /** @param array<string,mixed> $changes @return array<string,mixed> */
    public function update(string $id,array $changes):array
    {
        if(array_diff(array_keys($changes),['status','error','events','verification','started_at','finished_at'])!==[]){throw new RuntimeException('import_immutable_scope');}
        $h=@fopen($this->path($id),'r+b');if($h===false){throw new RuntimeException('import_job_missing');}
        try{
            if(!flock($h,LOCK_EX)){throw new RuntimeException('import_job_busy');}
            $job=$this->last($h);ImportPolicy::assertJob($job);
            $job=array_replace($job,$changes);fseek($h,0,SEEK_END);$this->append($h,$job);return $job;
        }finally{fclose($h);}
    }
    /** Per-run execution claim lock; separate from the shared DB-operation lock. @return resource */
    public function claim(string $id)
    {
        $path=$this->path($id).'.lock';$h=@fopen($path,'c+b');
        if($h===false||!flock($h,LOCK_EX|LOCK_NB)){if(is_resource($h)){fclose($h);}throw new RuntimeException('import_job_busy');}
        return $h;
    }
    public function ownershipPath(string $id):string{return $this->ownership.'/'.basename($this->path($id));}
    /** Observe orphaned PHP execution; do not retry, touch or remove its target. */
    public function recover(string $id):array
    {
        $job=$this->read($id);if($job['status']!=='running'){return $job;}
        try{$claim=$this->claim($id);}catch(RuntimeException){return $job;}
        try{
            $job=$this->read($id);
            return $job['status']==='running'
                ?$this->update($id,['status'=>'interrupted','error'=>'import_process_interrupted_reconcile_target','finished_at'=>gmdate('c')])
                :$job;
        }finally{flock($claim,LOCK_UN);fclose($claim);}
    }
    /** Called by authorized external provisioning only. Never from HTTP. @param array<string,mixed> $record */
    public function recordOwnership(string $id,array $record):void
    {
        $job=$this->read($id);$this->assertOwnership($job,$record);
        $h=@fopen($this->ownershipPath($id),'xb');if($h===false){throw new RuntimeException('import_ownership_collision');}
        try{$this->append($h,$record);}finally{fclose($h);}
    }
    /** @param array<string,mixed> $job */
    public function requireOwnership(array $job):void
    {
        $file=$this->ownershipPath($job['id']);
        if(!is_file($file)||filesize($file)>16384){throw new RuntimeException('import_provisioning_required');}
        $record=json_decode((string)file_get_contents($file),true);
        if(!is_array($record)){throw new RuntimeException('import_ownership_invalid');}
        $this->assertOwnership($job,$record);
    }
    private function assertOwnership(array $job,array $record):void
    {
        foreach(['id','target','username'] as $key){if(($record[$key]??null)!==$job[$key]){throw new RuntimeException('import_ownership_mismatch');}}
        $profile=($job['mode']??'')==='saved_profile';
        if(($record['server_uuid']??'')!==($profile?$job['server_uuid']:MysqldumpCommand::SERVER_UUID)||($record['created_new']??null)!==true
            ||(!$profile&&($record['account_created_new']??null)!==true)||($profile&&($record['account_created_new']??null)!==false)||!is_string($record['created_at']??null)){
            throw new RuntimeException('import_ownership_unproven');
        }
    }
    private function last($h):array
    {
        rewind($h);$last=null;
        while(($line=fgets($h,1048577))!==false){
            if(strlen($line)>=1048576){throw new RuntimeException('import_job_oversized');}
            $decoded=json_decode($line,true);if(is_array($decoded)){$last=$decoded;}
        }
        if(!is_array($last)){throw new RuntimeException('import_job_corrupt');}return $last;
    }
    private function append($h,array $value):void
    {
        $line=json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
        if(strlen($line)>=1048576||fwrite($h,$line)!==strlen($line)||!fflush($h)){throw new RuntimeException('import_job_write_failed');}
    }
}
