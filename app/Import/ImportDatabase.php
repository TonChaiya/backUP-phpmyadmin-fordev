<?php
declare(strict_types=1);
namespace BackupApp\Import;
interface ImportDatabase
{
    /** @param array<string,mixed> $job */
    public function start(array $job):void;
    /** @return array<string,mixed> */
    public function sourceSnapshot(string $source):array;
    /** @return array<string,mixed> */
    public function targetSnapshot(string $target):array;
    public function close():void;
}
