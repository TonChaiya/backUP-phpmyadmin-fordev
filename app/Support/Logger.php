<?php

declare(strict_types=1);

namespace BackupApp\Support;

use Throwable;

final class Logger
{
    public function __construct(private string $logPath)
    {
    }

    /**
     * @param array<int, string> $secrets
     */
    public function exception(Throwable $exception, array $secrets = []): void
    {
        $directory = dirname($this->logPath);

        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            return;
        }

        $message = $exception->getMessage();

        foreach ($secrets as $secret) {
            if ($secret !== '') {
                $message = str_replace($secret, '[REDACTED]', $message);
            }
        }

        $message = preg_replace('/[\r\n\t]+/', ' ', $message) ?? 'Application error';
        $line = sprintf(
            "[%s] %s: %s in %s:%d%s",
            date('c'),
            $exception::class,
            $message,
            $exception->getFile(),
            $exception->getLine(),
            PHP_EOL
        );

        if (@file_put_contents($this->logPath, $line, FILE_APPEND | LOCK_EX) !== false) {
            @chmod($this->logPath, 0640);
        }
    }
}

