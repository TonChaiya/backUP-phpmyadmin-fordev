<?php

declare(strict_types=1);

namespace BackupApp\Import;

use BackupApp\Database\DatabaseOperationLock;
use RuntimeException;
use Throwable;

final class ImportRunner
{
    public function __construct(private readonly ImportJobStore $store,private readonly DatabaseOperationLock $lock,
        private readonly ImportArtifactVerifier $artifacts){}
    /** @return array<string,mixed> */
    public function run(string $id,ImportDatabase $database,ImportClient $client,string $password):array
    {
        $claim=$this->store->claim($id);$global=null;$started=false;
        try{
            $job=$this->store->read($id);
            if($job['status']!=='awaiting_provisioning'){throw new RuntimeException('import_already_attempted_no_retry');}
            $this->store->requireOwnership($job);
            $global=$this->lock->acquire('import-'.$id,false);
            if($global===null){throw new RuntimeException('import_database_operation_busy');}
            $artifact=$this->artifacts->verify($job['artifact']['relative_path']);
            if($artifact!==$job['artifact']){throw new RuntimeException('import_artifact_changed');}
            $started=true;$job=$this->store->update($id,['status'=>'running','started_at'=>gmdate('c')]);
            $database->start($job);$before=$database->sourceSnapshot($artifact['database']);
            $result=$client->execute($job,$this->artifacts,$password);
            if(($result['exit_code']??-1)!==0){throw new RuntimeException('mysql_import_nonzero_exit');}
            $target=$database->targetSnapshot($job['target']);$after=$database->sourceSnapshot($artifact['database']);
            $verification=MysqliImportDatabase::compare($before,$after,$target);
            $events=$job['events'];$events[]=['at'=>gmdate('c'),'code'=>'mysql_exit_zero_and_comparison_passed'];
            return $this->store->update($id,['status'=>'succeeded','finished_at'=>gmdate('c'),'verification'=>$verification+['client'=>$result], 'events'=>$events]);
        }catch(Throwable $e){
            if(!$started){throw $e;}
            $code=$e->getMessage();
            if(preg_match('/^(?:import|mysql_import|mysqldump)_[a-z0-9_]+$/D',$code)!==1){$code='import_operation_failed';}
            return $this->store->update($id,['status'=>'failed','error'=>$code,'finished_at'=>gmdate('c')]);
        }finally{
            try { $database->close(); }
            finally {
                if($global!==null){$this->lock->release($global);}
                flock($claim,LOCK_UN);fclose($claim);
            }
        }
    }
}
