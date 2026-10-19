<?php

declare(strict_types=1);

namespace BackupApp\Backup;

use BackupApp\Database\AuthorizedSourcePolicy;
use RuntimeException;

final class MysqldumpEngine implements DumpEngine
{
    public function __construct(private readonly MysqldumpProcess $process = new MysqldumpProcess()) {}

    public function dump(string $database, array $connection, string $temporarySql): array
    {
        (new AuthorizedSourcePolicy())->assertSelected($database,$connection['selected_databases']??[]);
        MysqldumpCommand::assertConnection($connection);
        $command = new MysqldumpCommand();
        $command->verifyBinary();
        $credentials = new TemporaryMysqlCredentials();
        $out = null; $err = null;
        try {
            // BackupService owns this empty, create-only reserved file.
            if (!is_file($temporarySql) || is_link($temporarySql) || filesize($temporarySql) !== 0) {
                throw new RuntimeException('mysqldump_output_not_reserved_empty');
            }
            $file = $credentials->create((string) ($connection['password'] ?? ''), $connection['username'], $connection['host'], $connection['port']);
            $out = @fopen($temporarySql, 'r+b');
            $err = @fopen($credentials->stderrPath(), 'xb+');
            if ($out === false || $err === false) { throw new RuntimeException('mysqldump_output_create_failed'); }
            $exit = $this->process->run($command->arguments($database, $file, $connection['selected_databases']), $out, $err);
            if ($exit !== 0) { throw new RuntimeException('mysqldump_exit_' . $exit); }
            if ((fstat($err)['size'] ?? 0) !== 0) { throw new RuntimeException('mysqldump_diagnostic_output'); }
            $size = fstat($out)['size'] ?? 0;
            rewind($out);
            $header = fread($out, 4096);
            fseek($out, max(0, $size - 4096));
            $tail = stream_get_contents($out, 4096);
            if ($size < 64 || !is_string($header) || !str_starts_with($header, '-- MySQL dump ')
                || !str_contains($header, 'Database: ' . $database)
                || !is_string($tail) || preg_match('/\n-- Dump completed on [^\r\n]+\s*\z/D', $tail) !== 1) {
                throw new RuntimeException('mysqldump_output_incomplete');
            }
            fseek($out, 0, SEEK_END);
            $marker = "\n-- Backup completed successfully\n";
            if (fwrite($out, $marker) !== strlen($marker) || !fflush($out)) {
                throw new RuntimeException('mysqldump_completion_write_failed');
            }
            return ['name' => 'mysqldump', 'version' => MysqldumpCommand::VERSION,
                'binary_sha256' => MysqldumpCommand::SHA256, 'profile' => MysqldumpCommand::PROFILE,
                'options' => MysqldumpCommand::OPTIONS, 'exit_code' => 0];
        } finally {
            if (is_resource($out)) { fclose($out); }
            if (is_resource($err)) { fclose($err); }
            $credentials->close();
        }
    }
}
