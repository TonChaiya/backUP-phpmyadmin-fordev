<?php

declare(strict_types=1);

namespace BackupApp\Backup;

use BackupApp\Support\Logger;
use RuntimeException;
use Throwable;

final class BackupJobRunner
{
    public function __construct(
        private BackupJobStore $store,
        private BackupExecutionGuard $guard,
        private Logger $logger
    ) {
    }

    /** @param array<int, string> $secrets */
    public function run(string $jobId, BackupOperation $operation, array $secrets = []): array
    {
        $jobLock = $this->guard->acquireJob($jobId);

        if ($jobLock === null) {
            throw new BackupJobRequestException('งานสำรองกำลังทำงานอยู่แล้ว', 409);
        }

        $globalLock = null;

        try {
            $job = $this->store->read($jobId);

            if ($job['status'] !== 'queued' || $job['execution_claimed_at'] !== null) {
                throw new BackupJobRequestException('งานสำรองนี้ไม่สามารถเริ่มซ้ำได้', 409);
            }

            $this->store->update($jobId, static function (array $state): array {
                $state['execution_claimed_at'] = date('c');

                return $state;
            });
            $globalLock = $this->guard->acquireGlobal(true);

            if ($globalLock === null) {
                throw new RuntimeException('Cannot acquire global backup lock');
            }

            try {
                $operation->start();
            } catch (Throwable $exception) {
                $this->logger->exception($exception, $secrets);

                return $this->store->update($jobId, static function (array $state): array {
                    $now = date('c');
                    $state['status'] = 'failed';

                    foreach ($state['databases'] as &$database) {
                        if ($database['status'] === 'queued') {
                            $database['status'] = 'failed';
                            $database['finished_at'] = $now;
                            $database['error'] = [
                                'code' => 'connection_failed',
                                'message' => 'ไม่สามารถเริ่มการสำรองฐานข้อมูลได้',
                            ];
                        }
                    }
                    unset($database);

                    return $state;
                });
            }

            $this->store->update($jobId, static function (array $state): array {
                $state['status'] = 'running';

                return $state;
            });

            foreach ($this->store->read($jobId)['databases'] as $databaseState) {
                $databaseName = (string) $databaseState['name'];
                $this->setDatabaseRunning($jobId, $databaseName);

                try {
                    $artifact = $operation->backup($databaseName);
                    $this->setDatabaseSucceeded($jobId, $databaseName, $artifact);
                } catch (Throwable $exception) {
                    $this->logger->exception($exception, $secrets);
                    $this->setDatabaseFailed($jobId, $databaseName);
                }
            }

            return $this->store->update($jobId, static function (array $state): array {
                $hasFailure = false;

                foreach ($state['databases'] as $database) {
                    if ($database['status'] !== 'succeeded') {
                        $hasFailure = true;
                        break;
                    }
                }

                $state['status'] = $hasFailure ? 'failed' : 'succeeded';

                return $state;
            });
        } finally {
            try {
                $operation->finish();
            } catch (Throwable $exception) {
                $this->logger->exception($exception, $secrets);
            }

            $this->guard->release($globalLock);
            $this->guard->release($jobLock);
        }
    }

    public function recoverInterrupted(string $jobId): array
    {
        $job = $this->store->read($jobId);

        if (
            in_array($job['status'], ['succeeded', 'failed', 'interrupted'], true)
            || $job['execution_claimed_at'] === null
        ) {
            return $job;
        }

        $jobLock = $this->guard->acquireJob($jobId);

        if ($jobLock === null) {
            return $job;
        }

        try {
            $job = $this->store->read($jobId);

            if (
                in_array($job['status'], ['succeeded', 'failed', 'interrupted'], true)
                || $job['execution_claimed_at'] === null
            ) {
                return $job;
            }

            return $this->store->update($jobId, static function (array $state): array {
                $now = date('c');
                $state['status'] = 'interrupted';

                foreach ($state['databases'] as &$database) {
                    if (in_array($database['status'], ['queued', 'running'], true)) {
                        $database['status'] = 'interrupted';
                        $database['finished_at'] = $now;
                        $database['error'] = [
                            'code' => 'execution_interrupted',
                            'message' => 'งานสำรองถูกขัดจังหวะและจะไม่ลองใหม่อัตโนมัติ',
                        ];
                    }
                }
                unset($database);

                return $state;
            });
        } finally {
            $this->guard->release($jobLock);
        }
    }

    public function interruptQueued(string $jobId, string $code, string $message): array
    {
        $jobLock = $this->guard->acquireJob($jobId);

        if ($jobLock === null) {
            return $this->store->read($jobId);
        }

        try {
            $job = $this->store->read($jobId);

            if ($job['status'] !== 'queued' || $job['execution_claimed_at'] !== null) {
                return $job;
            }

            return $this->store->update($jobId, static function (array $state) use ($code, $message): array {
                $now = date('c');
                $state['status'] = 'interrupted';

                foreach ($state['databases'] as &$database) {
                    if ($database['status'] === 'queued') {
                        $database['status'] = 'interrupted';
                        $database['finished_at'] = $now;
                        $database['error'] = ['code' => $code, 'message' => $message];
                    }
                }
                unset($database);

                return $state;
            });
        } finally {
            $this->guard->release($jobLock);
        }
    }

    private function setDatabaseRunning(string $jobId, string $name): void
    {
        $this->store->update($jobId, static function (array $state) use ($name): array {
            foreach ($state['databases'] as &$database) {
                if ($database['name'] === $name && $database['status'] === 'queued') {
                    $database['status'] = 'running';
                    $database['started_at'] = date('c');
                    break;
                }
            }
            unset($database);

            return $state;
        });
    }

    /** @param array<string, mixed> $artifact */
    private function setDatabaseSucceeded(string $jobId, string $name, array $artifact): void
    {
        $this->store->update($jobId, static function (array $state) use ($name, $artifact): array {
            foreach ($state['databases'] as &$database) {
                if ($database['name'] === $name && $database['status'] === 'running') {
                    $database['status'] = 'succeeded';
                    $database['finished_at'] = date('c');
                    $database['artifact'] = $artifact;
                    $database['error'] = null;
                    break;
                }
            }
            unset($database);

            return $state;
        });
    }

    private function setDatabaseFailed(string $jobId, string $name): void
    {
        $this->store->update($jobId, static function (array $state) use ($name): array {
            foreach ($state['databases'] as &$database) {
                if ($database['name'] === $name && $database['status'] === 'running') {
                    $database['status'] = 'failed';
                    $database['finished_at'] = date('c');
                    $database['error'] = [
                        'code' => 'backup_failed',
                        'message' => 'ไม่สามารถสำรองฐานข้อมูลนี้ได้',
                    ];
                    break;
                }
            }
            unset($database);

            return $state;
        });
    }
}
