# Restore Integration Extension Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task **only after explicit implementation approval**. Steps use checkbox (`- [ ]`) syntax. The current task permits planning only and forbids commits, database connections, and implementation.

**Goal:** Implement a fail-closed, test-only restore integration path that uses application-generated Phase 2 artifacts to restore sequentially into newly created, exact-owned disposable databases, validate the result, and clean up safely without touching unauthorized databases.

**Architecture:** Keep Phase 3 as a no-executor preflight. Add a separate `BackupApp\Restore\Integration` subsystem built around a typed restore plan, version-aware privilege/server attestations, append-only ownership evidence, one shared Backup/Restore operation lock, sequential execution, post-restore comparison, and exact-name cleanup. Milestones 1–9 are offline implementation and fixture verification; milestones 10–11 are authorization-gated readiness/live procedures and must not run automatically.

**Tech Stack:** PHP 8.3, mysqli behind narrow interfaces, append-only JSONL, local Windows `flock`, native HTML/JavaScript, existing custom PHP test runner, Laragon Apache/MySQL or MariaDB for separately authorized live tests only.

**Spec:** `RESTORE_INTEGRATION_DESIGN.md`

**Baseline:** `TEST_BASELINE.md`

## Global Constraints

- Source allowlist is exactly `autoprintdb`, `drugmap_db`, `drug_stock_check`, and `prepack_labels`; only Backup Reader may read them.
- `jhcisdb` is absolutely protected. No query may name or inspect it, including existence/schema/data checks; only offline policy/grant-text rejection may contain the literal name.
- Other non-system databases are unauthorized and may not be discovered, read, changed, compared, restored, or dropped.
- Target databases must be new, absent before the run, exact-name owned, and use `restore_test_<source>_<32hex-run-id>` with maximum length 64.
- Never use root, global write/admin privileges, unrestricted `SHOW DATABASES`, direct dump import, generic string replacement, `multi_query`, wildcard/prefix cleanup, or `DROP DATABASE IF EXISTS`.
- MySQL/MariaDB must be the current Windows Laragon instance. Backup Reader and Restore Runner must attest to the same stable server identity.
- Phase 2 artifact provenance, completion marker, manifest schema, bytes, SHA-256, and Phase 3 structural safety remain mandatory and are rechecked under the shared lock immediately before execution.
- Credentials remain in authenticated session/process memory only; never persist or render passwords, grants, SQL values, marker nonce, session IDs, or raw errors.
- Restore remains sequential (`concurrency = 1`), has no scheduler/background worker/auto retry/auto resume, and cannot reach `succeeded` until SQL execution and mandatory comparison pass.
- Cleanup requires exact target, run ID, server identity, creation evidence, marker nonce, session/job ownership, held shared lock, and protected-name checks. Unproven ownership means no DROP.
- Offline implementation authorization does not authorize MySQL connection, account/grant changes, or live testing. Milestone 10 credential provisioning/readiness and Milestone 11 each require their own explicit authorization after offline evidence review.
- Use change-based tests. Do not run unrelated Phase 1–4 suites unless a milestone modifies their dependency.
- Do not modify `BackupService` dump generation unless a separate, proven defect and approved design requires it.
- No commit, push, tag, or release while the current strict rule remains active; replace commit steps with review checkpoints.

## Dependency Graph and File Map

```text
M1 Typed plan/compiler
  -> M2 Policy + source catalog + privilege/server attestation
      -> M3 Ownership journal + cleanup decision
          -> M4 Shared operation lock migration
              -> M5 Integration job persistence/state machine
                  -> M6 Provisioner + typed executor + runner
                      -> M7 Post-restore validator
                          -> M8 Authenticated API/minimal UI
                              -> M9 Offline verification/review
                                  -> M10 Live-readiness package + authorization gate
                                      -> M11 Authorized live integration procedure
```

New production code lives under `app/Restore/Integration/` except the shared SQL lexer (`app/Restore/Sql/`) and shared operation lock (`app/Database/`). New integration job, ownership, authorization, and comparison files remain under private `storage/` roots created at runtime and denied by the existing root `.htaccess` rule.

## Review Focus

1. **Hidden scope change inside SQL:** a delimiter/comment/string/stored body attempts another schema or dynamic SQL; Milestone 1 must reject it without producing an executable statement.
2. **Grant text looks exact but is broad:** unescaped `_`/`%`, role/PUBLIC inheritance, `partial_revokes`, or a global privilege widens access; Milestone 2 must block it.
3. **Crash between CREATE and journal append:** target exists but ownership evidence is incomplete; Milestones 3 and 6 must preserve evidence where possible and otherwise forbid DROP.
4. **Old/new lock migration overlap:** an old Phase 2 process holds the legacy lock while Restore uses the new path; Milestone 4 must require quiescence and prove cross-operation exclusion.
5. **Data appears equal from weak hashing:** duplicate rows, NULL/empty, binary/text, timezone/spatial/version normalization hide differences; Milestone 7 must use typed, multiplicity-preserving evidence or return NOT VERIFIED.

---

## Milestone 1 — Shared SQL Lexer, Typed Statements, and Restore Plan Compiler

**Dependencies:** Existing `StructuralSqlParser`, `RestoreArtifactValidator`, and Phase 3 SQL safety tests only.

**Files:**

- Create: `app/Restore/Sql/LexedStatement.php`
- Create: `app/Restore/Sql/SqlStatementLexer.php`
- Create: `app/Restore/Integration/Sql/RestorePlan.php`
- Create: `app/Restore/Integration/Sql/RestorePlanCompiler.php`
- Create: `app/Restore/Integration/Sql/RestorePlanItem.php`
- Create: `app/Restore/Integration/Sql/ExecutableRestoreStatement.php`
- Create: `app/Restore/Integration/Sql/RestoreStatementKind.php`
- Create: `app/Restore/Integration/Sql/Statement/SourceDatabaseDeclaration.php`
- Create: `app/Restore/Integration/Sql/Statement/SourceDatabaseSelection.php`
- Create: `app/Restore/Integration/Sql/Statement/SessionDirective.php`
- Create: `app/Restore/Integration/Sql/Statement/CreateTable.php`
- Create: `app/Restore/Integration/Sql/Statement/DropObject.php`
- Create: `app/Restore/Integration/Sql/Statement/InsertRows.php`
- Create: `app/Restore/Integration/Sql/Statement/AlterTable.php`
- Create: `app/Restore/Integration/Sql/Statement/LockTables.php`
- Create: `app/Restore/Integration/Sql/Statement/UnlockTables.php`
- Create: `app/Restore/Integration/Sql/Statement/CreatePlaceholderView.php`
- Modify: `app/Restore/StructuralSqlParser.php` to consume `SqlStatementLexer` without widening its allowlist
- Test: `tests/RestoreIntegrationSqlPlanTest.php`
- Test: `tests/Phase3SqlSafetyTest.php` only because the shared lexer changes its dependency
- Fixtures: `tests/fixtures/restore-integration/sql/*.sql`

**Interfaces:**

- `SqlStatementLexer::streamFile(string $path): iterable<LexedStatement>` streams exact statement boundaries, delimiters, byte offsets, and hashable bytes with the existing 16 MiB per-statement ceiling.
- `RestorePlanCompiler::compileFile(string $path, array $artifactEvidence): RestorePlan` returns either a complete immutable plan or throws a typed unsupported/safety exception; no partial executable plan escapes.
- `RestorePlan::sourceDatabase(): string`, `items(): array`, `contentBytes(): int`, `contentSha256(): string`, `objectInventory(): array`, and `policyVersion(): string` are the downstream contract.
- Only `ExecutableRestoreStatement` provides `renderForTarget(): string`; source CREATE/USE metadata does not implement it. There is no raw/generic SQL statement class.

**Steps:**

- [ ] **1.1 Write failing lexer and target-mapping tests.** Cover quoted semicolons, comments, custom delimiters, executable comments, exact one source CREATE/USE, additional/mismatched CREATE/USE, qualified references, dynamic SQL, unsafe DEFINER, file/network/account/global operations, unknown statements, malformed endings, oversized statements, and source metadata never implementing `ExecutableRestoreStatement`.
- [ ] **1.2 Run RED:** `php tests/run.php RestoreIntegrationSqlPlanTest`; expect failures because lexer/compiler/types do not exist.
- [ ] **1.3 Implement the shared streaming lexer and typed plan compiler.** Extract only lexical mechanics from `StructuralSqlParser`; keep Phase 3 validation decisions intact. Store validated fields rather than a mutable SQL blob. Treat view/trigger/routine/function/event statements as unsupported until a dedicated typed validator exists.
- [ ] **1.4 Run GREEN:** `php tests/run.php RestoreIntegrationSqlPlanTest`; expect all selected tests pass.
- [ ] **1.5 Run the affected Phase 3 safety suite:** `php tests/run.php Phase3SqlSafetyTest`; expect the existing seven Phase 3 tests to remain green with no new accepted syntax.
- [ ] **1.6 Review checkpoint:** scan `app/Restore/Integration/Sql` for `mysqli`, PDO, process execution, `multi_query`, `RawSql`, database credentials, and generic string replacement; expect none.

**Acceptance Criteria:**

- Source CREATE DATABASE and USE are typed metadata only and cannot be sent to a connection.
- Every executable item belongs to a known allowlisted type with unqualified identifiers.
- Any unsupported/ambiguous statement makes the entire plan ineligible; no object is silently skipped.
- Byte count and SHA-256 cover the exact byte stream parsed.
- Phase 3 parser behavior remains fail-closed and unchanged from the caller's perspective.

**Test Scope:** Offline fixtures only. No mysqli object, socket, MySQL client, or live SQL.

---

## Milestone 2 — Integration Policy, Exact Source Catalog, Server Identity, and Privilege Attestation

**Dependencies:** Milestone 1 typed plan metadata.

**Files:**

- Create: `app/Restore/Integration/Policy/RestoreIntegrationPolicy.php`
- Create: `app/Restore/Integration/Policy/TrustedRunId.php`
- Create: `app/Restore/Integration/Policy/TrustedTargetName.php`
- Create: `app/Restore/Integration/Database/MetadataConnection.php`
- Create: `app/Restore/Integration/Database/AuthorizedSourceCatalog.php`
- Create: `app/Restore/Integration/Database/IntegrationBackupOperation.php`
- Create: `app/Restore/Integration/Attestation/ServerIdentity.php`
- Create: `app/Restore/Integration/Attestation/ServerIdentityProbe.php`
- Create: `app/Restore/Integration/Attestation/PrivilegeEvidence.php`
- Create: `app/Restore/Integration/Attestation/PrivilegeDialect.php`
- Create: `app/Restore/Integration/Attestation/MySqlPrivilegeDialect.php`
- Create: `app/Restore/Integration/Attestation/MariaDbPrivilegeDialect.php`
- Create: `app/Restore/Integration/Attestation/PrivilegeAttestor.php`
- Create: `app/Restore/Integration/Attestation/LiveRestoreAttestationGate.php`
- Test: `tests/RestoreIntegrationPolicyAndAttestationTest.php`
- Fixtures: `tests/fixtures/restore-integration/grants/mysql/*.txt`
- Fixtures: `tests/fixtures/restore-integration/grants/mariadb/*.txt`

**Interfaces:**

- `RestoreIntegrationPolicy::authorizedSources(): array` returns exactly the four fixed names; `assertSource(string $name): void` and `targetFor(string $source, TrustedRunId $runId): TrustedTargetName` enforce case/length/protected-name rules.
- `AuthorizedSourceCatalog::available(MetadataConnection $connection): array` issues one exact allowlist query and cannot accept arbitrary database names.
- `IntegrationBackupOperation` implements existing `BackupOperation` and composes `ConnectionFactory`, `AuthorizedSourceCatalog`, and unchanged `BackupService`.
- `ServerIdentityProbe::probe(MetadataConnection $connection): ServerIdentity` requires a stable version-adapter-supported identity; endpoint or `server_id` alone is insufficient.
- `PrivilegeAttestor::backupReaderEvidence(...)` and `restoreRunnerEvidence(...)` return immutable `PrivilegeEvidence`; they do not mutate grants.
- `LiveRestoreAttestationGate::evaluate(array $evidence): array{open: bool, code: string, checks: array}` opens only when all typed evidence belongs to the same run/server/target/artifact.

**Server-Level Privilege Design:**

| Account | Positive scope | Mandatory negative scope |
|---|---|---|
| Backup Reader | Exact four source schemas only; minimum read/metadata privileges required by actual table/view/trigger/routine/event inventory and server version | No DML writes, DDL, DROP, account/GRANT, FILE, PROCESS, SHOW DATABASES, SUPER/admin/dynamic global privileges, unauthorized schema grants, or access to `jhcisdb` |
| Restore Runner | Exact pre-proposed target schemas only, even before creation; minimum `CREATE`, `DROP`, `ALTER`, `INDEX`, `INSERT`, `SELECT`, `REFERENCES`, `LOCK TABLES`, and only the object-family privileges required by the typed plan (`CREATE VIEW`/`SHOW VIEW`, `TRIGGER`, routine/event privileges) | No source privileges, no `jhcisdb`/system/other schema privileges, no global/admin/file/account/replication/plugin privileges, no GRANT OPTION, no broad database patterns |

The DBA provisions both users outside the application after exact target names and actual product/version are known. Database-level patterns containing `_` or `%` must be parsed using the active MySQL/MariaDB semantics; escaped wildcard rules, MySQL `partial_revokes`, active/default roles, MariaDB roles and `PUBLIC` are resolved before acceptance. If routine metadata would require reading `mysql.*` or a broad/global privilege on the actual version, that artifact is blocked rather than widening the account.

**Steps:**

- [ ] **2.1 Write failing policy tests.** Assert the four exact sources, 32-hex run IDs, target lengths 57/56/62/60, collision-safe regeneration, protected/system/source rejection, case policy, and absence of prefix-based authorization.
- [ ] **2.2 Write failing catalog/operation tests.** A recording connection must observe an exact `information_schema.SCHEMATA` allowlist query and never `SHOW DATABASES`; `IntegrationBackupOperation` must call the existing backup service sequentially through Phase 2's interface.
- [ ] **2.3 Write failing privilege/server tests.** Cover stable ID unavailable, Backup/Runner server mismatch, root/current-user rejection, global grants, unescaped wildcard patterns, `partial_revokes`, roles/PUBLIC, unknown grant syntax, source rights on Runner, write rights on Reader, and exact least-privilege evidence.
- [ ] **2.4 Run RED:** `php tests/run.php RestoreIntegrationPolicyAndAttestationTest`; expect missing-class failures.
- [ ] **2.5 Implement policy, exact catalog, backup adapter, version dialects, identity probe, privilege attestor, and evidence-only gate.** No method may create users, grant/revoke privileges, or accept an enable boolean.
- [ ] **2.6 Run GREEN:** rerun the filtered suite; expect all selected tests pass.
- [ ] **2.7 Review checkpoint:** source-scan integration code for `SHOW DATABASES`, `root`, `GRANT `, `REVOKE `, `CREATE USER`, and queries naming protected/unauthorized schemas. Allowed occurrences are offline policy constants/error checks only; no executable DB operation may match.

**Acceptance Criteria:**

- Integration backup uses actual `BackupJobRunner`/`BackupService` but never the current unrestricted database repository.
- No account can pass with global/broad/wildcard ambiguity or unknown inherited privileges.
- Both credentials must attest to one stable Laragon server identity; unsupported identity/version blocks.
- No code provisions credentials or changes server grants.

**Test Scope:** Offline recording connections and captured synthetic grants only. Actual version/grants remain NOT VERIFIED.

---

## Milestone 3 — Ownership Journal, Creation Evidence, and Cleanup Decision Engine

**Dependencies:** Milestone 2 trusted run/target/server evidence.

**Files:**

- Create: `app/Restore/Integration/Ownership/OwnershipEvent.php`
- Create: `app/Restore/Integration/Ownership/OwnershipJournal.php`
- Create: `app/Restore/Integration/Ownership/OwnershipSnapshot.php`
- Create: `app/Restore/Integration/Ownership/OwnershipMarker.php`
- Create: `app/Restore/Integration/Ownership/CleanupDecision.php`
- Create: `app/Restore/Integration/Ownership/CleanupDecisionEngine.php`
- Test: `tests/RestoreIntegrationOwnershipAndCleanupTest.php`

**Interfaces:**

- `OwnershipJournal::create(TrustedRunId $runId, array $proposal): OwnershipSnapshot`, `append(OwnershipEvent $event): OwnershipSnapshot`, and `read(): OwnershipSnapshot` use append-only JSONL, file locking, schema version, revision, and previous-record SHA-256.
- `OwnershipMarker::generate(TrustedRunId $runId): OwnershipMarker` provides a random table name and 256-bit nonce; public/job/log views expose hashes only.
- `CleanupDecisionEngine::authorize(OwnershipSnapshot $journal, array $runtimeEvidence): CleanupDecision` is pure and returns `allowed=false` for any mismatch or missing evidence. It never executes DROP.

**Steps:**

- [ ] **3.1 Write failing journal tests.** Cover new run, append/reopen, incomplete trailing line, invalid revision/hash chain, path traversal ID, duplicate creation event, immutable source/target/server/artifact fields, and secret/nonce omission from public serialization.
- [ ] **3.2 Write failing cleanup-decision tests.** Cover pre-existing target, absent creation proof, target/server/run/job mismatch, missing/wrong marker, source/protected/system target, lost session ownership, missing held-lock evidence, exact valid ownership, and crash between create and durable journal evidence.
- [ ] **3.3 Run RED:** `php tests/run.php RestoreIntegrationOwnershipAndCleanupTest`; expect missing-class failures.
- [ ] **3.4 Implement journal, marker, snapshot, and pure decision engine.** Store private records under the configured ownership root with restrictive permissions; never infer authorization from filename prefix.
- [ ] **3.5 Run GREEN:** rerun the filtered suite; expect all selected tests pass.
- [ ] **3.6 Review checkpoint:** verify there is no SQL client dependency and no cleanup method in this milestone; decision output is evidence only.

**Acceptance Criteria:**

- Journal contains run ID, exact source/target, server identity, timestamps, artifact/job linkage, absence/creation/marker evidence, restore result, cleanup decision/result, and post-cleanup evidence.
- Prefix, target format, or journal presence alone never authorizes deletion.
- Partial/corrupt/missing evidence produces `cleanup_blocked_ownership_unproven`.

**Test Scope:** Offline temporary filesystem and synthetic runtime evidence only.

---

## Milestone 4 — Shared Database Operation Lock and Phase 2 Lock Migration

**Dependencies:** Milestone 3 evidence model; existing Phase 2 lock behavior.

**Files:**

- Create: `app/Database/DatabaseOperationLock.php`
- Create: `app/Database/FileDatabaseOperationLock.php`
- Modify: `app/Restore/DatabaseOperationLockContract.php` to extend the shared interface for backward compatibility
- Modify: `app/Backup/BackupExecutionGuard.php` so only its global lock delegates to the shared lock; per-job locks remain unchanged
- Modify: `app/bootstrap.php` to derive `database_operation_lock_path` under private `storage/database-operations/global.lock`
- Modify: `public/job.php` to inject the shared lock into `BackupExecutionGuard`
- Create: `tools/check-database-operation-lock-migration.php`
- Test: `tests/SharedDatabaseOperationLockTest.php`
- Test: `tests/Phase2JobStoreAndGuardTest.php` because its lock dependency changes
- Test: `tests/Phase2JobRunnerTest.php` because global serialization is affected

**Interfaces:**

- `DatabaseOperationLock::acquire(string $operationId, bool $blocking = true)` and `release($handle): void` preserve the existing resource/null convention.
- `BackupExecutionGuard::__construct(string $jobDirectory, DatabaseOperationLock $sharedLock)` makes the shared dependency explicit; `acquireGlobal()` delegates while `acquireJob()` retains its current path/behavior.
- Migration checker acquires old and new lock paths non-destructively, reports active ownership, and never connects to a database or terminates a process.

**Steps:**

- [ ] **4.1 Write failing shared-lock tests.** Cover backup-vs-backup, backup-vs-restore, restore-vs-restore, blocking/nonblocking behavior, release, invalid operation ID, lock order, and separate Windows PHP processes.
- [ ] **4.2 Run RED:** `php tests/run.php SharedDatabaseOperationLockTest`; expect missing shared implementation.
- [ ] **4.3 Implement shared lock and dependency injection.** Use one exact path, local `flock`, restrictive permissions, and per-job-then-shared acquisition order.
- [ ] **4.4 Run GREEN:** rerun the shared-lock suite.
- [ ] **4.5 Run affected Phase 2 suites only:** `php tests/run.php Phase2JobStoreAndGuardTest` and `php tests/run.php Phase2JobRunnerTest`; expect existing concurrency/order/recovery behavior remains green.
- [ ] **4.6 Verify migration checker offline:** it must report `quiescent` only when neither legacy nor shared lock is owned. Deployment procedure must stop if either is busy; it must not delete either lock file.
- [ ] **4.7 Review checkpoint:** confirm no `BackupService`, publisher, job-state, retry, or queue behavior changed.

**Acceptance Criteria:**

- Backup and Restore cannot hold the shared lock concurrently across processes.
- Existing Backup job duplication/recovery semantics remain unchanged.
- Migration cannot proceed while an old executor owns the legacy lock.
- No lock file is used as database ownership evidence.

**Test Scope:** Offline filesystem/process tests. No DB connection.

---

## Milestone 5 — Restore Integration Job Store, Per-Job Guard, and State Machine

**Dependencies:** Milestones 2–4.

**Files:**

- Create: `app/Restore/Integration/Job/RestoreIntegrationJobStore.php`
- Create: `app/Restore/Integration/Job/RestoreIntegrationJobAccess.php`
- Create: `app/Restore/Integration/Job/RestoreIntegrationJobGuard.php`
- Create: `app/Restore/Integration/Job/RestoreIntegrationJobException.php`
- Create: `app/Restore/Integration/Job/RestoreIntegrationState.php`
- Modify: `app/bootstrap.php` to derive private integration-job and ownership paths
- Test: `tests/RestoreIntegrationJobTest.php`

**Interfaces:**

- `RestoreIntegrationJobStore::create(TrustedRunId $runId, array $items): array`, `read(string $id): array`, and `update(string $id, callable $mutator): array` use append-only JSONL.
- Each ordered item contains exact source, artifact identity, target, state, safe result codes, timestamps, comparison summary, and cleanup summary; never credentials/SQL/data/nonces.
- Overall states are `queued`, `validating`, `running`, `succeeded`, `failed`, `interrupted`, `blocked`; per-item states use the same vocabulary.
- `RestoreIntegrationJobGuard::acquireJob(string $id)` is nonblocking; orphan recovery may mark a claimed job interrupted only after acquiring it.
- `RestoreIntegrationJobAccess` retains at most 20 valid session-owned IDs and exposes a safe public view.

**Transition Rules:**

```text
queued -> validating -> running -> succeeded|failed|interrupted
queued -> interrupted
validating -> blocked|failed|interrupted
terminal -> same terminal only
```

Items run sequentially. Artifact-local unsupported syntax may block that item before target creation. Server identity, privilege, shared-lock, journal, or systemic safety failures block the whole remaining run. An execution failure may continue to the next item only after exact cleanup is verified and the failure is classified item-local; otherwise remaining items become blocked.

**Steps:**

- [ ] **5.1 Write failing store/state tests.** Cover unpredictable IDs, ordered four-item creation, append/reopen/partial tail, allowed/illegal transitions, no premature success, safe serialization, traversal rejection, duplicate job lock, and active session ownership.
- [ ] **5.2 Write failing recovery tests.** Cover unclaimed queued job, live claimed job, orphaned validating/running job, no auto retry/resume/drop, and refresh/status reads causing no state change.
- [ ] **5.3 Run RED:** `php tests/run.php RestoreIntegrationJobTest`; expect missing-class failures.
- [ ] **5.4 Implement store/access/guard/state model.** Keep Phase 3 `RestoreJobStore` unchanged.
- [ ] **5.5 Run GREEN:** rerun the filtered suite; expect all selected tests pass.
- [ ] **5.6 Review checkpoint:** assert no state path reaches `succeeded` without an explicit comparison-passed field and no GET/read method mutates state.

**Acceptance Criteria:**

- Duplicate submissions/executions and illegal state jumps are rejected.
- Browser refresh cannot create, execute, resume, retry, clean, or succeed a job.
- Interrupted jobs are truthful and require explicit, ownership-verified cleanup.
- Phase 3 preflight history/state semantics remain separate.

**Test Scope:** Offline temporary jobs/sessions/locks only.

---

## Milestone 6 — Database Adapter, Target Provisioner, Sequential Executor, Runner, and Cleanup Coordinator

**Dependencies:** Milestones 1–5. This milestone remains offline-tested despite containing production DB code.

**Files:**

- Create: `app/Restore/Integration/Database/RestoreConnection.php`
- Create: `app/Restore/Integration/Database/MysqliRestoreConnection.php`
- Create: `app/Restore/Integration/Database/RestoreConnectionFactory.php`
- Create: `app/Restore/Integration/Authorization/ExecutionAuthorization.php`
- Create: `app/Restore/Integration/Execution/TargetDatabaseProvisioner.php`
- Create: `app/Restore/Integration/Execution/TypedRestoreExecutor.php`
- Create: `app/Restore/Integration/Execution/CleanupCoordinator.php`
- Create: `app/Restore/Integration/Execution/RestoreIntegrationRunner.php`
- Create: `app/Restore/Integration/Execution/ExecutionResult.php`
- Test: `tests/RestoreIntegrationExecutorTest.php`

**Interfaces:**

- `RestoreConnection` exposes narrow prepared exact-name metadata reads, `serverIdentity()`, `currentDatabase()`, `createTarget(TrustedTargetName, charset, collation)`, `selectTarget(TrustedTargetName)`, `execute(ExecutableRestoreStatement)`, ownership-marker methods, target-only validation reads, and `dropExactTarget(TrustedTargetName)`. It has no arbitrary query or multi-query public method.
- `RestoreConnectionFactory::makeRunner(array $sessionCredentials): RestoreConnection` rejects root and does not persist credentials.
- `TargetDatabaseProvisioner::createNew(...)` performs exact absence check, CREATE without IF EXISTS, target selection, marker creation/readback, and durable ownership events before returning created evidence.
- `TypedRestoreExecutor::execute(RestorePlan $plan, RestoreConnection $connection): ExecutionResult` executes ordered executable items one at a time, checks server/current target before each item, and stops on first failure.
- `CleanupCoordinator::cleanup(...)` calls the pure decision engine, checks marker/server/lock again, issues one exact DROP without IF EXISTS, and verifies exact absence.
- `ExecutionAuthorization` is the narrow immutable authorization contract consumed by the runner; it exposes exact run/server/artifact/target scope, expiry, context ownership, and single-use state without depending on HTTP/session storage.
- `RestoreIntegrationRunner::run(string $jobId, array $backupCredentials, array $runnerCredentials, ExecutionAuthorization $authorization): array` orchestrates final artifact hash, attestations, shared lock, item execution, validation, and finally cleanup.

**Steps:**

- [ ] **6.1 Write failing recording-adapter tests.** Assert metadata CREATE/USE are never executed, target CREATE uses trusted exact identifier, connection pins target, one statement at a time, no multi-query, ordered execution, current-target/server checks, and immediate stop on mismatch/failure.
- [ ] **6.2 Write failing provisioning tests.** Cover target collision, race where target appears after precheck, CREATE without IF EXISTS, marker collision with artifact inventory, marker readback, journal append failure, and stable creation evidence.
- [ ] **6.3 Write failing cleanup tests.** Cover every ownership prerequisite, exact DROP text/identifier, no prefix/wildcard/IF EXISTS, post-drop absence, cleanup failure, and unproven ownership retaining target for manual review.
- [ ] **6.4 Write failing runner tests.** Cover artifact change under lock, systemic vs item-local failures, guaranteed finally cleanup while process lives, cleanup-blocked result, no auto retry, and succeeded only after validator success.
- [ ] **6.5 Run RED:** `php tests/run.php RestoreIntegrationExecutorTest`; expect missing-class failures.
- [ ] **6.6 Implement narrow mysqli adapter and orchestration.** Production classes may contain SQL templates only for exact metadata/target operations and typed statements; they must not be invoked by tests with a real host.
- [ ] **6.7 Run GREEN:** rerun the filtered suite with recording/fake connections only.
- [ ] **6.8 Static safety scan:** fail if integration execution code contains `multi_query`, shell/process execution, mysql CLI calls, `DROP DATABASE IF EXISTS`, `SHOW DATABASES`, raw string replacement of schema names, or an unrestricted query method.
- [ ] **6.9 Review checkpoint:** confirm no code path names or probes `jhcisdb`; protected-name handling remains offline string rejection only.

**Acceptance Criteria:**

- Target is never created before artifact, parser, identity, privilege, collision, journal, authorization, and shared-lock gates pass.
- SQL execution is sequential and accepts typed statements only.
- Failure never becomes success and cannot silently leave an owned target unreported.
- Cleanup either proves exact deletion or reports the exact retained target; it never broadens deletion scope.

**Test Scope:** Offline fake/recording DB adapters only. Merely loading production mysqli classes must not open a connection.

---

## Milestone 7 — Post-Restore Structural and Data Validator

**Dependencies:** Milestone 6 target-only connection and typed plan.

**Files:**

- Create: `app/Restore/Integration/Validation/PostRestoreValidator.php`
- Create: `app/Restore/Integration/Validation/ValidationReport.php`
- Create: `app/Restore/Integration/Validation/SchemaComparator.php`
- Create: `app/Restore/Integration/Validation/RowCanonicalizer.php`
- Create: `app/Restore/Integration/Validation/RowMultisetComparator.php`
- Create: `app/Restore/Integration/Validation/StoredObjectComparator.php`
- Test: `tests/RestoreIntegrationValidationTest.php`
- Fixtures: `tests/fixtures/restore-integration/validation/*.json`

**Interfaces:**

- `PostRestoreValidator::validate(RestorePlan $expected, RestoreConnection $target): ValidationReport` reads target only.
- `ValidationReport` records per-area `pass|fail|not_verified`, safe counts/digests/findings, and `mandatoryPassed(): bool`; it exposes no row values or SQL bodies.
- `SchemaComparator` covers tables, ordered columns/types/null/default/generated/extra, PK/FK/actions, indexes/order/prefix/visibility, engine, charset, collation, AUTO_INCREMENT, and object inventory.
- `RowCanonicalizer` uses type-aware, length-prefixed, binary-safe encoding that separates NULL/empty values.
- `RowMultisetComparator` preserves duplicate multiplicity; tables with unique keys stream deterministically, while no-key tables use bounded private temporary chunks.
- `StoredObjectComparator` compares typed canonical definitions with target SHOW CREATE output using only version-adapter-approved normalization.

**Steps:**

- [ ] **7.1 Write failing schema/object tests.** Cover each required schema area, reordered columns/index parts, FK actions, collation/charset, AUTO_INCREMENT normalization, missing/extra objects, and unsupported stored object family.
- [ ] **7.2 Write failing data tests.** Cover duplicates, NULL vs empty, binary data, decimals, timestamps/time zones, bit/spatial/version-specific types, no-PK tables, row-count mismatch, content mismatch, bounded chunk cleanup, and no value leakage.
- [ ] **7.3 Run RED:** `php tests/run.php RestoreIntegrationValidationTest`; expect missing-class failures.
- [ ] **7.4 Implement comparators and report semantics.** A mandatory `not_verified` prevents overall success; do not compare against a later live source snapshot.
- [ ] **7.5 Run GREEN:** rerun the filtered suite.
- [ ] **7.6 Review checkpoint:** verify report/log/public serialization contains only counts, hashes, object names, stable codes, and statuses.

**Acceptance Criteria:**

- `artifact_to_target` can be PASS only when every mandatory area is verified.
- `source_to_artifact_snapshot_consistency` remains NOT VERIFIED unless a separately approved consistent-snapshot design is implemented.
- Weak aggregate hashing cannot hide duplicate-row changes.
- Unsupported type/object semantics are reported truthfully and block success when mandatory.

**Test Scope:** Offline deterministic fixtures and fake target metadata/rows only.

---

## Milestone 8 — Authenticated Proposal, Authorization Receipt, API, and Minimal UI

**Dependencies:** Milestones 1–7.

**Files:**

- Create: `app/Restore/Integration/Authorization/LiveAuthorizationReceipt.php` implementing `ExecutionAuthorization`
- Create: `app/Restore/Integration/Authorization/LiveAuthorizationReceiptStore.php`
- Create: `public/restore-integration.php`
- Create: `public/assets/restore-integration.js`
- Create: `views/pages/restore-integration.php`
- Modify: `public/index.php` to render the operator view/read-only summaries
- Modify: `views/partials/navigation.php` only if a separate operator navigation entry is approved; otherwise link from Restore page
- Modify: `.htaccess` to rewrite only the new explicit public endpoint
- Test: `tests/RestoreIntegrationApiAndUiTest.php`
- Test: `tests/phase-restore-integration-ui.test.js`
- Test: `tests/RoutingAndViewTest.php` only because routing/shared navigation changes
- Test: `tests/SecurityFoundationTest.php` only because a new mutating endpoint is added

**Interfaces and HTTP Actions:**

- `proposal` POST: Auth+CSRF; creates a non-executing run proposal with exact sources/targets and no DB connection.
- `status` GET: Auth+session ownership+CSRF header; reads persisted state only.
- `authorize` POST: requires configured administrator authentication (unconfigured loopback mode is insufficient), CSRF, current readiness evidence, exact run/artifact/target/server scope, short expiry, single use; writes a private receipt, not a global flag.
- `execute` POST: requires exact unused receipt, session/context ownership, both dedicated credential roles, and runtime gate; releases PHP session lock before long work.
- `cleanup` POST: explicit recovery action for interrupted runs; still requires ownership evidence and exact target verification.

**Steps:**

- [ ] **8.1 Write failing API/security tests.** Cover auth, configured-auth requirement, CSRF, session ownership, exact receipt scope/expiry/single-use, duplicate submission, safe JSON, refresh GET immutability, credential omission, and no execute action without readiness/receipt.
- [ ] **8.2 Write failing UI/JavaScript tests.** Show fixed source list, generated exact targets, offline/live gate labels, truthful item/job/cleanup states, no fake percentage, no raw grants/SQL/password/nonce, and refresh polling that creates nothing.
- [ ] **8.3 Run RED:** `php tests/run.php RestoreIntegrationApiAndUiTest` and `node tests/phase-restore-integration-ui.test.js`; expect missing endpoint/view/script failures.
- [ ] **8.4 Implement receipt store, endpoint, minimal operator view, and progressive enhancement.** Do not add a configuration enable switch. Execution remains unreachable without a scoped receipt and runtime evidence.
- [ ] **8.5 Run GREEN:** rerun the two focused suites.
- [ ] **8.6 Run affected routing/security tests:** `php tests/run.php RoutingAndViewTest` and `php tests/run.php SecurityFoundationTest`.
- [ ] **8.7 Run syntax/static checks:** PHP lint changed files; `node --check public/assets/restore-integration.js`; source scan for secret fields and forbidden automatic actions.
- [ ] **8.8 Review checkpoint:** verify Apache exposes only the explicit endpoint/assets and still blocks integration jobs, ownership, receipts, fixtures, and documentation.

**Acceptance Criteria:**

- No browser GET, refresh, reconnect, or page load can create/authorize/execute/cleanup.
- Live authorization is exact, expiring, one-use evidence—not a boolean config flag.
- API preserves Phase 1 Auth/Session/CSRF and exposes only session-owned safe views.
- UI never implies Restore started or succeeded before persisted evidence says so.

**Test Scope:** Offline HTTP/session/render/JavaScript fixtures only; use fake runner and no DB host.

---

## Milestone 9 — Offline Change-Based Verification and Review Gate

**Dependencies:** Milestones 1–8 complete.

**Files:**

- Modify: `TEST_BASELINE.md` by appending an **Offline Restore Integration** section only
- Create: `tools/verify-restore-integration-offline.ps1`
- Modify: implementation/test files only when a focused failure proves a defect

**Steps:**

- [ ] **9.1 Run all new Restore Integration suites only** through the wrapper and record exact counts: SQL plan, policy/attestation, ownership/cleanup, shared lock, jobs, executor, validator, API/UI, and JavaScript behavior.
- [ ] **9.2 Run affected legacy suites only:** Phase 3 SQL safety; Phase 2 guard/runner; routing and security. Do not run full regression unless dependency analysis identifies another affected component and documents why.
- [ ] **9.3 Run syntax and forbidden-API scans.** Lint every new/modified PHP file; check JavaScript; fail on mysql CLI/process execution, raw schema replacement, `multi_query`, broad discovery/drop, root fallback, embedded credentials, or executable SQL outside typed adapters.
- [ ] **9.4 Run storage/web exposure checks.** Verify new private directories and files are denied through Apache and cannot be downloaded.
- [ ] **9.5 Conduct code review against the design.** Focus on the five Review Focus items, state transitions, TOCTOU, exact grant semantics, and cleanup evidence. Fix findings with focused RED/GREEN tests only.
- [ ] **9.6 Append baseline evidence.** Mark all DB/version/grant/live restore/comparison/cleanup results NOT VERIFIED; do not copy offline PASS into live rows.
- [ ] **9.7 Stop at Offline Implementation Gate.** Produce file list, test evidence, remaining risks, and request separate readiness authorization. Do not connect to MySQL.

**Acceptance Criteria:**

- Every new offline suite passes with exact counts and all affected legacy suites remain green.
- No test opened a MySQL socket or changed a database/user/grant/service.
- `TEST_BASELINE.md` clearly separates Implemented, Offline Tested, and Live NOT VERIFIED.
- Review finds no route around typed statements, runtime evidence, scoped authorization, ownership, or shared lock.

**Test Scope:** Offline only. This is the final milestone permitted by an implementation-only authorization.

---

## Milestone 10 — Live Readiness Package and Explicit Authorization Gate

**Dependencies:** Milestone 9 approved. This milestone has an offline substep and a separately authorized read-only substep.

**Files:**

- Create: `tools/restore-integration-readiness.php`
- Create: `docs/RESTORE_INTEGRATION_LIVE_RUNBOOK.md`
- Test: `tests/RestoreIntegrationReadinessTest.php`
- Modify: `TEST_BASELINE.md` only after actual authorized evidence exists

**Steps:**

- [ ] **10.1 Build and offline-test the readiness command in plan-only mode.** It accepts no default credentials, prints the four exact source and four exact target names, required evidence, stop conditions, and a redacted operator checklist; default behavior opens no connection.
- [ ] **10.2 Generate a run proposal.** Record 32-hex run ID and exact target names only; do not check server existence yet.
- [ ] **10.3 Stop and request explicit credential-provisioning authorization.** Scope it to two dedicated non-root accounts on the current Laragon server and the exact source/target privilege matrix; it must not authorize application execution, database creation/deletion, or any privilege involving `jhcisdb`, other schemas, global scope, wildcard scope, or GRANT OPTION.
- [ ] **10.4 After authorization, a DBA prepares the two dedicated accounts outside the application.** Provision only version-appropriate exact privileges from Milestone 2 and retain redacted evidence for review. Application code neither receives nor executes CREATE USER/GRANT/REVOKE statements.
- [ ] **10.5 Stop and request explicit read-only readiness authorization.** Required scope: current Laragon endpoint only; read product/version/current-user grants/stable identity; exact source existence/capabilities; exact target absence. Authorization must continue to prohibit SQL execution, target CREATE/DROP, source data comparison, and any query naming `jhcisdb`.
- [ ] **10.6 After authorization only, run read-only attestation.** Use both dedicated accounts, confirm same stable server identity, parse grants/roles/PUBLIC/pattern semantics, verify exact four sources with Backup Reader and exact target absence with Restore Runner, without unrestricted discovery.
- [ ] **10.7 Persist only redacted readiness evidence.** Passwords/raw grants/user host details remain out of baseline. Any mismatch stops before live authorization.
- [ ] **10.8 Stop and request explicit live integration authorization** scoped to the exact run ID, server fingerprint, artifact hashes, target names, and cleanup policy.

**Acceptance Criteria:**

- Plan-only readiness can run with zero DB access.
- Credential provisioning and actual attestation require separate authorizations; attestation additionally requires the two non-root credentials.
- Target collision, unstable/mismatched identity, broad/ambiguous privilege, unsupported version/dialect, missing source capability, or any need for protected-schema access yields STOP.
- No user/grant/database/service is changed during readiness.

**Test Scope:** `RestoreIntegrationReadinessTest` is offline. Step 10.6 is a live read-only check and remains NOT VERIFIED until actually authorized and run. Account/grant provisioning is an administrator action, not an application test.

---

## Milestone 11 — Authorized Backup → Restore → Compare → Cleanup Integration Run

**Dependencies:** Milestone 10 evidence approved and a new exact-scoped live authorization receipt. This milestone is documented now but must not run under design/planning or ordinary implementation approval.

**Files:**

- Create: `tools/restore-integration-live.php` with plan-only default and explicit run/receipt arguments
- Create: `tests/RestoreIntegrationLiveHarnessTest.php` for offline refusal/idempotency behavior
- Modify: `TEST_BASELINE.md` only with observed live results
- Runtime only after authorization: append private job/journal/receipt records and new backup artifacts; never alter old backups

**Authorized Execution Sequence:**

- [ ] **11.1 Revalidate authorization scope.** Exact run ID, Laragon server fingerprint, four sources, four targets, receipt expiry/single use, dedicated accounts, and cleanup rules must match Milestone 10 evidence.
- [ ] **11.2 Acquire per-job then shared database-operation lock.** Stop on duplicate/busy/migration ambiguity.
- [ ] **11.3 Re-attest both credentials and exact target absence.** Any drift is a stop condition; do not create anything.
- [ ] **11.4 Run the application's actual sequential backup workflow** with `IntegrationBackupOperation`, Phase 2 job store/runner, and unchanged `BackupService` for the four sources only.
- [ ] **11.5 Verify each backup result.** Require succeeded item/job state, SQL/manifest pair, completion marker, bytes, SHA-256, exact manifest schema, Phase 2 provenance, and full artifact eligibility. A failed source is recorded FAIL; no external dump substitutes for it.
- [ ] **11.6 Compile all four artifacts before any target CREATE.** Unsupported SQL, stored object, target mapping ambiguity, artifact change, or policy finding stops the run before creation and reports the affected source.
- [ ] **11.7 Execute one target at a time.** For each item: final revalidation, exact absence check, create+marker+journal evidence, sequential typed execution, post-restore validation, terminal item state, then cleanup in `finally`.
- [ ] **11.8 Apply failure continuation policy.** Continue to the next source only when the failure is item-local and any created target's cleanup is verified. Stop the whole run on privilege/identity/lock/journal/systemic failure or unverified cleanup.
- [ ] **11.9 Verify cleanup.** Confirm exact created targets are absent. With Backup Reader, verify only the four source names still exist; do not inspect their data/schema and do not query `jhcisdb`.
- [ ] **11.10 Record observed results.** For each source report Backup, parser support, Restore, schema comparison, data comparison, unsupported constructs, created target, dropped target, post-cleanup state, and errors. Keep source snapshot consistency NOT VERIFIED unless separately proven.
- [ ] **11.11 Stop.** No retry, no second run, no privilege widening, no manual mysql import, and no cleanup by prefix.

**Live Stop Conditions:**

| Condition | Required action |
|---|---|
| SQL unsupported, unknown, dynamic, unsafe DEFINER, cross-schema, file/network, or dialect ambiguity | Mark item/run blocked before CREATE; do not weaken parser |
| Backup artifact/provenance/hash/marker/manifest mismatch | Mark failed; do not restore |
| Root credential, broad/global/wildcard/role ambiguity, missing least privilege, or account-role crossover | Stop entire run before DB mutation |
| Server identity absent/mismatch or non-Laragon endpoint | Stop entire run |
| Target collision or race-created target | Do not use or drop it; create no replacement without a new proposal/authorization |
| Job/receipt/context/lock mismatch or duplicate submission | Reject; do not retry automatically |
| Journal/marker/creation evidence incomplete | Stop execution; do not DROP without full ownership proof |
| Statement/validation failure | Mark failed; run exact verified cleanup if ownership remains complete |
| Cleanup ownership mismatch or failed post-drop absence check | Do not broaden/force cleanup; report exact retained target and stop |
| Source snapshot changes or unsupported comparison semantics | Report NOT VERIFIED; never convert to PASS |

**Acceptance Criteria:**

- A source receives Live PASS only when its application backup, typed restore, mandatory artifact-to-target schema/data validation, exact cleanup, and post-cleanup checks were actually observed.
- No database outside the four source names and four exact run targets is queried or changed; `jhcisdb` is never named in a live query.
- Every target created by the run is either verified dropped or reported by exact name as retained due to blocked/failed cleanup.
- Existing backup files are not overwritten/deleted and source databases are never written.
- `TEST_BASELINE.md` differentiates PASS, FAIL, BLOCKED, and NOT VERIFIED with evidence; offline results are not presented as live results.

**Test Scope:** Offline harness refusal tests may run during implementation. Actual steps 11.1–11.11 are live tests and require separate explicit authorization.

---

## Offline Tests vs Live Tests

| Class | Included evidence | Status rule |
|---|---|---|
| Offline Tests | Fixtures, fake/recording adapters, temporary files, subprocess locks, HTTP/session simulation, PHP/JavaScript lint, forbidden-API and web-exposure scans | May be run only after implementation approval; report PASS/FAIL from observed output |
| Live readiness checks | Read-only product/version, stable server identity, current-user grant/role evidence, exact four-source capability, and exact four-target absence on the current Laragon server | Remains NOT VERIFIED until separately authorized and actually observed |
| Live Tests | Exact authorized Backup → Restore → Compare → Cleanup run using dedicated accounts and exact run-owned targets | Remains NOT VERIFIED until Milestone 11 is separately authorized and actually completed |

Offline PASS never implies live PASS. A failed or blocked live step is recorded as observed FAIL/BLOCKED; it is not replaced by fixture evidence.

---

## Execution and Authorization Gates

| Gate | Allows | Does not allow |
|---|---|---|
| Plan approval | Invoke an implementation workflow for Milestones 1–9 | MySQL connection, user/grant/database changes, live tests |
| Offline implementation approval | Create/modify code and run fixture/fake/HTTP/filesystem tests through Milestone 9 | Account/grant changes, Milestone 10.6 read-only DB attestation, or Milestone 11 |
| Credential-provisioning authorization | A DBA creates/changes only the two exact dedicated non-root accounts and exact server-version-aware grants from Milestone 2 | Application SQL execution, root use, global/wildcard/other-schema/`jhcisdb` privileges, target CREATE/DROP, live test |
| Read-only readiness authorization | Step 10.6 only against exact current Laragon endpoint with dedicated accounts | Backup data reads beyond necessary capabilities, CREATE/DROP, Restore, Compare, Cleanup |
| Live integration authorization | Exact Milestone 11 run ID/server/artifacts/targets and ownership-verified cleanup | Other servers/databases, root, privilege changes, retry, production restore |

## Completion Evidence Required from the Implementer

- Changed/created file list grouped by milestone.
- RED and GREEN output counts for every focused suite.
- Affected legacy test rationale and results; explicit list of suites intentionally not rerun.
- Static safety scan and private HTTP exposure evidence.
- State/lock/ownership/parser review findings and resolutions.
- `TEST_BASELINE.md` update separating Implemented, Offline Tested, Live NOT VERIFIED.
- Before any live request: exact authorization checklist containing run ID, server fingerprint, target names, account-role attestations, artifact hashes, stop conditions, and cleanup decision requirements.

No milestone includes a commit step because the current user instruction expressly prohibits Commit/Push/Tag/Release.
