# Phase 2 Backup Jobs Implementation Plan

> Execute inline with native PHP. The user's strict rule forbids commits, pushes, tags, releases, and Git-history changes, so this plan deliberately contains no commit steps.

**Goal:** Add reliable manual one/many/all database backups through a file-backed HTTP job executor with sequential execution, persisted truthful state, lock protection, and verified artifact publication.

**Architecture:** A session-authorized API creates file-backed jobs. A guarded HTTP executor releases the session lock and runs one database at a time through the existing `BackupService`. Append-only snapshots and filesystem locks provide observable state and duplicate/global protection. A focused publisher makes SQL/manifest finalization verifiable and compensating-safe.

**Tech Stack:** PHP 8.3, mysqli, native sessions, JSON Lines, `flock`, browser Fetch API, existing lightweight PHP tests.

---

### Task 1: Persistent jobs and execution guards

**Files:**
- Create: `app/Backup/BackupJobStore.php`
- Create: `app/Backup/BackupExecutionGuard.php`
- Test: `tests/Phase2JobStoreAndGuardTest.php`

1. Write failing tests for ID/path validation, ordered lifecycle persistence, corrupt trailing-line recovery, credential non-persistence, duplicate per-job lock, and global lock exclusion.
2. Run `php tests/run.php Phase2JobStoreAndGuard` and confirm RED.
3. Implement minimal append-only snapshots, summaries, safe reads/writes, and lock handles.
4. Re-run the focused test to GREEN.

### Task 2: Safe artifact publication

**Files:**
- Create: `app/Backup/BackupArtifactPublisher.php`
- Modify: `app/Backup/BackupService.php`
- Test: `tests/Phase2ArtifactPublisherTest.php`

1. Write failing fixture tests for marker/checksum/manifest verification, existing-file refusal, temporary cleanup, and rollback when the second final move fails.
2. Run the focused test and confirm RED.
3. Implement publisher and minimally route existing dump finalization through it; add exception cleanup around only the current temporary file.
4. Re-run the focused test to GREEN without connecting to MySQL.

### Task 3: Sequential job runner

**Files:**
- Create: `app/Backup/BackupOperation.php`
- Create: `app/Backup/MysqliBackupOperation.php`
- Create: `app/Backup/BackupJobRunner.php`
- Test: `tests/Phase2JobRunnerTest.php`

1. Write failing fake-operation tests for stable order, one call at a time, continue-on-error, start failure, accurate aggregate state, duplicate rejection, and orphan interruption.
2. Run the focused test and confirm RED.
3. Implement the interface, production adapter, runner state transitions, guarded execution, safe logging, and recovery.
4. Re-run the focused test to GREEN.

### Task 4: Authenticated API and backup status UI

**Files:**
- Create: `app/Backup/BackupJobAccess.php`
- Create: `public/job.php`
- Create: `public/assets/backup-jobs.js`
- Modify: `views/pages/backup.php`
- Modify: `views/layout.php`
- Modify: `public/index.php`
- Modify: `config/app.php`
- Modify: `.gitignore`
- Test: `tests/Phase2JobApiAndUiTest.php`

1. Write failing tests for ownership, database allowlist, secret-free response, manual creation contract, truthful labels/polling, and absence of percentage UI.
2. Run the focused test and confirm RED.
3. Implement create/execute/status actions using Phase 1 Auth/CSRF/session/storage policies; release the session lock before execution.
4. Add minimal status panel and polling/resume behavior to the existing backup page.
5. Re-run the focused test and the affected `RoutingAndViewTest` only.

### Task 5: Focused verification and handoff

**Files:**
- Modify: `TEST_BASELINE.md`
- Modify: `README.md` only if runtime/operator instructions need Phase 2 clarification

1. Run all `Phase2` tests and capture exact counts.
2. Run only Phase 1 tests whose shared dependencies changed; document which Phase 1 tests were intentionally not repeated and why.
3. Lint changed PHP files with the configured Laragon PHP 8.3 binary.
4. Inspect only the Phase 2 diff, verify no tracked SQL/backup artifact changed, and confirm no database command or connection was executed during tests.
5. Apply the verification-before-completion checklist and request a read-only focused code review if available.
6. Record commands, results, skips, unverified live-database behavior, risks, and Phase 3 handoff in `TEST_BASELINE.md`.
