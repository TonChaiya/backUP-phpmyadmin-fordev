<?php
declare(strict_types=1);

/** Read-only acceptance helpers, deliberately not wired to Import execution or job updates. */
final class LocalImportAcceptance
{
    public static function partitionQuery(string $schema):string
    {
        if(!in_array($schema,['autoprintdb','local_import_20261018_b2197f1be82de698'],true)){throw new RuntimeException('acceptance_schema_scope_invalid');}
        return "SELECT TABLE_NAME, PARTITION_NAME, SUBPARTITION_NAME, PARTITION_ORDINAL_POSITION, SUBPARTITION_ORDINAL_POSITION, PARTITION_METHOD, SUBPARTITION_METHOD, PARTITION_EXPRESSION, SUBPARTITION_EXPRESSION, PARTITION_DESCRIPTION, TABLESPACE_NAME, PARTITION_COMMENT FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = '{$schema}' ORDER BY TABLE_NAME, PARTITION_ORDINAL_POSITION, SUBPARTITION_ORDINAL_POSITION";
    }
    public static function ddlEvidence(string $source,string $target,array $sourceColumns,array $targetColumns):array
    {
        return \BackupApp\Import\EffectiveSchemaComparison::ddlEvidence($source,$target,$sourceColumns,$targetColumns);
    }

    public static function assertDataType(string $type):void
    {
        if(!in_array($type,['tinyint','smallint','mediumint','int','bigint','decimal','numeric','bit','char','varchar',
            'binary','varbinary','tinyblob','blob','mediumblob','longblob','tinytext','text','mediumtext','longtext',
            'date','datetime','timestamp','time','year','enum','set'],true)){throw new RuntimeException('acceptance_unsupported_data_type');}
    }

    public static function valueExpressions(string $column,string $type):array
    {
        self::assertDataType($type);$quoted='`'.str_replace('`','``',$column).'`';
        $expressions=['HEX(CAST('.$quoted.' AS BINARY))'];
        if(in_array($type,['enum','set'],true)){$expressions[]='HEX(CAST(CAST('.$quoted.' AS UNSIGNED) AS BINARY))';}
        return $expressions;
    }

    /** Exact byte comparison of sorted row encodings, not digest equality or row counts alone. */
    public static function rows(iterable $source,iterable $target,int $maxBytes=8388608,int $maxRows=10000):array
    {
        $read=static function(iterable $rows)use($maxBytes,$maxRows):array{
            $encoded=[];$bytes=0;
            foreach($rows as $row){
                if(!is_array($row)){throw new RuntimeException('acceptance_row_invalid');}
                foreach($row as $value){if($value!==null&&!is_string($value)){throw new RuntimeException('acceptance_value_not_lossless');}}
                $value=serialize(array_values($row));$bytes+=strlen($value);
                if($bytes>$maxBytes||count($encoded)>=$maxRows){throw new RuntimeException('acceptance_memory_bound');}
                $encoded[]=$value;
            }
            sort($encoded,SORT_STRING);return [$encoded,$bytes];
        };
        [$a,$aBytes]=$read($source);[$b,$bBytes]=$read($target);
        $digest=static function(array $rows):string{
            $hash=hash_init('sha256');foreach($rows as $row){hash_update($hash,pack('N',strlen($row)));hash_update($hash,$row);}return hash_final($hash);
        };
        return ['equal'=>$a===$b,'rows'=>count($a),'target_rows'=>count($b),'source_bytes'=>$aBytes,'target_bytes'=>$bBytes,
            'source_sha256'=>$digest($a),'target_sha256'=>$digest($b),'method'=>'exact_sorted_lossless_row_bytes_with_multiplicity'];
    }
}
