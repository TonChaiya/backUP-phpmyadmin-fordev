# Phase 3 Safe Restore Preflight Implementation Plan

> Execute inline in the existing workspace. The user's strict rule forbids commits, pushes, tags, releases, database restore execution, and SQL executors, so this plan deliberately contains none of those steps.

**Goal:** Add fail-closed artifact provenance/integrity validation, conservative structural SQL validation, file-backed restore-preflight jobs, safety gates, history, and minimal UI while keeping every restore execution path disabled.

**Architecture:** A read-only provenance index links artifacts to existing Phase 2 succeeded job evidence. History performs cheap metadata inspection only. An authenticated preflight job reopens the actual artifact, hashes and validates it, parses its structure, checks an offline target policy, then finishes `blocked` because mandatory live safety attestations are unavailable. Invalid input finishes `failed`; Phase 3 has no `succeeded` transition.

**Tech Stack:** PHP 8.3, native sessions/JSON Lines/`flock`, browser Fetch API, existing lightweight PHP test harness. Tests use temporary files and fixtures only.

---

### Task 1: Provenance and two-level artifact validation

**Files:**
- Create: `app/Restore/BackupProvenanceIndex.php`
- Create: `app/Restore/RestoreArtifactValidator.php`
- Test: `tests/Phase3ArtifactValidationTest.php`

1. Write failing behavior tests proving quick history inspection does not read/hash SQL bytes, full preflight detects byte/hash/final-marker/manifest/restore-order changes, exact succeeded provenance is required, and legacy artifacts return `unverified_origin`.
2. Run `php tests/run.php Phase3ArtifactValidation` and record RED.
3. Implement bounded manifest loading, root containment, quick metadata status, actual preflight hashing, strict final marker, and read-only Phase 2 provenance indexing.
4. Re-run the focused test to GREEN.

### Task 2: Conservative parser, target policy, and safety gates

**Files:**
- Create: `app/Restore/StructuralSqlParser.php`
- Create: `app/Restore/RestoreTargetValidator.php`
- Create: `app/Restore/RestoreSafetyGate.php`
- Test: `tests/Phase3SqlSafetyTest.php`

1. Write failing fixture tests for the exact supported dump envelope and for rejection of mismatched schema scope, qualified references, `DEFINER`, dynamic SQL, `DROP DATABASE`, file operations, unsupported syntax, and malformed delimiter/quote state.
2. Add target tests for source/system/`jhcisdb`/unsafe names and gate tests proving all runtime attestations stay `not_verified` with no flag override.
3. Run `php tests/run.php Phase3SqlSafety` and record RED.
4. Implement a delimiter-aware conservative validator, offline target policy, and hard-closed gate.
5. Re-run the focused test to GREEN.

### Task 3: Restore-preflight jobs and duplicate protection

**Files:**
- Create: `app/Restore/RestoreJobRequestException.php`
- Create: `app/Restore/RestoreJobStore.php`
- Create: `app/Restore/RestoreExecutionGuard.php`
- Create: `app/Restore/RestoreJobAccess.php`
- Create: `app/Restore/RestorePreflightRunner.php`
- Test: `tests/Phase3RestoreJobTest.php`

1. Write failing tests for append-only snapshots, safe IDs, secret-free persistence, session ownership, per-job/global preflight locks, orphan recovery, and state transitions limited to `blocked`, `failed`, or `interrupted`.
2. Prove a valid artifact ends `blocked` with no execution-start field and invalid input ends `failed`; assert no `succeeded` state is accepted.
3. Run `php tests/run.php Phase3RestoreJob` and record RED.
4. Implement the minimal store/access/guard/runner around file-only preflight.
5. Re-run the focused test to GREEN.

### Task 4: Authenticated endpoint and minimal restore/history UI

**Files:**
- Create: `public/restore-job.php`
- Create: `public/assets/restore-jobs.js`
- Create: `views/pages/restore.php`
- Modify: `app/Http/Router.php`
- Modify: `public/index.php`
- Modify: `views/pages/history.php`
- Modify: `views/layout.php`
- Modify: `config/app.php`
- Modify: `app/bootstrap.php`
- Modify: `.gitignore`
- Test: `tests/Phase3ApiAndUiTest.php`

1. Write failing tests for restore route/view, authenticated ownership and CSRF contract, truthful quick-history labels, blocked/failed display, absence of success/execute controls, and secret/path-free public job projection.
2. Run `php tests/run.php Phase3ApiAndUi` and record RED.
3. Implement create/status/preflight actions only, release session locking before file preflight, and add the minimal shared-layout UI.
4. Re-run the focused test plus only the affected routing/view and storage/download filters.

### Task 5: Focused verification and handoff

**Files:**
- Modify: `TEST_BASELINE.md`

1. Run all `Phase3` tests and capture exact counts.
2. Run only Phase 1/2 filters affected by router, layout, history, bootstrap storage paths, or artifact interpretation; document why unaffected engine tests were not repeated.
3. Lint every changed PHP file using the available PHP binary.
4. Inspect the Phase 3 diff and search for SQL connection/execution APIs, restore enable flags, string database-name replacement, tracked backup changes, and accidental Phase 2 executor edits.
5. Request read-only focused review, then resolve confirmed Phase 3 defects without expanding scope.
6. Apply verification-before-completion and record Implemented, Tested, `NOT VERIFIED`, risks, and Phase 4 handoff in `TEST_BASELINE.md`.

## Review focus before implementation

1. Every eligibility result requires positive provenance plus full content validation.
2. History never implies checksum verification and never hashes SQL files.
3. Parser ambiguity always rejects; it never rewrites SQL.
4. Job state cannot reach `succeeded`, and `blocked` never implies execution.
5. No database client, SQL executor, production connection, or Phase 2 backup lock/executor modification enters the change set.
