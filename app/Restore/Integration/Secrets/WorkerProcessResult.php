<?php
declare(strict_types=1);
namespace BackupApp\Restore\Integration\Secrets;
final readonly class WorkerProcessResult
{
    public function __construct(public int $exitCode, public string $stdout, public bool $hadStderr) {}
}
