<?php
declare(strict_types=1);
namespace BackupApp\Import;

/** Presentation only: never executes SQL, changes job state, or grants execution eligibility. */
final class ImportResultPresentation
{
    public static function forJob(array $job,?array $review=null):array
    {
        $status=$job['status']??'';$v=is_array($job['verification']??null)?$job['verification']:[];
        $execution=match($status){'running'=>'running','succeeded'=>'completed','failed','interrupted'=>'failed',default=>'not_started'};
        // These comparison errors occur only after the native client has completed successfully.
        if($status==='failed'&&in_array($job['error']??'',[
            'import_target_schema_or_rows_mismatch','import_source_changed_during_verification'],true)){
            $execution='completed';
        }
        $schema=($v['schema_and_row_counts_match']??null)===true?'verified':'not_verified';$live='not_verified';$at=null;
        if($review!==null&&self::bound($review,$job)){
            $at=$review['accepted_at'];
            $data=is_array($review['data']??null)?$review['data']:[];$ddl=is_array($review['ddl']??null)?$review['ddl']:[];
            $schemaComplete=$ddl!==[]&&($review['effective_metadata_equal']??null)===true;
            foreach($ddl as $definition){if(!is_array($definition)||($definition['equivalent']??null)!==true){$schemaComplete=false;break;}}
            if($schemaComplete){$schema='verified';}
            $complete=$data!==[]&&array_keys($data)===array_keys($ddl)&&($review['current_source_target_equivalence']??'')==='PASS';
            foreach($data as $name=>$table){
                if(!is_array($table)||($table['equal']??null)!==true||($ddl[$name]['equivalent']??null)!==true
                    ||!is_int($table['rows']??null)||$table['rows']<0||$table['rows']!==($table['target_rows']??null)
                    ||($table['method']??'')!=='exact_sorted_lossless_row_bytes_with_multiplicity'
                    ||preg_match('/^[a-f0-9]{64}$/D',(string)($table['source_sha256']??''))!==1
                    ||($table['source_sha256']??null)!==($table['target_sha256']??null)){$complete=false;break;}
            }
            if($complete&&$schema==='verified'){$live='verified';}
        }
        // Current workflow has no independently verified native SQL-snapshot value comparison.
        // Even a current-source PASS or an arbitrary evidence flag must not upgrade this claim.
        $overall=in_array($status,['awaiting_provisioning','running'],true)?'pending':
            (($schema==='verified'||$live==='verified'||$status==='succeeded')?'partial':'failed');
        return ['recorded_status'=>$status,'execution'=>$execution,'schema'=>$schema,'live_data'=>$live,
            'snapshot_data'=>'not_verified','verification'=>$overall,'reviewed_at'=>$at,
            'message'=>self::message((string)($job['error']??''))];
    }

    public static function loadReview(string $directory,array $job):?array
    {
        $id=$job['id']??'';if(!is_string($id)||preg_match('/^[a-f0-9]{32}$/D',$id)!==1){return null;}
        $root=realpath($directory);if($root===false){return null;}
        $paths=glob($root.DIRECTORY_SEPARATOR.$id.'.acceptance-*.json')?:[];
        if(count($paths)>100){return null;}
        usort($paths,static fn(string $a,string $b):int=>strcmp(basename($b),basename($a)));
        foreach($paths as $path){
            if(is_link($path)||!is_file($path)||filesize($path)>1048576||dirname((string)realpath($path))!==$root){continue;}
            $text=@file_get_contents($path,false,null,0,1048577);if($text===false||strlen($text)>1048576){continue;}$review=json_decode($text,true,32);
            if(is_array($review)&&self::bound($review,$job)){return $review;}
        }
        return null;
    }

    private static function bound(array $review,array $job):bool
    {
        if(($review['job_id']??null)!==($job['id']??null)||($review['target']??null)!==($job['target']??null)
            ||($review['source']??null)!==($job['artifact']['database']??null)||!is_string($review['accepted_at']??null)
            ||strtotime($review['accepted_at'])===false){return false;}
        foreach(['database','relative_path','bytes','sha256'] as $key){
            if(!isset($job['artifact'][$key])||($review['artifact'][$key]??null)!==$job['artifact'][$key]){return false;}
        }
        return true;
    }

    public static function message(string $code):string
    {
        return match($code){
            ''=>'',
            'import_credential_required'=>'กรอกรหัสผ่านบัญชี Import เฉพาะงานนี้ หากไม่มี ให้ติดต่อผู้ดูแล ระบบไม่รีเซ็ตรหัสผ่านอัตโนมัติ',
            'import_connect_export_reader_first','connection_settings_missing'=>'ไปที่ Settings เพื่อบันทึกและทดสอบโปรไฟล์ MySQL ก่อน แล้วกลับมายืนยัน Import',
            'import_legacy_job_read_only'=>'งานเก่าเก็บเป็นประวัติ ไม่เริ่มซ้ำด้วยโปรไฟล์ใหม่',
            'import_insufficient_target_privileges','import_privilege_semantics_unsupported'=>'สิทธิ์ของบัญชีไม่เพียงพอหรือยังพิสูจน์ไม่ได้สำหรับฐานใหม่ ระบบไม่เพิ่ม GRANT อัตโนมัติ',
            'import_target_already_exists'=>'ชื่อฐานเป้าหมายมีอยู่แล้ว หยุดโดยไม่เขียนทับหรือ Import ซ้ำ',
            'import_connection_settings_changed'=>'Settings เปลี่ยนหลังเตรียมงาน งานเดิมเริ่มต่อไม่ได้ ให้ผู้ดูแลตรวจ Ownership/งานเดิมก่อนเตรียมงานใหม่ ห้าม Import ซ้ำลงฐานเดิม',
            'import_provisioning_required'=>'ยังไม่มีบัญชีและฐานใหม่ที่ยืนยัน Ownership ให้ผู้ดูแลเตรียมเฉพาะชื่อตามงานก่อน ไม่ต้องกดสร้างงานซ้ำ',
            'import_manifest_missing','import_unsupported_or_untrusted_dump','import_artifact_origin_mismatch',
            'import_artifact_integrity_failed','import_artifact_changed','import_input_hash_changed','import_input_size_changed'
                =>'ไฟล์หรือหลักฐาน Backup ไม่ตรงกัน ให้เลือกไฟล์ที่ระบบรองรับและตรวจใหม่ก่อนเริ่ม ห้ามแก้ SQL เพื่อข้ามการตรวจ',
            'import_explicit_confirmation_required'=>'ตรวจชื่อฐานใหม่ แล้วพิมพ์ IMPORT เพื่อยืนยันว่าจะไม่เขียนทับฐานเดิม',
            'import_already_attempted_no_retry'=>'งานนี้เริ่มแล้ว จึงไม่อนุญาตให้ Import ซ้ำ เก็บฐานเดิมไว้ให้ผู้ดูแลตรวจสอบ',
            'import_target_schema_or_rows_mismatch'=>'ผลตรวจเดิมพบโครงสร้างหรือจำนวนแถวไม่ตรงกัน ดูผลตรวจเพิ่มเติมแยกด้านล่าง ประวัติงานเดิมจะไม่ถูกแก้ย้อนหลัง',
            'import_source_changed_during_verification'=>'ฐานต้นทางเปลี่ยนระหว่างตรวจ จึงยังสรุปผลไม่ได้ เก็บฐานเป้าหมายไว้โดยไม่ลอง Import ซ้ำ',
            'import_process_interrupted_reconcile_target'=>'งานถูกขัดจังหวะ ให้ผู้ดูแลตรวจฐานเป้าหมายเดิมก่อน ไม่ลอง Import ซ้ำอัตโนมัติ',
            'import_database_operation_busy','import_job_busy'=>'มีงานอื่นกำลังใช้ระบบอยู่ กรุณารอแล้วตรวจสถานะอีกครั้ง',
            'import_job_not_owned','import_job_missing'=>'ไม่พบงานใน Session นี้ กลับหน้า Import เพื่อดูงานของ Session ปัจจุบัน',
            'csrf_invalid'=>'Session หรือแบบฟอร์มหมดอายุ กรุณาโหลดหน้าใหม่ก่อนทำรายการ',
            'loopback_only'=>'ใช้งานได้เฉพาะ localhost ของเครื่องนี้',
            default=>'ดำเนินการไม่สำเร็จ ตรวจบัญชีเฉพาะงาน รหัสผ่าน และไฟล์ที่เลือก หากยังมีปัญหาให้ผู้ดูแลตรวจสอบ ระบบไม่รีเซ็ตรหัสหรือ Import ซ้ำอัตโนมัติ',
        };
    }
}
