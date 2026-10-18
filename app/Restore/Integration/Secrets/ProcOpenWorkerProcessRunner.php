<?php
declare(strict_types=1);
namespace BackupApp\Restore\Integration\Secrets;

use Throwable;

final class ProcOpenWorkerProcessRunner implements WorkerProcessRunner
{
    public function run(array $command, string $stdin, int $timeoutMs, int $maxOutputBytes): WorkerProcessResult
    {
        if ($stdin === '' || strlen($stdin) > 16384 || $timeoutMs < 1 || $timeoutMs > 5000 || $maxOutputBytes > 65536) {
            throw new SecretProviderException('worker_input_invalid');
        }
        $pipes = [];
        $process = null;
        try {
            $process = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
            if (!is_resource($process)) { throw new SecretProviderException('worker_unavailable'); }
            $inputOffset = 0;
            while ($inputOffset < strlen($stdin)) {
                $written = fwrite($pipes[0], substr($stdin, $inputOffset));
                if (!is_int($written) || $written < 1) { throw new SecretProviderException('worker_failed'); }
                $inputOffset += $written;
            }
            fclose($pipes[0]);
            $start = hrtime(true); $lastStatus = null;
            while (true) {
                $status = proc_get_status($process);
                $lastStatus = $status;
                if (!$status['running']) { break; }
                if ((hrtime(true) - $start) / 1_000_000 > $timeoutMs) { proc_terminate($process); throw new SecretProviderException('worker_timeout'); }
                usleep(10000);
            }
            $stdout = (string) stream_get_contents($pipes[1], $maxOutputBytes + 1);
            $stderr = (string) stream_get_contents($pipes[2], $maxOutputBytes + 1);
            $hadStderr = $stderr !== '';
            if (strlen($stdout) > $maxOutputBytes || strlen($stderr) > $maxOutputBytes) { throw new SecretProviderException('worker_output_invalid'); }
            fclose($pipes[1]); fclose($pipes[2]);
            $closedCode = proc_close($process);
            $exitCode = is_array($lastStatus) && is_int($lastStatus['exitcode']) && $lastStatus['exitcode'] >= 0 ? $lastStatus['exitcode'] : $closedCode;
            return new WorkerProcessResult($exitCode, $stdout, $hadStderr);
        } catch (SecretProviderException $exception) {
            foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
            if (is_resource($process)) { @proc_terminate($process); @proc_close($process); }
            throw $exception;
        } catch (Throwable) {
            foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
            if (is_resource($process)) { @proc_terminate($process); @proc_close($process); }
            throw new SecretProviderException('worker_failed');
        }
    }
}
