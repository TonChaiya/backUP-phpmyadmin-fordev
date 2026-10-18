<?php
declare(strict_types=1);
namespace BackupApp\Restore\Integration\Secrets;
interface WorkerProcessRunner
{
    /** @param list<string> $command */
    public function run(array $command, string $stdin, int $timeoutMs, int $maxOutputBytes): WorkerProcessResult;
}
