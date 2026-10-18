<?php

declare(strict_types=1);

namespace BackupApp\Restore;

use BackupApp\Support\Logger;
use RuntimeException;
use Throwable;

final class RestorePreflightRunner
{
    public function __construct(
        private RestoreJobStore $store,
        private RestoreExecutionGuard $guard,
        private RestoreArtifactValidator $artifacts,
        private SqlStructureValidator $parser,
        private RestoreTargetValidator $targets,
        private RestoreSafetyGate $gate,
        private Logger $logger
    ) {
    }

    public function run(string $id): array
    {
        $jobLock = $this->guard->acquireJob($id);
        if ($jobLock === null) {
            throw new RuntimeException('Restore preflight is already running');
        }
        $globalLock = null;
        try {
            $job = $this->store->read($id);
            if ($job['status'] !== 'queued' || $job['preflight_claimed_at'] !== null) {
                throw new RuntimeException('Restore preflight cannot run in this state');
            }
            $globalLock = $this->guard->acquireGlobal(true);
            if ($globalLock === null) {
                throw new RuntimeException('Cannot acquire restore preflight lock');
            }
            $job = $this->store->update($id, static function (array $state): array {
                $state['status'] = 'running';
                $state['preflight_claimed_at'] = date('c');
                return $state;
            });

            $artifact = $this->artifacts->preflight($job['artifact']);
            if (($artifact['eligible'] ?? false) !== true) {
                return $this->finish($id, 'failed', (string) ($artifact['status'] ?? 'artifact_ineligible'), false, false, [], []);
            }

            $structure = $this->parser->validateFile((string) $artifact['sql_path'], (string) $artifact['database']);
            if (
                (int) ($structure['content_bytes'] ?? -1) !== (int) ($artifact['bytes'] ?? -2)
                || !is_string($structure['content_sha256'] ?? null)
                || !hash_equals((string) $artifact['sha256'], (string) $structure['content_sha256'])
            ) {
                return $this->finish($id, 'failed', 'artifact_changed_during_preflight', false, false, ['snapshot_mismatch'], []);
            }
            if (!$structure['supported']) {
                return $this->finish($id, 'failed', 'unsupported_sql', true, false, $structure['findings'], []);
            }

            $target = $this->targets->validate($job['target'], (string) $artifact['database']);
            if (!$target['valid']) {
                return $this->finish($id, 'failed', $target['code'], true, true, [], []);
            }

            $gate = $this->gate->evaluate();
            return $this->finish($id, 'blocked', $gate['code'], true, true, [], $gate['checks'], (string) $artifact['database']);
        } catch (Throwable $exception) {
            $this->logger->exception($exception);
            try {
                $current = $this->store->read($id);
                if ($current['status'] === 'running') {
                    return $this->finish($id, 'failed', 'preflight_internal_error', false, false, [], []);
                }
            } catch (Throwable $persistenceFailure) {
                $this->logger->exception($persistenceFailure);
            }
            throw $exception;
        } finally {
            $this->guard->release($globalLock);
            $this->guard->release($jobLock);
        }
    }

    public function recoverInterrupted(string $id): array
    {
        $job = $this->store->read($id);
        if (!in_array($job['status'], ['queued', 'running'], true) || $job['preflight_claimed_at'] === null) {
            return $job;
        }
        $lock = $this->guard->acquireJob($id);
        if ($lock === null) {
            return $job;
        }
        try {
            return $this->finish($id, 'interrupted', 'preflight_interrupted', false, false, [], []);
        } finally {
            $this->guard->release($lock);
        }
    }

    /** @param array<int, string> $findings @param array<string, string> $checks */
    private function finish(string $id, string $status, string $code, bool $integrity, bool $structure, array $findings, array $checks, ?string $source = null): array
    {
        return $this->store->update($id, static function (array $state) use ($status, $code, $integrity, $structure, $findings, $checks, $source): array {
            $state['status'] = $status;
            $state['finished_at'] = date('c');
            $state['result'] = [
                'code' => $code,
                'message' => self::message($code),
                'source_database' => $source,
                'integrity_verified' => $integrity,
                'structure_supported' => $structure,
                'findings' => $findings,
                'checks' => $checks,
            ];
            return $state;
        });
    }

    private static function message(string $code): string
    {
        return match ($code) {
            'live_restore_not_verified' => 'ผ่านการตรวจสอบไฟล์แล้ว แต่ Restore ถูกปิดเพราะยังไม่มีหลักฐาน Isolation และสิทธิ์ฐานทดลอง',
            'protected_target' => 'ฐานข้อมูลปลายทางเป็นชื่อที่ได้รับการป้องกัน',
            'invalid_target_name' => 'ชื่อฐานข้อมูลปลายทางไม่ผ่านนโยบายความปลอดภัย',
            'unsupported_sql' => 'รูปแบบ SQL ไม่อยู่ในขอบเขตที่ parser รับรอง',
            'unverified_origin' => 'ไม่พบ Phase 2 provenance ที่ยืนยันแหล่งที่มา',
            'preflight_interrupted' => 'การตรวจสอบถูกขัดจังหวะและจะไม่เริ่มใหม่อัตโนมัติ',
            'preflight_internal_error' => 'การตรวจสอบล้มเหลวอย่างปลอดภัยก่อนเริ่มการทำงานกับฐานข้อมูล',
            'artifact_changed_during_preflight' => 'ไฟล์เปลี่ยนระหว่างการตรวจสอบ จึงยกเลิกแบบ Fail-Closed',
            default => 'ไฟล์สำรองไม่ผ่านการตรวจสอบ Restore preflight',
        };
    }
}
