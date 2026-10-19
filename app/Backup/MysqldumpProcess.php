<?php

declare(strict_types=1);

namespace BackupApp\Backup;

use RuntimeException;

/** File-backed stdout/stderr avoid Windows anonymous-pipe backpressure. */
class MysqldumpProcess
{
    /** @param list<string> $command @param resource $stdout @param resource $stderr */
    public function run(array $command, $stdout, $stderr, float $timeout = 3600,
        int $stdoutLimit = 17_179_869_184, int $stderrLimit = 65536, string $input = '', ?callable $cancel = null, $inputStream = null): int
    {
        if ($inputStream !== null && !is_resource($inputStream)) { throw new RuntimeException('mysql_input_stream_invalid'); }
        $environment = [];
        foreach (['SystemRoot', 'WINDIR', 'SystemDrive', 'TEMP', 'TMP'] as $key) {
            $value = getenv($key);
            if (is_string($value)) { $environment[$key] = $value; }
        }
        $process = @proc_open($command, [0 => $inputStream ?? ['pipe', 'r'], 1 => $stdout, 2 => $stderr], $pipes,
            null, $environment, ['bypass_shell' => true, 'suppress_errors' => true, 'create_process_group' => true]);
        if (!is_resource($process)) { throw new RuntimeException('mysqldump_process_start_failed'); }
        $closed = false;
        try {
            if (strlen($input) > 16384 || ($inputStream === null && $input !== '' && fwrite($pipes[0], $input) !== strlen($input))) {
                throw new RuntimeException('mysqldump_process_input_failed');
            }
            if (isset($pipes[0])) { fclose($pipes[0]); }
            $started = hrtime(true);
            while (true) {
                $status = proc_get_status($process);
                $outSize = fstat($stdout)['size'] ?? $stdoutLimit + 1;
                $errSize = fstat($stderr)['size'] ?? $stderrLimit + 1;
                if ($outSize > $stdoutLimit || $errSize > $stderrLimit) {
                    throw new RuntimeException('mysqldump_output_limit');
                }
                if (!$status['running']) {
                    $exit = (int) $status['exitcode'];
                    $closeExit = proc_close($process);
                    $closed = true;
                    return $exit >= 0 ? $exit : $closeExit;
                }
                if ((hrtime(true) - $started) / 1_000_000_000 > $timeout) {
                    throw new RuntimeException('mysqldump_timeout');
                }
                if ($cancel !== null && $cancel()) { throw new RuntimeException('mysqldump_cancelled'); }
                usleep(20_000);
            }
        } finally {
            if (isset($pipes[0]) && is_resource($pipes[0])) { fclose($pipes[0]); }
            if (!$closed) {
                proc_terminate($process);
                proc_close($process);
            }
        }
    }
}
