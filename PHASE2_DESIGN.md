# Phase 2 Design Specification — Backup Engine & Sequential Queue

วันที่: 2026-10-16  
สถานะ: Approved architecture — File-backed HTTP Job Executor

## 1. Scope

Phase 2 adds reliable manual backup jobs for one, multiple, or all session-authorized databases. It preserves the existing SQL dump logic, Phase 1 authentication/session/CSRF/storage/download boundaries, shared layout, and manual-only operation. It does not add restore, scheduling, automatic backup, a CLI worker, a queue framework, or database writes.

## 2. Components

- `BackupJobStore`: append-only JSON Lines snapshots under private `storage/jobs/`.
- `BackupExecutionGuard`: one non-blocking per-job lock plus one global execution lock.
- `BackupJobRunner`: executes databases in declared order and continues after an individual failure.
- `BackupOperation`: test seam for the production `MysqliBackupOperation`.
- `BackupArtifactPublisher`: verifies and safely publishes SQL plus manifest as one logical result.
- `public/job.php`: authenticated, CSRF-protected create/execute/status API.
- Backup-page JavaScript: creates only after a manual submit, polls persisted state, and resumes the session's active job without creating another job.

## 3. Job Lifecycle and State Machine

Overall job states:

```text
queued -> running -> succeeded
                  -> failed
queued/running    -> interrupted (orphaned claimed execution only)
```

Each database starts `queued`, then becomes `running`, and finishes as `succeeded` or `failed`. If the PHP process disappears while owning a claimed job, any `queued` or `running` database is marked `interrupted`; interrupted work is never retried automatically.

A job is `succeeded` only when every database succeeds. It is `failed` when execution finishes and at least one database failed. Summary counts are derived from persisted per-database states and never estimated.

## 4. Sequential Execution and Locks

`BackupJobRunner` uses one ordered loop and invokes one database backup at a time. The per-job lock rejects duplicate execution of the same job. The global lock is held for the whole database loop and serializes all backup jobs across browser sessions (`concurrency = 1`). A job may wait for the global lock while its per-job lock remains held.

The executor records `execution_claimed_at` only after acquiring its per-job lock. Status recovery treats a non-terminal claimed job as interrupted only when that lock can be acquired, proving no live executor still owns it. An unclaimed queued job is not classified as interrupted.

## 5. File-backed Persistence

Each job uses an unpredictable 32-hex-character identifier and is stored as `storage/jobs/<id>.jsonl`. Every line is a complete JSON snapshot. Reads take a shared lock and use the last valid line, ignoring an incomplete trailing write. Writes take an exclusive lock, append one encoded snapshot, flush it, and never modify older snapshots. Job IDs are strictly validated before constructing a path.

The job file stores database names, timestamps, safe states, summary counts, safe error codes/messages, and allowed artifact metadata. It never stores a database password, connection credential, session ID/token, CSRF token, or authentication credential.

## 6. Credentials, Authentication, and Authorization

All API actions require Phase 1 authentication. Create and execute require valid CSRF tokens. The requested databases must be members of the connected session's allowed database list. Job ownership is stored server-side in the authenticated PHP session as job IDs; status and execute reject jobs not owned by that session.

Connection credentials are copied from the session into process memory for execution and are never persisted or returned to the browser. Each successful connection receives a random non-secret context ID stored only in the session; a job is bound to that context in the session ownership map. Reconnecting interrupts an unclaimed queued job or blocks execution of an already claimed job, so an old selection cannot silently target another server. The executor releases the PHP session lock before the long-running operation so status polling remains available. Logs redact the active password and browser responses expose only stable safe errors.

## 7. HTTP Timeout and Browser Disconnect Policy

Execution is initiated by an HTTP request. The endpoint applies `ignore_user_abort(true)` and removes the PHP execution time limit where supported, but these are resilience aids rather than durability guarantees. A web server, FastCGI process, operating-system restart, or administrator action can still terminate the executor.

Closing or refreshing the page never creates a new job. The active job ID remains in the session; the page can fetch its persisted status. If the executor is still alive, it may continue after disconnect. If its lock is no longer owned, status recovery marks the unfinished work interrupted. No scheduler, detached process, retry daemon, or automatic retry is introduced.

## 8. Artifact Finalization

The existing dump generation remains authoritative. It writes only to the current backup's temporary SQL file. On any dump exception that temporary file is removed.

Publishing performs these checks before success:

1. SQL temporary file exists and contains the completion marker.
2. Its SHA-256 matches the digest created while dumping.
3. Neither final SQL nor final manifest already exists.
4. A temporary manifest is written, decoded, and verified against filename, relative path, byte count, SHA-256, and `complete=true`.
5. Manifest is moved to its final name first; SQL is moved last, so a hard interruption cannot leave a visible final SQL without its manifest.
6. The published pair is re-read and verified.

If publishing fails, only temporary artifacts belonging to that attempt and any newly moved SQL without its manifest are removed. Existing successful backups are never overwritten or deleted. A database is marked succeeded only after final verification returns.

## 9. Error Handling and Logging

Failure of one database is logged with technical context and the runner continues with the next queued database. Persisted/browser errors use stable, non-sensitive messages. An operation-start failure marks every still-queued database failed because none can run. Expected request/state conflicts use typed 4xx responses; unexpected storage, lock, configuration, or runner failures return a generic 500 response. Store or runner failures are reported without embedding credentials. Logging failure does not change artifact state.

## 10. UI Contract

The existing shared layout/navigation is retained. The backup page adds one status panel driven only by the status API. It shows `queued`, `running`, `succeeded`, `failed`, or `interrupted` for each database plus exact state counts for the job. It does not show a percentage, ETA, or inferred byte progress.

The user may choose one or more allowed databases or use the existing all-databases action. JavaScript disables duplicate submission while an active job is non-terminal. Refresh resumes viewing the session's active job and does not create a job.

## 11. Security Boundaries

- Private job and lock files remain outside `public/` and are ignored by Git.
- Strict job-ID validation prevents traversal.
- Phase 1 Auth, Session, CSRF, storage-root policy, signed download authorization, and safe layout escaping remain in force.
- Status responses omit filesystem paths, credentials, session data, and raw exception text.
- Only databases already authorized in the current connection session may enter a job.
- The implementation never writes to source databases; it uses the existing read-only dump queries.

## 12. Change-based Test Matrix

| Area | Focused evidence | Data source |
|---|---|---|
| Job store | lifecycle snapshots, incomplete trailing record, ID validation, credential absence | temporary directory |
| Sequential runner | declared order, one-at-a-time calls, continue-on-error, accurate summary | fake operation |
| Protection | duplicate per-job execution and global serialization | temporary lock files |
| Recovery | claimed orphan becomes interrupted; unclaimed queue remains queued | temporary jobs/locks |
| Artifact publishing | marker/hash/manifest verification, no overwrite, rollback on second move failure | temporary SQL fixtures |
| API access | authentication, CSRF, ownership, database allowlist, safe response | isolated session/service fixtures and HTTP smoke where safe |
| UI | real state labels, polling contract, no percentage, no password rendering | rendered view/static JavaScript assertions |
| Existing affected behavior | focused routing/view and download-integrity checks only if their dependencies change | Phase 1 fixtures |

No live MySQL/MariaDB connection is used. SQL compatibility remains unverified unless a separately authorized disposable database becomes available.

## 13. Risks and Limitations

- HTTP execution cannot guarantee survival across web-server/FastCGI/OS termination; interruption is detected, not automatically recovered.
- An unclaimed queued job can remain queued until the user resumes/executes it; there is no scheduler.
- JSONL history grows with state transitions and job files have no automated retention in Phase 2.
- File locking depends on the local Windows filesystem; shared/network filesystem semantics are outside this phase.
- A two-file SQL/manifest publish cannot be physically atomic. Compensating rollback prevents a lone new SQL file from being treated as successful, and final verification gates success.

## 14. Rollback

Rollback is file-level only: remove the Phase 2 endpoints/classes/UI changes and restore the narrow `BackupService` finalization integration. No database schema, backup history, Git history, service, scheduler, or Laragon configuration is changed.
