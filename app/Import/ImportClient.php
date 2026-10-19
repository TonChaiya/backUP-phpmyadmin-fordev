<?php
declare(strict_types=1);
namespace BackupApp\Import;
interface ImportClient
{
    /** @param array<string,mixed> $job @return array<string,mixed> */
    public function execute(array $job,ImportArtifactVerifier $artifacts,string $password):array;
}
