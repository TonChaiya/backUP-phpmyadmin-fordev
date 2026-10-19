<?php
declare(strict_types=1);
namespace BackupApp\Import;
use RuntimeException;

/** Evidence for a narrowly supported SHOW CREATE rendering difference, never SQL rewriting. */
final class EffectiveSchemaComparison
{
    public static function ddlEvidence(string $source,string $target,array $sourceColumns,array $targetColumns):array
    {
        if($sourceColumns!==$targetColumns){throw new RuntimeException('acceptance_column_metadata_mismatch');}
        $a=explode("\n",$source);$b=explode("\n",$target);$evidence=[];
        if(count($a)!==count($b)){throw new RuntimeException('acceptance_unexplained_ddl');}
        foreach($a as $line=>$text){
            if($text===$b[$line]){continue;}
            $explanation=null;
            foreach($sourceColumns as $column){
                $prefix='  `'.str_replace('`','``',$column['COLUMN_NAME']).'` ';
                $charset=$column['CHARACTER_SET_NAME'];$collation=$column['COLLATION_NAME'];
                if(!is_string($charset)||!is_string($collation)||preg_match('/^[a-zA-Z0-9_]+$/D',$charset)!==1
                    ||preg_match('/^[a-zA-Z0-9_]+$/D',$collation)!==1||!str_starts_with($text,$prefix)||!str_starts_with($b[$line],$prefix)){continue;}
                foreach([[$text,$b[$line]],[$b[$line],$text]] as [$implicit,$explicit]){
                    if(str_contains($implicit,' CHARACTER SET ')){continue;}
                    $position=strpos($implicit,' COLLATE '.$collation);
                    if($position!==false&&$explicit===substr($implicit,0,$position).' CHARACTER SET '.$charset.substr($implicit,$position)){
                        $explanation=['line'=>$line+1,'column'=>$column['COLUMN_NAME'],'reason'=>'equivalent_explicit_charset',
                            'effective_charset'=>$charset,'effective_collation'=>$collation,
                            'source_line_sha256'=>hash('sha256',$text),'target_line_sha256'=>hash('sha256',$b[$line])];break 2;
                    }
                }
            }
            if($explanation===null){throw new RuntimeException('acceptance_unexplained_ddl');}
            $evidence[]=$explanation;
        }
        return ['equivalent'=>true,'source_sha256'=>hash('sha256',$source),'target_sha256'=>hash('sha256',$target),'differences'=>$evidence];
    }
}
