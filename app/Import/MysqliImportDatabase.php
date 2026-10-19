<?php

declare(strict_types=1);

namespace BackupApp\Import;

use BackupApp\Backup\MysqldumpCommand;
use BackupApp\Database\AuthorizedSourcePolicy;
use BackupApp\Database\ConnectionFactory;
use mysqli;
use RuntimeException;

/** Read-only metadata/count verification; SQL import is handled only by mysql.exe. */
final class MysqliImportDatabase implements ImportDatabase
{
    private ?object $source=null;
    private ?object $target=null;
    private ?string $targetName=null;
    private ?\Closure $connectionCheck;
    private ?\Closure $recordOwnership;
    private \Closure $connect;
    public function __construct(private readonly array $reader,private readonly string $password='',?callable $connectionCheck=null,?callable $recordOwnership=null,?callable $connect=null)
    {$this->connectionCheck=$connectionCheck!==null?\Closure::fromCallable($connectionCheck):null;$this->recordOwnership=$recordOwnership!==null?\Closure::fromCallable($recordOwnership):null;
        $this->connect=$connect!==null?\Closure::fromCallable($connect):(new ConnectionFactory(['connect_timeout_seconds'=>5]))->make(...);}
    public function start(array $job):void
    {
        if($this->connectionCheck!==null){($this->connectionCheck)();}
        ImportPolicy::assertJob($job);
        (new AuthorizedSourcePolicy())->assertAuthorized($job['artifact']['database']);
        MysqldumpCommand::assertConnection($this->reader);
        if(($job['mode']??'')==='saved_profile'){
            foreach(['host','port','username','server_uuid','account'] as $key){if(($job[$key]??null)!==($this->reader[$key]??null)){throw new RuntimeException('import_connection_settings_changed');}}
            if($this->recordOwnership===null){throw new RuntimeException('import_ownership_recorder_required');}
            $this->target=($this->connect)($this->reader['host'],$this->reader['port'],$this->reader['username'],$this->reader['password']);
            $identity=MysqldumpCommand::verifyConnection($this->target,$this->reader);
            if((string)$identity['partial_revokes']!=='0'){throw new RuntimeException('import_privilege_semantics_unsupported');}
            $grants=[];$r=$this->target->query('SHOW GRANTS FOR CURRENT_USER()');while($row=$r->fetch_row()){$grants[]=$row[0];}
            ImportPolicy::assertProfileGrants($grants,$job['target']);
            $escaped=$this->target->real_escape_string($job['target']);
            if($this->target->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '{$escaped}'")->fetch_row()!==null){throw new RuntimeException('import_target_already_exists');}
            // No IF NOT EXISTS: a concurrent external creator must cause failure, never reuse.
            $this->target->query('CREATE DATABASE `'.$job['target'].'` CHARACTER SET utf8mb4');
            ($this->recordOwnership)($job['id'],['id'=>$job['id'],'target'=>$job['target'],'username'=>$job['username'],'server_uuid'=>$identity['server_uuid'],'created_new'=>true,'account_created_new'=>false,'created_at'=>gmdate('c')]);
            $this->targetName=$job['target'];
            $this->source=($this->connect)($this->reader['host'],$this->reader['port'],$this->reader['username'],$this->reader['password']);
            MysqldumpCommand::verifyConnection($this->source,$this->reader);return;
        }
        $this->target=($this->connect)('127.0.0.1',3306,$job['username'],$this->password);
        ImportPolicy::verifyConnection($this->target,$job);
        $this->targetName=$job['target'];$escaped=$this->target->real_escape_string($this->targetName);
        $schemas=$this->target->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '{$escaped}'");
        if($schemas->num_rows!==1){throw new RuntimeException('import_owned_target_missing');}
        $tables=$this->target->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$escaped}'");
        if($tables->num_rows!==0){throw new RuntimeException('import_target_not_empty');}
        $this->source=($this->connect)($this->reader['host'],$this->reader['port'],$this->reader['username'],(string)$this->reader['password']);
        MysqldumpCommand::verifyConnection($this->source,$this->reader);
    }
    public function sourceSnapshot(string $source):array
    {
        (new AuthorizedSourcePolicy())->assertAuthorized($source);
        if($this->source===null){throw new RuntimeException('import_reader_not_connected');}
        return $this->snapshot($this->source,$source);
    }
    public function targetSnapshot(string $target):array
    {
        if($this->target===null||$this->targetName!==$target){throw new RuntimeException('import_target_scope_mismatch');}
        return $this->snapshot($this->target,$target);
    }
    private function snapshot(mysqli $db,string $schema):array
    {
        $escaped=$db->real_escape_string($schema);
        $result=$db->query("SELECT TABLE_NAME, TABLE_TYPE, ENGINE, TABLE_COLLATION, CREATE_OPTIONS, TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$escaped}' ORDER BY TABLE_NAME");
        $snapshot=['tables'=>[],'ddl'=>[],'columns'=>[],'indexes'=>[],'foreign_keys'=>[],'referential_actions'=>[],'checks'=>[],'rows'=>[]];
        while($table=$result->fetch_assoc()){
            if($table['TABLE_TYPE']!=='BASE TABLE'||$table['ENGINE']!=='InnoDB'){throw new RuntimeException('import_unsupported_table');}
            $snapshot['tables'][]=$table;
            $name=str_replace('`','``',$table['TABLE_NAME']);$safeSchema=str_replace('`','``',$schema);
            $definition=$db->query("SHOW CREATE TABLE `{$safeSchema}`.`{$name}`")->fetch_assoc();
            if(!is_string($definition['Create Table']??null)||$definition['Create Table']===''){throw new RuntimeException('import_table_definition_unavailable');}
            $snapshot['ddl'][$table['TABLE_NAME']]=$definition['Create Table'];
            $count=$db->query("SELECT COUNT(*) AS n FROM `{$safeSchema}`.`{$name}`")->fetch_assoc();
            $snapshot['rows'][$table['TABLE_NAME']]=(string)$count['n'];
        }
        $queries=[
            'columns'=>"SELECT TABLE_NAME, ORDINAL_POSITION, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, CHARACTER_SET_NAME, COLLATION_NAME, GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '{$escaped}' ORDER BY TABLE_NAME, ORDINAL_POSITION",
            'indexes'=>"SELECT TABLE_NAME, NON_UNIQUE, INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME, COLLATION, SUB_PART, INDEX_TYPE, EXPRESSION, IS_VISIBLE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '{$escaped}' ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX",
            'foreign_keys'=>"SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, ORDINAL_POSITION, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA = '{$escaped}' ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION",
            'referential_actions'=>"SELECT TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, MATCH_OPTION, UPDATE_RULE, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '{$escaped}' ORDER BY TABLE_NAME, CONSTRAINT_NAME",
            'checks'=>"SELECT CONSTRAINT_NAME, CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '{$escaped}' ORDER BY CONSTRAINT_NAME",
        ];
        $cross=$db->query("SELECT COUNT(*) AS n FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA = '{$escaped}' AND REFERENCED_TABLE_SCHEMA IS NOT NULL AND REFERENCED_TABLE_SCHEMA <> CONSTRAINT_SCHEMA")->fetch_assoc();
        if((int)$cross['n']!==0){throw new RuntimeException('import_cross_schema_dependency');}
        foreach($queries as $key=>$sql){$r=$db->query($sql);while($row=$r->fetch_assoc()){$snapshot[$key][]=$row;}}
        return $snapshot;
    }
    public static function compare(array $before,array $after,array $target):array
    {
        if($before!==$after){throw new RuntimeException('import_source_changed_during_verification');}
        $sourceMetadata=$before;$targetMetadata=$target;
        unset($sourceMetadata['ddl'],$targetMetadata['ddl']);
        if($sourceMetadata!==$targetMetadata){throw new RuntimeException('import_target_schema_or_rows_mismatch');}
        $ddlEvidence=[];
        if(($before['ddl']??[])!==($target['ddl']??[])){
            if(array_keys($before['ddl']??[])!==array_keys($target['ddl']??[])){throw new RuntimeException('import_target_schema_or_rows_mismatch');}
            foreach($before['ddl'] as $table=>$definition){
                $columns=array_values(array_filter($before['columns'],static fn(array $c):bool=>$c['TABLE_NAME']===$table));
                $targetColumns=array_values(array_filter($target['columns'],static fn(array $c):bool=>$c['TABLE_NAME']===$table));
                try{$ddlEvidence[$table]=EffectiveSchemaComparison::ddlEvidence($definition,$target['ddl'][$table],$columns,$targetColumns);}
                catch(RuntimeException){throw new RuntimeException('import_target_schema_or_rows_mismatch');}
            }
        }
        return ['schema_and_row_counts_match'=>true,'source_metadata_and_counts_unchanged'=>true,
            'table_count'=>count($target['tables']),'row_counts'=>$target['rows'],
            'data_value_equality'=>'not_verified','external_concurrent_writes'=>'not_excluded','ddl_evidence'=>$ddlEvidence];
    }
    public function close():void
    {
        foreach(['source','target'] as $key){if($this->$key!==null){$this->$key->close();$this->$key=null;}}
    }
}
