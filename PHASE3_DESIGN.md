# Phase 3 Design Specification — Safe Restore & File Management

วันที่: 2026-10-17  
สถานะ: Approved Architecture A — Conservative Structural Validation + No SQL Executor

## 1. Scope and governing rule

Phase 3 adds a read-only backup catalog, strict restore-artifact preflight, a conservative structural SQL parser, file-backed restore-preflight jobs, safety gates, history integration, and a minimal restore UI. It deliberately contains no SQL executor and cannot restore or modify any database.

The governing rule is fail-closed: an artifact or SQL construct is eligible only when every required property is positively proven. Unknown, ambiguous, malformed, unsupported, stale, or unavailable evidence produces `unsupported`, `ineligible`, `failed`, or `blocked`; it never produces success.

Phase 3 does not add scheduling, automatic restore, deletion, renaming, upload, SQL rewriting, database creation, database connection for restore, configuration-only enablement, or Phase 4 UI redesign.

## 2. Architecture boundaries

- `BackupProvenanceIndex` reads Phase 2 job snapshots as evidence without changing `BackupJobStore` or the backup executor.
- `RestoreArtifactValidator` has two explicit modes: lightweight catalog inspection and full preflight.
- `StructuralSqlParser` recognizes only the documented dump envelope and a small allowlist of statements emitted by the current `BackupService`.
- `RestoreTargetValidator` performs offline target-name policy checks only; it cannot prove server isolation, target existence, or privileges.
- `RestoreSafetyGate` reports the four mandatory runtime attestations as unavailable in Phase 3 and therefore always closes execution.
- `RestoreJobStore`, `RestoreJobAccess`, and `RestorePreflightRunner` persist and expose preflight work independently from Phase 2 backup jobs.
- `public/restore-job.php` offers authenticated create/status/preflight actions only. There is no execute action, executor class, SQL client call, or configuration flag that can enable restore.

## 3. Artifact identity and provenance

An artifact is the pair `<name>.sql` and `<name>.sql.json` under the configured backup storage root, matching the Phase 2 publisher. Paths are resolved through the existing storage policy and must remain contained by that root.

Phase 2 provenance is proven only when the latest valid snapshot of a Phase 2 per-database job entry is `succeeded`, and its published artifact `relative_path`, database name, byte count, and SHA-256 match the artifact under review. Provenance is read from existing append-only job files; it is never synthesized or backfilled.

Backups without matching Phase 2 provenance are classified `unverified_origin` and are not eligible. This includes Phase 1 and manually copied files even if their checksum and manifest are otherwise valid.

## 4. Lightweight catalog inspection

History uses lightweight inspection and must not hash SQL content. It may read file metadata, the small manifest, and an in-memory per-request provenance index. Results use truthful labels:

- `candidate`: SQL and manifest exist and basic metadata can be read, but content integrity has not been revalidated.
- `unverified_origin`: no matching Phase 2 provenance.
- `invalid_metadata`: path, manifest, or size metadata is malformed/inconsistent.
- `missing_pair`: SQL or manifest is absent.

Lightweight inspection never returns `verified` or restore eligibility. Only full preflight can do so.

## 5. Full restore preflight

Preflight reopens the actual SQL and manifest and performs all checks, regardless of catalog status:

1. Resolve and re-check storage containment without following a path outside the root.
2. Require both SQL and manifest as regular files.
3. Decode a bounded manifest and require the current schema fields.
4. Verify exact filename, normalized relative path, database name, `complete=true`, byte count, SHA-256, and the documented `restore_order` list.
5. Require the completion marker `-- Backup completed successfully` as the final non-whitespace content.
6. Require matching Phase 2 succeeded-job provenance; otherwise return `unverified_origin` and ineligible.
7. Stream the SQL through the structural parser while hashing the same byte stream; reject the job if that digest or byte count differs from the manifest-verified artifact snapshot, and reject every unsupported or ambiguous construct.
8. Validate the requested target name using offline policy.
9. Evaluate safety gates. Even a structurally eligible artifact becomes a `blocked` job because live restore attestations do not exist in Phase 3.

No SQL text is transformed or rewritten. In particular, database names are never replaced by string substitution.

## 6. Structural SQL parser contract

The parser is a validator, not an interpreter and not an executor. It tracks quoted strings, quoted identifiers, line/block comments, version comments, and explicit `DELIMITER` changes so statement boundaries are not guessed from raw semicolons. It evaluates completed statements incrementally instead of retaining the whole dump. A single statement larger than 16 MiB is rejected fail-closed as unsupported to keep memory bounded under common Laragon PHP limits.

Supported top-level statements are restricted to the subset of the current dump format that Phase 3 can prove structurally: the exact versioned session `SET` allowlist, one matching `CREATE DATABASE IF NOT EXISTS`, one matching `USE`, allowlisted-engine table DDL, table data `INSERT`, `LOCK/UNLOCK TABLES`, documented `ALTER TABLE` operations, object drops, and constant placeholder views. General view bodies and trigger/routine/function/event definitions are classified unsupported in Phase 3 because a regex-level validator cannot prove their complete semantics. Each accepted statement must remain within the one declared source schema.

The parser rejects at least:

- additional or mismatching `CREATE DATABASE` or `USE` statements;
- any schema-qualified reference whose safety cannot be proven;
- `DEFINER`, `PREPARE`, `EXECUTE`, dynamic SQL, or stored-code text that cannot be classified safely;
- `DROP DATABASE`, account/privilege statements, plugins, UDFs, `SOURCE`, `LOAD DATA`, `INFILE`, `OUTFILE`, and filesystem/network-capable constructs;
- malformed quoting/comments/delimiters, unsupported statement families, or unrecognized dump layout;
- embedded executable comments; only whole-statement versioned `SET` comments emitted by the current backup format are accepted.

Because correctness takes priority over compatibility, valid but more complex SQL may be marked `unsupported`. Such output is ineligible and execution remains disabled.

## 7. Target policy

Offline target validation requires a non-empty MySQL identifier of at most 64 characters using the conservative set `[A-Za-z0-9_]+`. It rejects the source database, all configured system databases, and `jhcisdb` case-insensitively.

This validation does not prove that a target is disposable, empty, isolated, reachable, or privilege-scoped. Those properties require a separately authorized live integration environment and remain `NOT VERIFIED`.

## 8. Restore-preflight job lifecycle

Restore jobs are separate from backup jobs and contain one artifact/target pair. Their states are:

```text
queued -> running -> blocked
                  -> failed
queued/running    -> interrupted
```

- `blocked` means artifact and structural validation completed but the mandatory live safety gate is closed. It does not mean SQL execution started.
- `failed` means artifact, parser, target, authorization, or internal validation failed.
- `interrupted` means an already-claimed preflight lost its process lock.
- `succeeded` is reserved for a future phase that performs and verifies a real restore; Phase 3 cannot create that state.

Jobs store only safe validation results, finding codes, artifact-relative identity, target name, timestamps, and ownership linkage in the authenticated session. They never store credentials, SQL content, session identifiers, or raw exception messages.

Per-job locking prevents duplicate preflight. A restore-preflight global lock serializes these read-only jobs. Browser disconnect handling mirrors Phase 2 resilience, but a disconnect cannot cause SQL execution because none exists.

## 9. Safety gates

Future restore execution requires all four independently verified evidences at runtime:

1. isolated disposable MySQL/MariaDB instance;
2. dedicated restore credential, distinct from ordinary/production credentials;
3. privileges constrained to the approved disposable target database;
4. a passing live restore integration suite for the exact supported dump format.

Phase 3 has no attestation provider for these facts. `RestoreSafetyGate` therefore reports each as `not_verified` and returns closed. No configuration flag can override this decision.

## 10. Shared database-operation lock contract

Phase 3 documents a future `DatabaseOperationLock` contract: any operation that can mutate a database must acquire the same exclusive, host-local lock before connecting, and release it after terminal verification. The future restore executor must integrate with the Phase 2 backup lock through a reviewed migration.

This phase does not implement or wire that shared lock and does not modify `BackupExecutionGuard`, `BackupJobRunner`, or backup concurrency behavior. Preflight performs file reads only.

## 11. UI, authentication, and file management

The shared layout/navigation remains unchanged. History adds quick origin/metadata status and a preflight link; it does not claim checksum verification. The Restore page lets an authenticated user select a catalog artifact, enter a target name, create a preflight job, and view findings and `queued`, `running`, `blocked`, `failed`, or `interrupted` state.

All mutating HTTP requests require existing authentication and CSRF validation. Status/preflight access requires session ownership. Responses expose no absolute paths, credentials, SQL text, PHP exception details, or authentication/session secrets.

Download security remains owned by Phase 1. Phase 3 does not delete, rename, upload, overwrite, or otherwise modify backup artifacts.

## 12. Failure and logging policy

Expected validation failures are persisted as stable finding codes with safe Thai/English summaries. Technical context is written through the existing redacting logger without SQL content or credentials. A parser or preflight exception results in `failed`; a closed safety gate results in `blocked`. Neither can result in `succeeded`.

No temporary restored database, partial SQL execution, or success-looking backup file can be produced because Phase 3 has no SQL execution path.

## 13. Change-based test matrix

| Area | Focused evidence | Data source |
|---|---|---|
| Provenance | exact Phase 2 succeeded match, failed/missing/mismatched origin rejection, no backfill | temporary JSONL jobs |
| Catalog | no SQL hashing/read, truthful quick states, containment/pair checks | temporary files |
| Full artifact validation | real SHA-256/bytes/marker/manifest/restore-order checks every preflight | small SQL fixtures |
| SQL parser | supported current dump envelope; reject ambiguous scope, qualified refs, dynamic SQL, DEFINER, dangerous/unknown syntax | hand-written fixtures |
| Target policy | conservative identifier, system/source/`jhcisdb` rejection | pure fixtures |
| Jobs and gates | queued/running to blocked or failed only; duplicate protection; no success state | temporary job/lock files |
| API/UI | auth, CSRF, ownership, safe response, honest labels, no execute control | isolated sessions/rendered views |

No live database connection is permitted. Phase 1/2 tests are repeated only where a shared route, layout, history model, or storage boundary changed.

## 14. NOT VERIFIED and residual risks

- Live restore integration, target creation/existence/emptiness, dedicated-user privilege scope, server isolation, and post-restore verification are `NOT VERIFIED`.
- The conservative parser can reject safe SQL constructs that fall outside the recognized dump subset.
- File contents can change between catalog display and preflight; preflight reopens and fully validates to close that gap.
- File contents could change after preflight. A future executor must validate again under a shared operation lock immediately before execution.
- Local `flock` behavior on network/shared filesystems is outside scope.

## 15. Rollback

Rollback removes only the Phase 3 restore namespace, endpoint, script/view additions, history annotations, and restore-job storage configuration. It does not alter databases, backup artifacts, Phase 2 job history, Git history, services, or Laragon configuration.
