# Existing-target read-only Import acceptance

Cleanup note (2026-10-19): the historical one-off reset/interactive wrapper and unrelated prototype/deployment files were retired. Their names/results below remain historical evidence, not executable instructions. The read-only inspector, lossless comparison helper, active effective-schema comparison and original private evidence/job/artifact remain retained. No live verification or state rewrite was performed during cleanup.

## UI finalization (presentation-only, no new database inspection)

The website now presents native execution, schema verification, live-source value verification, SQL-snapshot value verification and pending/failed/partial verification separately. Existing failed jobs stay failed; bound separate acceptance evidence may explain later schema/live-source results without mutating execution history. Evidence must match exact job ID, source, target and artifact path/bytes/SHA-256. Unsupported/malformed/unrelated evidence cannot upgrade status. The current workflow always keeps snapshot fidelity NOT VERIFIED; current-source PASS cannot become Fully verified.

ImportResultPresentation is a read-only UI adapter, not a new executor or reconciliation transition. It reads at most 1 MiB per review file from private runtime storage, performs no SQL or whole-dump hash on status polling, and cannot unlock execution. UI shows the review timestamp so earlier inspection is not mistaken for a fresh source check. Internal exception codes/events are replaced by short curated explanations; missing credentials never trigger Reset/provisioning. See LOCAL_USER_GUIDE.md. All live findings below are reused evidence, not new comparison runs.

## Final result: PARTIAL — current schema/data proven; historical snapshot values NOT VERIFIED

Completed read-only live inspection **2026-10-19 09:51:02 +07:00**, existing target only. Evidence: `storage/restore-jobs/local-import/b2197f1be82de698bd6678a09e1507c7.acceptance-20261019-025102-a0534352.json`. Actual evidence file SHA-256 **9181636186ae9641e85df07f429719cadc6cea8935883613d8b212dc89d742e2**. Effective metadata digest `39ae2c5d8f166e03a4d80a57e7f8ebcbf4b057ef11a9558b4832ab8103c133fc` is supplementary; equality decisions used exact metadata and row bytes, not digest alone.

All **nine existing tables'** effective schema agrees. All **eight** DDL differences are fully explained by 28 individual explicit CHARACTER SET lines with identical effective metadata; the ninth definition is byte-identical. The table matrix below records every differing table. No defaults/types/nullability/generated expressions/index/constraint/FK/CHECK/partition/table-option mismatch was ignored.

Exact current-source/target data comparison: **9/9 tables, 63/63 rows PASS**, preserving duplicate multiplicities, SQL NULL vs empty, text/binary bytes, exact numeric/date/time representation and actual ENUM text/ordinal. Per-table rows: dose_change_audit 4; flag_reconcile_runs 4; flag_reconcile_settings 1; queue 50; cleanup_security 1; facility_settings 1; herbal_compress_history 1; network_access_settings 1; visit_flag_audit 0. Raw data and passwords were not emitted. Both fixed-scope inspection connections used the interactively authenticated administrator in server-enforced READ ONLY repeatable-read transactions; actual Import execution remained the previous dedicated-account operation. Exact dedicated grants were attested unchanged. Root was not used to re-import or write source/target data.

Database defaults are **not identical**: source utf8mb4_general_ci, target utf8mb4_0900_ai_ci (both utf8mb4). Existing table/column collation and defaults agree; the dump intentionally excludes CREATE DATABASE. No ALTER was performed, and full database-default/object equivalence is not claimed.

The verified original SQL is a trustworthy snapshot artifact, but its values have **not been independently decoded and compared**. Its native INSERTs use positional values (8 statements, zero explicit column lists); the archived parser requires explicit column lists. No supported independent comparison is currently available without further parser work or replay, neither of which was introduced. Present-source/target equality is not a substitute for historical snapshot-value proof. Consequently overall acceptance remains PARTIAL, with separate JSON evidence and the original job still failed. No manual JSON/state overwrite.

Actual HTTP readiness: Dashboard/Export/Import/History/Settings 200; forged-header non-loopback 403; private source/tools/SQL URLs 403; History and authorized Download 200/200. Downloaded 20,601 bytes with original SHA-256 `0fafc77e5bcff9d73112fab6033c5b388a7c6d58a69449007b0d039dbdbfd4bd`. Export 4/4 live and 39/39 focused are reused evidence; no new Export or Import execution. Fresh changed-component tests **8/8**, PHP syntax **7/7**, PowerShell parse zero errors; no full regression/stress/unrelated suite.

No new target/account, GRANT changes, backup deletion, service/config/deployment change, protected/unauthorized database access or Git commit/push/tag/release. The only persistent database mutation in this continuation was the one explicitly approved existing-account password reset.

## 2026-10-19 continuation after explicit reset approval

The human explicitly approved ALTER USER for the one existing account only, using interactive administrator authentication, followed by verification against the existing target. Reset completed at **09:38:12 +07:00**. Private receipt `storage/restore-integration-ownership/local-import/b2197f1be82de698bd6678a09e1507c7.password-reset-20261019.json` verifies server UUID/version/port, unchanged exact grants, and successful dedicated-account authentication. The new generated password went to Windows Clipboard only. No database, grants, source/target rows or schema were changed; no Import/Export was rerun. Reset is not repeated when this receipt exists.

The read-only inspector now receives an interactively entered administrator credential only over bounded stdin from `tools/interactive-existing-import-acceptance.ps1`; it does not retrieve credentials from source, logs, browser sessions or Git history. The inspection connections read only autoprintdb and the exact owned target in server-enforced read-only transactions, and separately attest the dedicated import account's unchanged exact grants. This administrator inspection is not the website's routine Import credential; actual Import continues using its dedicated account only. The original reset verified dedicated-account login, but its transient Clipboard credential was subsequently overwritten; no further reset was attempted. No passwords are persisted in receipts/evidence.

A real verification attempt failed with MySQL **1054**, stage `metadata_0_partitions`; the inspector incorrectly selected ENGINE from INFORMATION_SCHEMA.PARTITIONS. ENGINE is already obtained from TABLES. Removed this nonexistent PARTITIONS column according to the [MySQL 8.4 PARTITIONS column reference](https://dev.mysql.com/doc/refman/8.4/en/information-schema-partitions-table.html). The focused SQL-column fixture first reproduced the missing-column failure, then passed after the fix. Failure evidence remains preserved, not overwritten. Subsequent verification requires the interactive continuation prompt, not another reset.

The active comparer was minimally fixed through `app/Import/EffectiveSchemaComparison.php`: effective metadata must be equal; only the exact redundant explicit CHARACTER SET line difference with matching effective charset/collation may pass. All other text, defaults, types, indexes/constraints and table-option differences fail. Original failed job remains immutable in practice; no historical state was rewritten. The separate inspector shares this helper, avoiding divergent comparison logic.

Focused fresh checks: comparer/CLI-boundary **3/3**, read-only helper/partition/ENUM fixture **5/5**, PHP syntax **7/7**, PowerShell parse zero errors. ENUM/SET comparison retains both exact text bytes and UNSIGNED ordinal/bitmask bytes, including NULL and empty/error index distinctions; unknown/FLOAT/DOUBLE/JSON/spatial types remain ineligible. No old Export or Import execution tests rerun. Actual Apache routes Dashboard/Export/Import/History/Settings returned **200** without login; actual non-loopback request with forged forwarding headers returned **403**; app/tools/SQL direct URLs returned **403**. Shared services/configuration were unchanged.

### Actual effective-schema evidence after query correction

Read-only report `b2197f1be82de698bd6678a09e1507c7.acceptance-failed-20261019-024717-97f1fb2a.json` records equal full collected effective metadata and explains **all eight** differing table definitions. Every changed line is solely the redundant explicit CHARACTER SET utf8mb4 with matching effective utf8mb4_unicode_ci; no other character differs on an accepted line. All other DDL lines, including table options/index/constraint declarations, match. Table engines, column properties, defaults/nullability, generated expressions, indexes, FK/actions, CHECK enforcement and partition metadata match. The ninth table's DDL is byte-identical.

| Table | Proven explicit-charset line differences |
|---|---:|
| autoprint_dose_change_audit | 6 |
| autoprint_flag_reconcile_runs | 1 |
| autoprint_flag_reconcile_settings | 1 |
| autoprint_queue | 6 |
| cleanup_security | 1 |
| facility_settings | 3 |
| herbal_compress_history | 9 |
| network_access_settings | 1 |
| visit_flag_audit | 0 |

This is **28 individually evidenced column lines**, not blind normalization. The report also established exact current source/target row-value equality for the first three tables (4/4/1 rows); that intermediate tool stopped on ENUM before comparing the remaining six. That failure was retained, then ENUM/SET text-plus-ordinal support was added with an observed failing regression followed by passing tests. The final complete current-value evidence is the 09:51:02 report above, not an inference from this intermediate run.

The SQL artifact itself is a verified backup snapshot. The exact remaining value-proof gap is **no supported independent decoding/comparison of that native SQL snapshot's row values**, not absence of a trustworthy backup file. No historical canonical row-value evidence was recorded in its manifest (tables=9, rows=null). A current-source/target exact comparison must be labeled as such, never promoted to historical snapshot equivalence. Do not introduce a new SQL engine or re-import merely to manufacture that missing evidence.

New/changed files in this continuation: `app/Import/EffectiveSchemaComparison.php`, `app/Import/MysqliImportDatabase.php`, `tools/verification/LocalImportAcceptance.php`, `tools/verify-existing-import.php`, `tools/reset-existing-import-password.php`, `tools/interactive-existing-import-acceptance.ps1`, `tests/ImportSchemaComparisonTest.php`, `tests/LocalImportAcceptanceTest.php`, this report, README.md and TEST_BASELINE.md (11 files). Existing unrelated working-tree changes are retained, not attributed to this continuation.

The earlier credential-blocked observations below remain historical and are superseded by the successful reset receipt; they are not newly run results.

## Earlier outcome (historical): PARTIAL / live verification credential-blocked

Scope is the single previously imported target `local_import_20261018_b2197f1be82de698`, original job `b2197f1be82de698bd6678a09e1507c7`, source autoprintdb. No Import, database/account creation, grants, cleanup or source writes are permitted in this round.

The existing source reader authenticates and passes actual native identity/exact-grant validation. The dedicated target account rejects the current transient credential with MySQL error 1045. The user confirmed the import password was not retained. No password reset, root fallback, credential guessing or alternate-account inspection was attempted. Therefore none of the new complete DDL/data verification can be reported as a live pass. A user-supplied phpMyAdmin tree confirms the expected target and nine table names, but is not evidence of row-value/schema equality or the import account's credentials. Other listed databases remain out of scope and were not inspected.

## Historical evidence, not rerun or newly passed

The preceding authorized Import ran official mysql.exe once with exit 0. A prior read-only diagnosis found nine tables, 63 rows and matching collected metadata/counts. Eight raw SHOW CREATE TABLE definitions differed; one observed cause was an explicit target CHARACTER SET utf8mb4 on a column whose source rendering only showed COLLATE utf8mb4_unicode_ci. The remaining seven definitions were not fully explained. That example must not be generalized into a schema PASS.

## Read-only inspection tool and focused verification

The earlier version of `tools/verify-existing-import.php` used the existing session/target credentials. That path is superseded by the interactive read-only administrator inspection described above; no browser-session credential recovery is used in the current workflow. The tool remains fixed to the exact existing job/target, ownership journal and full artifact hash/provenance, holds the existing shared lock, and cannot execute an Import, provision resources or update any job.

The prepared inspection uses two server-enforced read-only, repeatable-read consistent snapshots with UTC time zone. INFORMATION_SCHEMA comparisons cover table engine/options/collation/comments, ordered column types/nullability/defaults/charset/collation/generated expressions, index properties, FK actions, CHECK enforcement, partitions and additional precision/comment/SRS metadata. Every raw DDL line must remain identical except an exactly identified explicit-vs-implicit CHARACTER SET declaration whose full effective column metadata is equal. Unexplained definitions fail; no arbitrary SQL normalization or rewriting. Database defaults are reported separately, because the accepted dump profile intentionally does not include CREATE DATABASE.

Data comparison uses unbuffered reads of HEX(CAST(column AS BINARY)), decodes exact bytes, preserves SQL NULL, length-delimits row values with string-only serialization, and compares sorted row byte arrays directly, including duplicate multiplicities. SHA-256 values are supplementary evidence, not the equality decision. Supported exact numeric/date/time/character/binary types are allowlisted. ENUM/SET additionally retain exact ordinal/bitmask bytes. FLOAT/DOUBLE/JSON/spatial and unknown types fail closed instead of lossy conversion. Source-target metadata must agree before comparing values.

Bounds: 128 MiB PHP memory limit, at most 10,000 rows and 8 MiB serialized bytes per side/table, 2 MiB HEX-output row precheck, 120-second CLI limit and 30-second SELECT limit. Excess limits produce NOT VERIFIED, not partial success. No table data or credentials are printed. The helper's NULL/empty/binary/UTF-8/precision/time/duplicate and overflow tests use synthetic values only.

## Backup snapshot and reconciliation limitations

No independent historical source value digest or decoded SQL-backup row multiset was recorded. An unchanged verified artifact plus the prior single native Import is supporting provenance, not an independently decoded backup-value comparison. Future current-source/target equality can establish effective equivalence at the inspection snapshots. A mismatch alone cannot determine whether the source drifted after backup or whether Import was wrong; keep PARTIAL and record the ambiguity rather than mislabeling source drift.

The original job remains failed. The application currently has no purpose-built validated failed-to-succeeded reconciliation transition; the generic persistence update method is not such a mechanism. Do not directly edit JSON or call that method to overwrite failure. Even after a future proven equality inspection, retain original execution history and record a separate acceptance result unless a reconciliation feature is explicitly implemented/tested.

The preceding entry predated reset approval. Its password-reset prohibition is superseded only by the explicit single-account approval and actual reset receipt above. Grants/data remain unchanged; further reset is not automatic. No new target or re-import is needed.
