# Change-Based Test Baseline

## 2026-10-19 — Account-based discovery and Export selection

User clarified that the four-schema allowlist was controlled-live-test scope, not permanent product policy. Current catalog follows the saved account's visible schemas; protected jhcisdb (case-insensitive) is excluded in the SQL predicate and rejected again on any unexpected result. Only schema names are discovered; no protected table/object inspection. System schemas information_schema/performance_schema/mysql/sys display separately, are not Export/Import eligible and never enter Select All. Windows-unsafe artifact names are also displayed separately as unsupported; ordinary Unicode/spaces/leading-hyphen names are supported. No live discovery was run under this task's fake-only test policy.

Selection is drawn from the fresh account catalog, bound to the session-owned job, compared with persisted names before execution and rechecked against fresh account visibility. Service checks exact selected schema visibility before table metadata; engine/command require selected scope and use native -- argument termination. Old queued jobs without selection evidence cannot resume automatically; history/artifacts unchanged. Import source eligibility follows the new policy, with Unicode-safe contained artifact names; hash/provenance/identity/new-target guards unchanged.

### Fresh focused verification

**29 PASS / 0 FAIL / 0 SKIP**, not the previous79-suite rerun. All PHP invocations used `-d disable_functions=mysqli_init`; fake mysqli/catalog/dump adapters and isolated temporary storage only, no native client execution:

| Selected checks | PASS |
|---|---:|
| DatabaseDiscoveryTest: dynamic names, separated system/unsupported names, Select All, forged selection/job rejection, scoped argv, saved-profile catalog projection/UI, vanished source rejection before table access, Unicode Import artifacts | 8 |
| ManualBackupSourcePolicyTest: generic eligibility, protected SQL/result rejection, forged protected selection/storage/operation guards | 6 |
| Phase2JobApiAndUiTest: affected selection/API/ownership/presentation contract | 5 |
| LocalImportArtifactTest: affected source/path/provenance/hash checks | 3 |
| Existing fake service publication, failure, unsupported engine/view and sequential runner artifact checks (individual name filters only) | 4 |
| Existing native command construction/protected source checks (construction only, no --help/--version/process) | 2 |
| Existing Export view selection/search/confirmation rendering check (individual name filter) | 1 |

TDD initial new discovery tests0/4, then green; new fixture runner initially failed because its old fake catalog/selected scope covered only one of its two fixture names; corrected fixture scope/catalog, then passed. Changed PHP syntax17/17 PASS; git diff --check PASS. No UI JavaScript change. Independent source-only targeted review found no Important/Critical issue; no reviewer DB/HTTP/test actions. Previous native Export/Import acceptance evidence reused without repeating execution or upgrading snapshot fidelity.

Files changed in this task: app/Database/{AuthorizedSourcePolicy,DatabaseRepository}; app/Backup/{BackupJobAccess,BackupService,MysqliBackupOperation,MysqldumpEngine,MysqldumpCommand}; app/Http/Controller/BackupController; app/Import/ImportArtifactVerifier; public/{index,job}; views/pages/backup; tests/{DatabaseDiscoveryTest (new),ManualBackupSourcePolicyTest,MysqldumpBackupTest,MysqldumpServiceTest,LocalImportArtifactTest}; README.md, AGENTS.md, docs/{ARCHITECTURE,LOCAL_USER_GUIDE,LOCAL_MYSQL_MANAGER}, TEST_BASELINE.md. No source files removed.

Limitations/NOT VERIFIED: live dynamic catalog/browser rendering/native export of newly permitted names intentionally not tested. Visibility does not prove all required table privileges or supported object types; native privilege and InnoDB/profile checks still apply. Windows-unsafe names remain ineligible. System schemas are display-only (no optional manual-system Export). Same-runtime/admin compromise and concurrent external DDL limits remain unchanged.

Safety for this discovery task: no MySQL connection/query, database/account/GRANT mutation, Export/Import execution, native executable invocation, original backup/history deletion, Apache/MySQL restart, commit/push/tag/release. Own temporary fixture files only. Existing accidental target remains OWNERSHIP UNPROVEN and untouched.

## 2026-10-19 — Live Settings check before the superseding discovery request

Actual existing Laragon browser at localhost:8081/backUP-phpmyadmin-fordev/index.php?page=settings showed Connected with the user's saved profile (06:28:00Z). Test Connection visibly returned success (06:28:10Z); Save Settings without changing fields/password returned success and Connected (06:28:22Z). No credentials read out or changed, no Export/Import controls pressed. This occurred before source discovery changes, so it is not live verification of the new catalog. Fresh Session, disconnected-path and Export browser checks were not completed when the user superseded that task; remain NOT VERIFIED.

Existing incident evidence inspected: application app.log and the already recorded failed test output did not establish an exact accidental target name. No database/directory discovery, ownership inference, cleanup or DROP was attempted. Status stays **OWNERSHIP UNPROVEN**. Older incident details below remain intact.

## 2026-10-19 — Generic local MySQL profile / empty password / saved-profile Import

Current implementation supersedes the dedicated-reader policy in older entries; those entries remain historical evidence, not new PASS results. No account was recreated/reset/GRANTed. Active PHP/config/JS/views/tests and maintained README/AGENTS/Architecture/User Guide/Local Manager docs contain no dependency on the former special username.

Settings defaults are local127.0.0.1:3306/root/empty, **not used until explicit Test/Save**. No profile, saved-empty, preserved nonempty, explicit Replace/Clear are distinct. Test never persists; Save probes first, captures native UUID/CURRENT_USER and requires confirmation for endpoint/UUID/account changes. Empty password is valid, not a disconnected sentinel. Unsaved profiles have no fabricated last-check timestamp. Private LocalAppData storage/NTFS ACL/atomic CAS remain, v1 readable/v2 written, plaintext-at-rest limitation unchanged. Active roles remain unsupported; no remote/port3333.

Export retains the native engine/sequential locks/publication/download; connection credentials include full saved UUID/account all the way through the adapter. Native client option files now carry the generic validated username/endpoint and escaped password, including empty; passwords never enter argv or persisted jobs/manifests. Four-source allowlist unchanged regardless of account privilege.

New Import jobs use mode=saved_profile and the same private configuration, no separate password field or new account. Preparing verifies file/provenance/hash and same native server binding, generates only the new target name. Execution confirms IMPORT, checks revision under shared lock, actual identity, conservative sufficient target grants (SELECT/INSERT/CREATE/REFERENCES, global or exact escaped target scope, partial_revokes=0), target absence, then CREATE without IF NOT EXISTS and exact ownership journal before native import. Collision/journal failure stops; no automatic DROP/retry. Old jobs/history stay unchanged/read-only via HTTP. Schema/count comparison and independent historical review presentation retained; snapshot-value fidelity still NOT VERIFIED. SQL non-redirection relies on pinned trusted producer/options, source allowlist, no stored objects/cross-schema FK and immutable provenance/hash, not a general SQL parser or protection from same-Windows-account compromise.

### Actual focused results

Final guarded change-based run: **79 PASS / 0 FAIL / 0 SKIP**: AutomaticConnectionTest11, GenericConnectionTest6, MysqldumpBackupTest10, MysqldumpServiceTest8, LocalImportArtifactTest3, LocalImportTest6, LocalImportRunnerTest7, LocalImportUiTest2, LocalManagerPresentationTest5, Phase4UiTest8, RoutingAndViewTest4, CleanupDependencyTest3, Node presentation fixtures6. PHP tests run with `php -d disable_functions=mysqli_init tests/run.php <suite>`; the new adapter fixture refuses to run without that process-level no-live guard and validates the injected factory seam. Native clients invoked only for offline --version/--help; actual Export/Import clients in lifecycle tests use fake process adapters. Synthetic private NTFS artifacts removed only by exact owned paths. Changed PHP syntax35/35, JS syntax2/2 and git diff --check PASS.

RED/GREEN: new empty-password/profile/grant tests initially0/3; timestamp regression5/6 then6/6; initial fake adapter test failed as described in the incident below. Updated obsolete credential-form/source-contract assertions follow the new intended UI. No full regression. Earlier live Export4/4, focused39/39 and historical Import/schema/live-source-value evidence reused, not rerun or upgraded.

Independent source-only review found one P1: Export endpoint discarded saved UUID/account while rebuilding credentials. Fixed by preserving the complete validated profile; reviewer rechecked and found no remaining Important/Critical issue in the targeted source. No independent live inspection occurred.

### Operational incident — NOT an offline-only session

During the **first new Import adapter fixture**, a fifth injected factory argument was passed before the constructor declared that parameter. PHP ignored the extra argument and used the real ConnectionFactory. The executed path connected to local MySQL using the intended synthetic root/empty fixture, checked identity/grants and absence, **created one random empty generated target database**, then checked the second connection identity. No SQL artifact was imported, no source tables/schema/rows or protected database were queried, and no account/GRANT mutation occurred on that path. This is inferred from the executed path returning successfully and is not independently confirmed by live audit.

The generated exact target name existed only in the failed test process and was not captured in output/persistent ownership evidence. **Exact target identity/current emptiness remain NOT VERIFIED.** It was not searched for, dropped, moved or otherwise changed afterwards. There is no safe ownership-based cleanup authorization/evidence; never infer ownership from a prefix. This was an unintended persistent mutation outside the planned fixture-only test and was disclosed to the user during work. No claim that this entire work session was offline or mutation-free is made.

Remediation: explicit injected factory is now declared and used; fixture verifies its presence and refuses execution unless mysqli_init is disabled. Subsequent selected tests were run with that guard. No further intentional live calls, real Export/Import, cleanup, credential enrollment, account/grant changes, service/restart/config mutation, commit/push/tag/release.

### Acceptance and remaining gates

Offline implementation/focused checks PASS. **LIVE CONNECTION NOT VERIFIED**: no application profile was saved with real credentials, no Settings/Apache/fresh-browser live acceptance or real Export/Import rerun. Browser rendering remains NOT VERIFIED (isolated PHP route and JS DOM fixtures tested). Overall task **PARTIAL**, not live PASS. The unintended new target requires separately authorized read-only identification/ownership investigation before any possible cleanup; do not guess its name or DROP. Original historical Import acceptance remains PARTIAL due to unverified SQL-snapshot values.

## 2026-10-19 — Automatic saved MySQL connection / Settings management

Scope: Settings-only reader form, private persistent configuration, automatic actual verification boundary, queued-context invalidation and affected UI/API access. No Export/Import execution or unrelated full regression.

Implementation: ConnectionSettingsStore + WindowsPrivateDirectory; plaintext `%LOCALAPPDATA%/BackupPhpMyAdmin/ConnectionSettings/<project-hash>/settings.json` outside source/web root, owner/runtime SID plus SYSTEM NTFS ACL, reparse/path checks, bounded JSON, exclusive lock/revision CAS, create-only temp + fsync + atomic rename. No encryption claim, no config/local.php modification, no password in Session/HTML/API/jobs/manifests. Blank Save preserves; explicit clear disconnects. Import target password remains separate per-request. Actual page checks occur only when configured on Dashboard/Export/Import/Settings; polling/assets/History do not query MySQL. Save uses existing shared operation lock; both database adapters assert saved revision under that lock before their first connection. Exact endpoint/reader/server pins and four-source policy unchanged.

Fresh focused results: **54 PASS / 0 FAIL / 0 SKIP** — PHP AutomaticConnectionTest11, LocalManagerPresentationTest5, Phase4UiTest8, RoutingAndViewTest4, Phase2JobApiAndUiTest5, LocalImportUiTest2, CleanupDependencyTest3, SecurityFoundationTest10; Node presentation fixtures6. PHP syntax **19/19 PASS**, both changed JavaScript files syntax PASS, git diff --check PASS (Git reports LF/CRLF normalization warnings, not whitespace failures).

Evidence: persistence across a fresh Session with fake MySQL probe; real status/failure/no stale lists; unsaved Test; blank/clear; wrong host/port3333/root rejection before probing; unauthorized catalog rejection; no secret disclosure; shared-lock Save exclusion; cross-Session revision invalidation; both native adapters stop before SQL on stale revision; tampered private SID/server/role/JSON rejection; atomic replacement failure preserves previous record. One synthetic-only real Windows NTFS test created its own previously absent LocalAppData hash leaf, verified restricted ACL/read/atomic overwrite, and removed only its own two fixed files and leaf. Empty restricted parent application directories may remain. No real credential enrolled.

RED evidence: initial missing implementation failed; source-root alias rejection exposed canonical-path issue and was fixed; added Host regression initially failed 10/11, then passed 11/11 after production entrypoint Host validation. Independent read-only code review identified DNS-rebinding exposure and stale cross-Session polling hints; fixed exact permitted local Hosts on all four entrypoints plus metadata-only queued revision synchronization. Reviewer rechecked these deltas and found no Important/Critical defect; remaining link-copy issue corrected. Queued Export Resume no longer disappears after a 30-second hint expiry; invalid Import context hides execution controls.

CleanupDependencyTest executed nine isolated source-copy route/API cases with temporary storage and disabled mysqli_init, not actual Apache browser verification. Earlier Export4/4, focused39/39 and historical Import evidence are **reused, not rerun**; historical SQL-snapshot fidelity remains NOT VERIFIED / acceptance PARTIAL. No existing job was reconciled or upgraded.

**NOT VERIFIED:** real saved password + Apache Windows identity/LocalAppData/ACL path; actual live MySQL auto-connect after browser restart; real invalid credentials/server outage; real cross-browser HTTP polling; browser rendering with the new form. No live Export/Import/download acceptance rerun. Offline implementation PASS; overall live end-to-end acceptance PARTIAL pending entry of the existing reader password in Settings and separately scoped live verification.

Safety: no MySQL connection or schema access (including protected/unauthorized databases), no account/GRANT/password reset, no Export/Import, no existing SQL/manifest/job/history/config rewrite or deletion. No Windows account/service/policy/Laragon configuration changes; only the owned synthetic private test directories/ACL described above. No commit/push/tag/release.

## 2026-10-19 — Final architecture documentation and authorized Git synchronization

This round documents/publishes the completed local working tree; it does not reimplement or re-test the unchanged Export/Import engines. Added root AGENTS.md and docs/ARCHITECTURE.md (20 implemented-architecture sections,3 Mermaid diagrams), expanded README installation/configuration/operation/maintenance and linked retained guides. Documents explicitly distinguish native completion, normal schema/count comparison, separate historical exact live-source values and NOT VERIFIED SQL-snapshot fidelity. Manifestless legacy download compatibility, plaintext private sessions/temporary option files, pinned local identity and concurrent DDL/process-crash limits are not hidden.

Repository hygiene: .gitignore excludes all storage children except storage/.htaccess, SQL/paired manifests, job/session/log/temp/private-key/local-config and binary/build files. Removed **14 formerly tracked SQL/manifest files from the index only**, not disk; existing local user data is preserved. Old Git commits still contain historical artifacts; no history rewrite or claim of historical sanitization. Removed obsolete architectures remain absent. Current staged source tree contains107 files before this evidence update; no app runtime dependency was added.

Fresh checks (not old-suite reruns): inspected **63 retained PHP files**, autoloaded **47 declarations**, resolved **41 explicitly referenced application classes**, verified **13 documented method references** without bootstrap/DB. Checked Markdown links in maintained docs and3 Mermaid source blocks (not a claim of visual Mermaid rendering). Reviewed actual staged paths/diff and scanned all72 added/modified blobs with offline credential/private-key/token/URI/binary/path checks. Four initially flagged test literals were individually confirmed synthetic sentinels; no real credential finding and no secret values printed. This bounded pattern/manual review is not a guarantee against every possible unknown secret. No dedicated gitleaks/trufflehog tool is installed.

Only active access-rule change this round: deny new AGENTS.md via root FilesMatch. Actual Apache GET **AGENTS.md403**, **docs/ARCHITECTURE.md403**, rejected before PHP/bootstrap/session/SQL. No original application-page smoke or live clients were rerun. Two pre-existing newly staged PHP files had trailing blank EOF removed; PHP syntax **2/2 PASS**; no behavioral change. Staged and working-tree whitespace checks passed after those formatting fixes. Final maintained-document link check **18/18 valid**;3 Mermaid source blocks included, visual rendering not separately tested.

Protected-data check: **66/66 files unchanged** by exact inventory, SHA-256, size and LastWriteTimeUtc across this task, including all local storage files and config/local.php. Independent read-only architecture/index review found no Critical/Important/Minor issue; it did not claim live behavior or remote publication. Review branch/remote identity matched the explicit task; safe fetch found no unexpected changes and main was an ancestor. Real synchronization results are reported only after actual pushes.

Reused unchanged evidence only: Export live4/4, focused39/39, mysql.exe exit0, effective schema9/9, current-source exact values9/9, finalization12/12 and cleanup50PASS/1SKIP. Historical Import job remains failed; overall acceptance PARTIAL and SQL-snapshot data NOT VERIFIED. No full regression, MySQL connection, source/target mutation, credentials/accounts/grants change, Backup/Import/Restore execution or Laragon/Windows/service action. Publication status/commit IDs are reported from actual Git results, not preclaimed here.

---

## 2026-10-19 — Authorized project cleanup (latest)

**Offline cleanup completed; no new Live Export/Import acceptance claimed.** Current working-tree graph traced from public/index.php, job.php, import-job.php, download.php through bootstrap/router/controllers/views/adapters/stores/locks. Deleted **960 exact obsolete files / 257,105,758 bytes**: unreachable custom Restore/Integration/typed SQL/privilege/receipt/secret framework, DPAPI/.NET sources and build outputs, signing/dedicated Apache tooling/templates/packages, dead HTTP endpoints/views/scripts, one-off reset wrappers and abandoned tests/design/runbooks. Removed related script/rewrite registrations, unused direct BackupController backup method, dead index candidate collection and old login/config remnants. Official Export/Import SQL behavior and current locks/integrity/download implementations were not rewritten.

Retained **48 app files**, including Restore/BackupProvenanceIndex (actual Import/History origin dependency) and a metadata-only RestoreArtifactValidator for old History records; removed its unused custom Restore preflight. Retained current Import verification tools/helpers/evidence. Legacy runtime path names and existing configuration/storage remain compatible without migration. Current README/local guide/architecture document replace abandoned phase instructions. Historical TEST_BASELINE entries and acceptance report remain unchanged below; references to retired files in those entries are historical evidence, not current instructions.

Fresh change-based checks: **50 PASS / 0 FAIL / 1 SKIP, 51 selected**, not full regression: CleanupDependencyTest3, ImportSchemaComparisonTest3, SecurityFoundationTest10, retained current Phase4UiTest8, RoutingAndViewTest4, LocalImportArtifactTest3, LocalManagerPresentationTest5, LocalImportUiTest2, Phase2JobApiAndUiTest5, StorageAndDownloadTest7 PASS +1 SKIP. Skip: Windows isolated symlink creation unavailable; no symlink-escape PASS fabricated. Cleanup dependency test initially failed with obsolete graph still present; after deletion/reference pruning all three cleanup checks pass. Updated CLI-boundary test no longer invokes the retired reset tool; malformed JSON correctly expects the retained inspector's existing safe generic error. No change to production SQL/verification semantics.

Changed PHP syntax **14/14 PASS**; git diff --check PASS. No JavaScript change, prior presentation JS5/5 reused. Actual isolated child entrypoint smoke uses copied current source and temporary runtime root, disables mysqli_init: Dashboard/Export/Import/History/Settings **200**, unknown page **404**, wrong-method job API **405**, Import CSRF **419**, unauthorized Download **404**. All nine cases executed without MySQL or original runtime writes; not represented as fresh Apache HTTP tests. Previously recorded Apache results are reused. Autoload declarations and explicit application namespace references resolve; runtime/test scan found no references to deleted classes/files.

Before/after exact inventory + SHA-256 + size + LastWriteTimeUtc matched for **66 protected files**, including every original storage file and config/local.php; no new protected file or unexpected deletion. **960/960** deletion inventory entries absent; remaining reviewed application/source/tool/test/docs inventory **104 files** (excludes protected runtime/local config). Reparse checks performed before cleanup; no symlink/junction following. Original SQL, manifests, jobs, acceptance JSON, ownership, sessions/logs/locks/private files and databases/accounts preserved. No MySQL connection, services/Windows/Laragon server-configuration/ACL change, credential mutation, Backup/Restore execution, commit/push/tag/release or history rewrite. Application source routing/default templates were intentionally simplified as described above.

Reused acceptance only: Export live4/4, focused39/39, mysql.exe exit0, effective schema9/9, live-source values9/9 and finalization12/12. Existing failed Import job remains failed; separate acceptance remains PARTIAL and SQL-snapshot values NOT VERIFIED. Independent read-only cleanup review: no Critical/Important/Minor findings; no missing retained dependency. Reviewer did not claim live DB/Apache behavior or independently re-hash private files. **CLEANUP PASS** within offline scope. Detailed KEEP/DELETE/REVIEW reasoning, exact 960 deleted and 104 retained file paths, source graph and tracked Git diff --stat: docs/PROJECT_CLEANUP.md.

---

## 2026-10-19 — Local manager UI/status finalization (latest)

**Finalization implemented and focused UI checks passed. Overall historical Import snapshot fidelity remains PARTIAL / NOT VERIFIED.** This round performs no MySQL connection, database mutation, live Export/Import or repeated live schema/data comparison. Existing Laragon, engines, allowlist, locks, CSRF/session/ownership, storage and downloads remain unchanged.

Import presentation now separates native execution, schema, exact live-source data, SQL-snapshot data and pending/failed/partial verification. The read-only ImportResultPresentation adapter binds existing private review JSON to exact job/source/target/artifact path/bytes/SHA-256; rejects malformed/foreign/incomplete proof; never promotes current-source proof or row counts to snapshot fidelity. All current results keep snapshot_data=not_verified. Native succeeded jobs show completion, not Fully verified. Failed comparison jobs without a valid later review remain verification-failed; a valid later review may show partial verification without changing recorded_status=failed. Full DDL evidence is required before presenting schema verified. Existing evidence reads are bounded to 1 MiB/file, at most 100 candidate review files; no SQL file hashing on polling.

Observed actual stored-job mapping, read-only filesystem only: job `b2197f1be82de698bd6678a09e1507c7` presents **recorded_status failed / execution completed / schema verified / live_data verified / snapshot_data not_verified / verification partial**, reviewed_at 2026-10-19T02:51:02+00:00. This reuses the earlier report; it is not a fresh MySQL observation. Job/history/evidence were not rewritten. Wrong-session status cannot obtain these review details.

Export page explicitly states InnoDB base-table structure + rows in official mysqldump SQL format, excluding Views, Triggers, Stored procedures/functions, Events and Non-InnoDB engines; no full-database claim or engine change. Import/Export/Settings distinguish MySQL credentials from website login and explain missing credentials without automatic Reset/provision/GRANT. History distinguishes artifact metadata from Import verification. Existing navigation/design/components/data hooks/API URLs remain intact; raw event/verification JSON is no longer dumped into Import UI.

Independent focused review completed for the named finalization files. Its sole P2 (disappearing request rejection) was fixed and rechecked; final reviewer verdict: no further important findings in the narrow presentation scope. This is not an independent review of the unchanged engines or live data fidelity.

### Fresh change-based results

| Check | Actual result |
|---|---|
| Initial presentation TDD | **0/5 RED**, missing class/status facets |
| New LocalManagerPresentationTest | **5/5 PASS**, zero skipped: result scopes, immutable history, no snapshot upgrade, binding/completeness, bounded review selection, safe messages and affected views |
| Directly affected LocalImportUiTest | **2/2 PASS**, zero skipped: preserved forms/hooks/API protection and script wiring only |
| Node actual UI-script behavior in fixture DOM | **5/5 PASS**: independent facets + forced snapshot NOT VERIFIED, pending controls hidden, safe API/network errors, rejected execution guidance retained after status refresh with password field cleared; no automatic prepare/execute |
| Export credential error HTTP regression | **RED missing specific guidance -> GREEN HTTP 200** after POST rejection/redirect; wrong synthetic username is rejected by existing MysqldumpCommand::assertConnection before ConnectionFactory, no DB connection; synthetic password absent response |
| Changed PHP syntax | **8/8 PASS** |
| Production/test JS syntax | **2/2 PASS** |
| Git diff whitespace check | PASS for changed tracked files |

Fresh automated focused aggregate: **12/12 PASS** (PHP 7 + JS 5), zero skipped. Additional observed RED-to-GREEN boundaries: incomplete/unexplained DDL could initially display schema verified, now rejected; failed verification without a later review initially showed partial, now failed. Independent focused review found one P2: status refresh erased a curated pre-execution rejection message. Added actual-script regression failed before the fix, passed after preserving the request error across status reconciliation. Network fallback now distinguishes an unavailable response from proven execution failure and instructs checking status without retry. No Export engine, Import execution, completed live comparison, full regression/stress or unrelated tests rerun.

Actual Apache changed routes **Export/Import/History/Settings 200**. Import Browser rendering verified current session's existing awaiting_provisioning job: execution not started, schema/live values unverified, snapshot NOT VERIFIED, verification pending, execution controls hidden. Browser also verified updated Export scope and Settings credential copy with existing design. Node fixture exercises completed/reviewed-failure rendering; real historical reviewed job was mapped via actual persisted JSON through the presentation class, not falsely claimed as a browser-owned job. Current viewport rendering was checked; no claim of a fresh full desktop/tablet/mobile responsive matrix.

Actual changed API error paths: foreign-session existing job status **404**, curated message present, no job/review exposed; invalid-CSRF request **419**, curated reload guidance. No prepare or execute action passed authorization/validation in these checks; no new Import job or database was created. Existing Dashboard/loopback enforcement/secure Download/Artifact hashes and other unchanged security checks reuse their earlier evidence.

Reused, not rerun: Live Export **4/4**, Export focused **39/39**, actual mysql.exe Import exit **0**, effective schema **9/9**, exact current-source/target value comparison **9/9 / 63 rows**, five-page HTTP evidence and protected SQL/download evidence. Independent SQL-snapshot value comparison remains **NOT VERIFIED**; full database objects/default collation equivalence is not claimed. No manual failed-to-succeeded transition, credentials in reports/JSON, credential reset/account/GRANT operation, protected/unauthorized database access, services/config/deployment change, backup/history deletion or Git commit/push/tag/release.

Files in this finalization (14): `app/Import/ImportResultPresentation.php`; `public/import-job.php`; `public/index.php`; `public/assets/local-import.js`; `views/pages/import.php`; `views/pages/backup.php`; `views/pages/history.php`; `views/pages/settings.php`; `tests/LocalManagerPresentationTest.php`; `tests/js/local-import-presentation.test.js`; `README.md`; `docs/LOCAL_USER_GUIDE.md`; `docs/LOCAL_IMPORT_ACCEPTANCE_2026-10-19.md`; `TEST_BASELINE.md`. Earlier unrelated working-tree changes are preserved, not attributed here. Active runtime/retired historical paths are documented in the short local user guide; no historical modules or artifacts were removed merely for tidiness.

---

## 2026-10-19 — Final-local-manager continuation and authorized credential reset

User explicitly authorized one ALTER USER password reset for `local_import_bd6678a09e1507c7@localhost`, preserving exact grants/target/data, followed by existing-target acceptance only. Interactive administrator prompt was used, no password arguments/environment/source/logs; no credential search in browser sessions or Git. Reset succeeded at **09:38:12 +07:00**. Private reset receipt verifies MySQL 8.4.3 / port 3306 / UUID `198f474e-899b-11f1-903d-f0761cf4041a`, unchanged exact SELECT/INSERT/CREATE/REFERENCES target grants and successful generated-account login. New password delivered to Windows Clipboard only. No new target/account, grants, SQL Import/Export, source writes or cleanup. A receipt guard prevents repeat reset.

Actual read-only verification reached `metadata_0_partitions` then failed with **1054**: the inspection SELECT included nonexistent ENGINE in INFORMATION_SCHEMA.PARTITIONS. ENGINE remains checked via TABLES. Corrected the query to MySQL 8.4's documented columns. New in-memory SQLite SQL-column fixture (not MySQL; no external database) reproduced **RED no such column ENGINE -> GREEN**. This is a fixture check, not a claim of a completed MySQL schema/data inspection. Safe failure evidence `b2197f1be82de698bd6678a09e1507c7.acceptance-failed-20261019-024100-db6a1aa4.json` retained under private runtime job storage.

Subsequent actual read-only evidence at **09:47:17 +07:00**: `b2197f1be82de698bd6678a09e1507c7.acceptance-failed-20261019-024717-97f1fb2a.json` proves **effective metadata equality for all nine tables**, and explains **all eight differing SHOW CREATE TABLEs**, 28 column lines total, solely explicit CHARACTER SET utf8mb4 with equal effective utf8mb4_unicode_ci. Differences by table: dose_change_audit 6, flag_reconcile_runs 1, flag_reconcile_settings 1, queue 6, cleanup_security 1, facility_settings 3, herbal_compress_history 9, network_access_settings 1; visit_flag_audit raw DDL identical. All other DDL characters/metadata/index/constraint/default/options agree. Exact current source/target row-multiset comparison completed for the first three tables (4/4/1 rows), then stopped fail-closed on ENUM. This is partial value verification, not a data PASS for the remaining tables.

ENUM/SET handling was added to the read-only comparison after an observed regression **RED undefined valueExpressions -> GREEN**. Compare text bytes **and** exact UNSIGNED ordinal/bitmask bytes, preserving SQL NULL, empty member vs error index 0, 64-bit mask precision and duplicates; it does not execute or rewrite SQL artifacts. FLOAT/DOUBLE/JSON/spatial/unknown conversions remain unsupported. The inspector now uses the interactively authenticated administrator for both fixed-schema READ ONLY inspection connections and attests the dedicated account's unchanged exact grants separately; the application's actual Import runner still requires its dedicated account. This avoids reset repetition when a transient Clipboard credential was overwritten. No source/log/browser-session/Git password recovery or root Import fallback was used.

**Completed corrected live inspection: 2026-10-19 09:51:02 +07:00.** Separate evidence `storage/restore-jobs/local-import/b2197f1be82de698bd6678a09e1507c7.acceptance-20261019-025102-a0534352.json`, actual file SHA-256 **9181636186ae9641e85df07f429719cadc6cea8935883613d8b212dc89d742e2**. Verified native MySQL 8.4.3/3306/expected UUID, interactively authenticated root@localhost roles NONE/partial_revokes 0, exact dedicated Import grants unchanged. Both connections were server-enforced READ ONLY repeatable-read snapshots; root was used solely for this fixed-scope verification, not Import execution. All 9 tables' effective metadata and every DDL difference are proven equivalent at the existing-table level. Exact sorted byte row multisets, not hashes/counts alone, matched **9/9 tables, 63/63 rows**: 4,4,1,50,1,1,1,1,0 in table order shown in the earlier live result. Includes actual ENUM text plus ordinal; no data values or credentials emitted. Post-inspection schema snapshots matched pre-inspection snapshots in measured dimensions.

**Current source/target exact equivalence PASS; overall final acceptance PARTIAL.** Historical SQL snapshot row-value equivalence remains NOT VERIFIED: the artifact is verified, but no supported independent native-dump value decoder/historical row-value evidence is available. Direct offline file inspection found 8 native INSERT statements, none with explicit column lists; the archived InsertRowsParser requires explicit column lists. That inspection did not execute SQL or claim a validated general parser. Do not reactivate/build a custom Import engine or re-import to invent proof. Database defaults also differ: source utf8mb4_general_ci, target utf8mb4_0900_ai_ci (charset utf8mb4 both); existing table/column defaults/collations and data compare equal. No database-default ALTER was authorized/performed. This is not full database-object/default equivalence.

Final actual History/authorized Download HTTP **200/200**, downloaded SQL **20,601 bytes**, hash `0fafc77e5bcff9d73112fab6033c5b388a7c6d58a69449007b0d039dbdbfd4bd` matches original manifest/job. No source data read was needed for this download check. The original Import job remains failed; separate acceptance evidence records partial outcome without manual job edits or fabricated succeeded transition. No re-export, re-import, new database/account, GRANT mutation, source/target data/schema write or cleanup after the single approved credential reset.

Active comparer change: effective metadata equality remains mandatory; allow only the observed exact explicit/implicit CHARACTER SET text difference when full column metadata, charset/collation and every other DDL character agree. Structural defaults/types/index/constraint/partition changes and source drift still fail. TDD comparer regression **1/2 RED -> 2/2 GREEN**, plus CLI rejection test added afterwards. Shared helper now resides in app/Import/EffectiveSchemaComparison.php; read-only tool delegates to it. No SQL dump rewriting or custom Import engine. Existing failed job was not reconciled or edited.

Fresh focused results: **ImportSchemaComparisonTest 3/3 PASS**, **Read-only acceptance 5/5 PASS**, zero skipped; PHP syntax **7/7 PASS**, PowerShell parse **0 errors**. These eight checks are new/changed comparer, lossless row-multiset/ENUM helper, bounded-memory/type rejection, MySQL PARTITIONS column fixture and safe unauthorized/malformed CLI input only. Previous Export **4/4 live and 39/39 focused** and official mysql.exe exit 0 remain reused evidence, not repeated execution. No full regression, stress test or unrelated suites.

Actual existing Apache HTTP routes Dashboard/Export/Import/History/Settings **200**, no application login form. Actual non-loopback interface request with forged X-Forwarded-For/Forwarded loopback headers **403**. Direct app/bootstrap.php, tools/reset-existing-import-password.php and selected SQL artifact URLs **403**. No Apache/listener/configuration change was made to obtain these results. Other unchanged CSRF/session/download checks reuse preceding evidence.

Historical artifact is trustworthy as SQL bytes/provenance; what is missing is a supported independent decoding/comparison of that artifact's row values (its original manifest records nine tables but no row-value digest, rows=null). Current live-source equivalence alone cannot substitute for snapshot equivalence. Do not report snapshot-data PASS without that missing comparison, or mislabel source drift as Import failure. Overall remains **PARTIAL** despite the completed corrected live current-source/target inspection, because historical snapshot-value proof remains missing. Original target/job/history/backups retained; no protected/unauthorized database access, commit/push/tag/release or new phase.

Details, exact files and limitations: docs/LOCAL_IMPORT_ACCEPTANCE_2026-10-19.md. Earlier credential-missing entries remain historical; they are superseded only by the actual reset evidence, not by a claimed complete acceptance pass.

---

## 2026-10-19 — Existing-target exact acceptance inspection (latest)

**PARTIAL / LIVE VERIFICATION BLOCKED.** Only existing target `local_import_20261018_b2197f1be82de698` is in scope. Actual current reader authentication and native server/exact-grant verification succeeded; target dedicated-account connection failed with **1045**, before schema/data reads from that target. User confirmed no saved target password. No reset, grant change, root fallback, Import retry, CREATE/DROP, source write or other-database access occurred. The supplied phpMyAdmin tree confirms the existing nine-table target only; it does not prove effective schema or exact data equality.

Prepared separate CLI-only read-only inspector `tools/verify-existing-import.php` and pure helpers `tools/verification/LocalImportAcceptance.php`. Active Import executor/comparison/state transitions were not changed. The inspector requires exact job/session/ownership/artifact/server/account/grants; bounded stdin; shared lock; server-enforced READ ONLY consistent snapshots; effective INFORMATION_SCHEMA metadata; exact evidence for every DDL difference; sorted lossless byte row-multiset comparison including duplicates, with explicit row/byte/time/type bounds. Unsupported conversions or unexplained DDL fail closed. No data/secret output. See docs/LOCAL_IMPORT_ACCEPTANCE_2026-10-19.md for scope and historical-backup limitations.

New focused synthetic tests: initial **0/3 RED** (missing helpers), final **3/3 PASS**, zero skipped. Cases cover metadata-proven explicit charset difference, actual type/nullability/partition and collation mismatch rejection, exact row-multiset ordering/duplicates, NULL vs empty, NUL/0xFF bytes, Thai/escaping, large exact numeric strings, microsecond timestamps, unsupported type rejection and row/byte overflow. These are helper checks, **not live MySQL data fidelity proof**. New PHP syntax checks **3/3 PASS** and baseline git diff whitespace check passed. Final read-only filesystem check confirms job failed with the original mismatch error and artifact still 20,601 bytes with unchanged SHA-256. No Export/Import execution tests, full regression or unrelated suites rerun.

Full explanation of all eight DDL definitions, complete effective metadata comparison, all live row values/duplicate multiplicities and backup-snapshot value equality remain **NOT VERIFIED**. Earlier 9-table/63-row observations are reused historical evidence, not new PASS results. Job remains **failed**, original history and ownership evidence preserved; no manual JSON edit or unsupported failed-to-succeeded transition. No new independent acceptance PASS exists.

No Git commit/push/tag/release, Windows/Apache change, new resources, re-import or cleanup. Missing credentials are the immediate blocker; the existence of the database is not in dispute.

---

## 2026-10-19 — Authorized single live Import acceptance

**PARTIAL: native SQL Import completed; strict verification FAILED.** This supersedes only the pending authorization status in the preceding implementation report. Do not count this as a successful Import acceptance or change the persisted failed job to succeeded.

Human explicitly authorized creation of target `local_import_20261018_b2197f1be82de698` and account `local_import_bd6678a09e1507c7@localhost`, exact SELECT/INSERT/CREATE/REFERENCES grants on that target, and one autoprintdb artifact Import. No other target/account was created. The unrelated browser fixture job was not provisioned.

Live flow through existing Laragon Apache: recover the same legitimate session-owned job, authenticate the existing reader through the normal Export connection POST, check job awaiting_provisioning, run the authorized external provisioning CLI with administrative password on stdin, then POST Import execute with CSRF, IMPORT confirmation and per-target credential. Administrative connection was used only for exact server/collision/account/grant provisioning checks, CREATE DATABASE, CREATE USER and the exact escaped GRANT. SQL Import ran under the new dedicated account, never root or backup_reader. Password delivered to Windows Clipboard, not source/config/job/log/report; no credential was reset.

Verified identity before mutations and again under actual runtime accounts: MySQL Community **8.4.3**, endpoint **127.0.0.1:3306**, native UUID `198f474e-899b-11f1-903d-f0761cf4041a`, roles NONE, partial_revokes 0; reader CURRENT_USER backup_reader@localhost; target CURRENT_USER exact generated account. Provisioning required exact schema/account absence; no IF NOT EXISTS or prefix matching. Actual grants matched USAGE plus only SELECT, INSERT, CREATE, REFERENCES on the escaped exact target. Ownership journal records exact names, job ID, server UUID, creation evidence/timestamps. Runner confirmed target empty and acquired shared operation lock before execution.

Job `b2197f1be82de698bd6678a09e1507c7`: creation evidence **2026-10-19 09:03:35 +07:00**; running **09:03:36**; finished **09:03:38**. Official pinned mysql.exe returned **exit 0**, no stdout/stderr diagnostic failure; code then obtained target/source snapshots and rejected comparison with `import_target_schema_or_rows_mismatch`. This follows actual runner control flow, not an inference that HTTP 200 means Import success. HTTP execute/status transport completed; persisted status endpoint **200**, actual job **failed**.

Read-only diagnosis under existing SELECT-only reader and exact target account found:

| Table | Source rows | Target rows |
|---|---:|---:|
| autoprint_dose_change_audit | 4 | 4 |
| autoprint_flag_reconcile_runs | 4 | 4 |
| autoprint_flag_reconcile_settings | 1 | 1 |
| autoprint_queue | 50 | 50 |
| cleanup_security | 1 | 1 |
| facility_settings | 1 | 1 |
| herbal_compress_history | 1 | 1 |
| network_access_settings | 1 | 1 |
| visit_flag_audit | 0 | 0 |

**9 tables / 63 rows** on each side; successful COUNT reads establish target data accessibility. Tables/columns/indexes/foreign keys/referential actions/check metadata matched in the collected snapshots. Raw SHOW CREATE TABLE strings differed for **8** tables. Concrete observed difference: source cleanup_security.password_hash declaration `varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL`, target `varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL`. This demonstrates a redundant charset-rendering difference in one definition; it does not prove that every raw DDL difference is semantically harmless. No ad-hoc SQL replacement, parser change, comparison relaxation, schema ALTER or success fabrication was performed. All raw DDL must remain fail-closed until a separately justified comparison improvement is implemented/tested. The runner's source-before/source-after snapshots matched in measured dimensions (otherwise it raises the distinct source-changed error first); full data-value equality and exclusion of same-count external writes remain NOT VERIFIED.

Selected SQL file remains **20,601 bytes**, SHA-256 `0fafc77e5bcff9d73112fab6033c5b388a7c6d58a69449007b0d039dbdbfd4bd`; actual post-run hash matches existing manifest/job. Zero actual new import-password matches found in inspected job/log/ownership files. Original artifact was not rewritten. The temporary read-only diagnostic source created for this specific inspection was removed after use; it did not execute SQL writes. No application source fix or old test rerun was needed in this acceptance round.

Target and account remain present for explicit reconciliation; failed job/ownership journal preserved. **No retry, DROP, source writes, protected/unauthorized-source access, port 3333 connection, service restart, Windows/Apache/config change, Git commit/push/tag/release.** Existing listeners retain Apache PID 4024/8081 and MySQL PID 16220/3306. Readiness: Export retains prior 4/4 evidence; live Import execution demonstrated, full Import acceptance still **BLOCKED by strict raw-DDL comparison**. No additional persistent actions are automatically authorized.

---

## 2026-10-19 — Local Export / Import Manager (current implementation)

Status: **OFFLINE IMPLEMENTED / LIVE IMPORT BLOCKED — exact target/account authorization pending**. No MySQL connection, account provisioning, CREATE DATABASE, GRANT, SQL Import, source writes or cleanup occurred in this round. The four successful live Exports in the next historical entry are reused evidence, not fresh passes or Import proof.

Scope: direct-loopback access without application login; existing official Export retained; new trusted-artifact Import using pinned official mysql.exe, generated exact target/account, private ownership evidence, shared operation lock, explicit session-owned confirmation and safe persisted results. Historical custom Restore endpoints now return 410; their implementation files remain retained. No new custom SQL parser/executor.

Fresh focused results observed during implementation:

| Check | Result | Evidence scope |
|---|---|---|
| LocalImport tests, final run | **18/18 PASS**, zero skipped | Artifact provenance/hash/size/profile, tamper/truncation/path rejection, generated scope/exact grants, ownership/session isolation, shared lock, lifecycle/no retry/interrupted recovery, comparison mismatch, pinned binary/options, stdin streaming, native diagnostic suppression, local access and UI/API wiring |
| Directly affected SecurityFoundationTest | **10/10 PASS** | Loopback-only authorization, CSRF/session/download foundation |
| Directly affected RoutingAndViewTest | **4/4 PASS** | Active routes/navigation |
| Mysqldump focused suite | **18/18 PASS** | Shared process optional file stdin and generated credential username support; existing reader defaults preserved; no repeat live Export |
| Phase2JobApiAndUiTest | **5/5 PASS** | Existing Export access/CSRF/ownership hooks affected by shared access changes |
| Selected Phase4Ui tests | **6/6 PASS** | Shared layout, Dashboard, Settings, History only; not the full historical UI suite |

Unique focused aggregate: **61/61 PASS**. The first aggregate was 60/60; orphaned Import recovery was subsequently added with an observed failing test, implemented and passed; final LocalImport rerun confirms all 18 tests. No full regression or unrelated Restore/DPAPI/deployment suite. Other observed RED-to-GREEN fixes: missing native-client stdout rejection, missing Import script in shared layout allowlist, initial absent components, import account option validation and file-backed stdin support.

Actual existing Apache HTTP checks (no database connection): Dashboard/Export/Import/History/Settings **200**; Import prepare **201** with actual selected artifact full integrity verification; missing CSRF **419**; browser-supplied target override **409**; foreign-session job status **404**; old Restore APIs **410**; direct SQL storage and app/bootstrap.php **403**. Request via the machine's non-loopback address with forged forwarding headers **403**; direct IPv6 loopback Import page **200**. CSRF initially returned 500 because PHP/Apache did not recognize the 419 reason phrase; explicit HTTP status line fixed the actual check to 419. Browser rendering exposed the initially missing Import JS allowlist entry; fixed and verified generated-target/status rendering and hidden execution controls before provisioning. No real Import button execution or MySQL validation is claimed.

Final changed PHP syntax checks: **32/32 PASS**. Updated JS passed Node --check; git diff --check reported no whitespace errors (Git emitted existing LF-to-CRLF conversion warnings). Browser checks establish actual desktop route/control rendering; tablet/mobile visual revalidation is **NOT VERIFIED** (no broad layout redesign).

Pinned mysql.exe SHA-256: `87a1539a9bfec68bfb4a66b7e5d0079418ed8c0084cb22645e9fc3145aad3797`; actual no-connection version/help option checks passed for MySQL 8.4.3 Win64. Selected autoprintdb file remains 20,601 bytes, SHA-256 `0fafc77e5bcff9d73112fab6033c5b388a7c6d58a69449007b0d039dbdbfd4bd`. Offline inspection found zero anchored CREATE DATABASE/USE directives in that concrete file. This observation is not a general SQL parser or arbitrary-dump safety claim: acceptance requires exact trusted official producer/profile/options plus successful job provenance and full hash, and the actual per-target least-privilege account remains the final boundary. Unknown/legacy/source-directive profiles are rejected, never string-rewritten.

Live verification, when authorized, compares raw SHOW CREATE TABLE, columns/indexes/FKs/checks and exact row counts against source-before/source-after snapshots. Full data-value equality, exclusion of external concurrent DDL/writes, live import permissions, actual native SQL exit, target accessibility and successful restored result remain **NOT VERIFIED**. Source drift or schema/count mismatch must fail rather than claim success. Fake adapters cannot establish real MySQL compatibility.

Prepared authorization job: `b2197f1be82de698bd6678a09e1507c7`; proposed new target `local_import_20261018_b2197f1be82de698`; proposed account `local_import_bd6678a09e1507c7@localhost`; only SELECT, INSERT, CREATE, REFERENCES on the escaped exact target. Provisioning CLI plan-only output was checked without connecting. No ownership journal was manufactured for live acceptance. One additional browser smoke-test job is only awaiting provisioning. Authorization, fresh server UUID/version/endpoint/grants/collision checks and usable reader/import credentials are still required before persistent actions. No automatic target/account deletion or retry is authorized.

Existing backups/history retained; source and production package manifests not rewritten; no port 3333, Windows/Apache/service/trust changes, protected-source access, commit/push/tag/release. Only own isolated test fixtures were removed. Historical results below remain unchanged and are not relabeled as newly run.

---

## 2026-10-18 — Controlled live mysqldump acceptance (latest result)

**PASS for Manual InnoDB table structure/data backup on the four authorized sources.** This supersedes the live-credential blocker in the preceding offline entry. Full database-object coverage and Restore correctness are not claimed.

User explicitly authorized resetting the lost reader credential and continuing. Reset only `backup_reader@localhost` with a random password; existing exact SELECT grants unchanged (verified before reset and by the reader connection afterwards). Root was used only for server/account checks and the authorized password reset, never for dump execution. Native server facts: MySQL Community 8.4.3, `127.0.0.1:3306`, UUID `198f474e-899b-11f1-903d-f0761cf4041a`, reader `CURRENT_USER()=backup_reader@localhost`, roles `NONE`, `partial_revokes=0`. New password delivered to Windows Clipboard, not printed or persisted to config/report. No production Secret Provider enrollment.

Real HTTP workflow through existing Laragon Apache: GET Backup -> POST connection form with CSRF -> POST job create -> POST execute -> GET persisted status -> GET History -> authorized Download. Application Auth/CSRF/session ownership remained enabled; PowerShell WebRequestSession retained cookies. Browser separately confirmed History rendering. This is actual website/API execution, not a CLI-only dump or a claim that browser buttons were clicked.

Initial live job `d0df8d7faa71ac7af7ea3befd0e571bb` truthfully failed all four items with exit 7, no artifacts published. Diagnosis: installed utility rejected `--connect-timeout=5`. Removed the unsupported flag; added a real-binary, no-connection `--help` profile regression: **RED exit 7 -> GREEN exit 0**. No unrelated engine or server changes.

Successful job: `22233e185117ea16dd424086220089dd`, 2026-10-18 **22:37:54–22:37:59 +07:00**. Persisted 12 revisions; maximum running items in any revision **1**; terminal **succeeded**, succeeded **4**, failed **0**.

| Source | Tables | SQL bytes | SHA-256 | Result |
|---|---:|---:|---|---|
| autoprintdb | 9 | 20,601 | `0fafc77e5bcff9d73112fab6033c5b388a7c6d58a69449007b0d039dbdbfd4bd` | succeeded |
| drugmap_db | 7 | 775,477 | `69c77e2e9a7ce03447a4ffba5cf27093de319d4ede6a4405a0936a2c5266b6b7` | succeeded |
| drug_stock_check | 10 | 522,848 | `ecd48ce34a0a938489c131058b7eb44e53f6402066ee4ddb960df17be545531f` | succeeded |
| prepack_labels | 5 | 183,155 | `196640df501a62fc20b42ebc3c947e46c340cb13379aa202abcb3c4ea0dc533e` | succeeded |

All four: actual utility exit **0**, nonempty SQL, native completion footer and application marker present, manifest `complete=true`, source identity correct, actual bytes/SHA-256 match manifest/job. All four authorized HTTP downloads returned **200** and downloaded bytes hashed identically to stored SQL. History returned **200** and listed all filenames. Job create **201**, execute/status **200**, connection form **200**. Negative checks using a separate legitimate loopback session: foreign job status **404**; invalid download token **404**. No protected/unauthorized-schema negative database query.

History integration fix: new official-utility metadata was previously shown as `invalid_metadata` because History reused the old custom-exporter Restore validator. Added lightweight `BackupService::mysqldumpHistoryMetadata()` and front-controller mapping; new manifest metadata/provenance now display correctly while Restore stays `unsupported_mysqldump_format`, ineligible. No SQL hash on each History load, fabricated row count or weakening of Restore validator/parser. Focused regression **RED undefined method -> GREEN**. Browser verified all four new History rows: provenance **verified**, integrity **not_checked** (history is metadata-only), Restore **unsupported_mysqldump_format**. Full live hashes above were independently checked during acceptance, not claimed as a cached UI hash result.

Final targeted verification: `Mysqldump` **18/18 PASS** (including real binary option validation and History metadata); directly affected source-policy/runner/publisher/API contract suites **6+5+5+5 PASS**. Aggregate **39/39 PASS**, zero skipped. No full regression, unrelated UI, Restore, DPAPI or deployment test rerun. Changed PHP lint **12/12 PASS**; `git diff --check` passed.

Post-run: SQL `.tmp` count under authorized-source folders **0**; private credential temp directories **0**; reader-password matches in live job files, app log and new manifests **0**. Shared Laragon process/listener baseline unchanged: Apache PID 4024/8081, MySQL PID 16220/3306. Existing backups retained, failed job history retained. No connection to port 3333, protected/unauthorized database access, source data/schema writes, broad grants, Restore execution, service/config/deployment changes, commit/push/tag/release. Old production package remains unchanged/stale.

Remaining limits: table-only scope excludes triggers/routines/events and rejects visible views/non-InnoDB/cross-schema FK; avoid concurrent DDL; MySQL 8.4 SQL compatibility with other client/server dialects and live Restore are NOT VERIFIED. New artifacts intentionally remain ineligible for the existing Restore workflow. Clipboard is transient; future login requires retaining the reader password securely.

---

## 2026-10-18 — Official mysqldump replacement (latest)

Current acceptance: **OFFLINE IMPLEMENTED / LIVE BLOCKED — existing reader credential required interactively**. This entry supersedes custom-exporter implementation assumptions below, not the historical observations. No live mysqldump backup was executed in this change round.

Verified installed binary: `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe`; reported `mysqldump Ver 8.4.3 for Win64 on x86_64 (MySQL Community Server - GPL)`; SHA-256 `25d8eab0985f55864d5007574ec275b79fdf761cf53c3a0a766ec4f2bdd10acc`. This is the installed-artifact pin, not a new vendor-signature/provenance approval.

| Focused verification | Observed result | Scope |
|---|---|---|
| Initial TDD RED | 0/6 pass | Command/process/credential classes missing |
| Service integration RED | 7/10 pass, 3 expected failures | Existing custom exporter exercised by new service contract |
| Grant policy RED | 0/1 pass | Missing exact grant validator |
| `php tests/run.php Mysqldump` | **16/16 PASS**, 0 skip | Fixed argv, allowlist, endpoint/runtime account/server UUID/version/roles/partial_revokes checks, exact escaped grants, option escaping, real synthetic NTFS ACL lifecycle, 2MiB stdout + 1MiB stderr, exit/timeout/cancel/limits, fake engine header/footer/diagnostics, secret-safe errors, cleanup/no-overwrite, publisher hashes, sequential persisted runner integration |
| `ManualBackupSourcePolicyTest` | **6/6 PASS** | Exact source policy and rejection before database/process access |
| `Phase2JobRunnerTest` | **5/5 PASS** | Directly affected operation integration, sequence/failure/re-entry/recovery |
| `Phase2ArtifactPublisherTest` | **5/5 PASS** | Publisher completion/integrity/create-only/rollback contracts |
| `Phase2JobApiAndUiTest` | **5/5 PASS** | Auth/CSRF/ownership/public response and existing backup markup contract |
| Changed PHP lint | **12/12 PASS** | BackupService, operation, five new adapter components, controller, front controller, two new test files and backup view |
| Apache browser rendering | **VERIFIED** | Existing `http://localhost:8081/backUP-phpmyadmin-fordev/index.php?page=backup` renders new scope notice and existing workflow controls; no DB connection initiated |

Fresh focused aggregate: **37/37 PASS**. No full regression, Restore, DPAPI or deployment suites run. The prior 48/48 result remains historical; 13 custom-exporter tests (`ManualBackupSnapshotTest`, `ManualBackupSqlFidelityTest`, `ManualBackupEngineIntegrationTest`) and the unused serializer/batch/snapshot classes are retired because official mysqldump replaces their behavior. Their success is not transferred to the new utility.

Table-only manifests explicitly record exclusions and `full_database_objects=false`; restricted metadata must not imply trigger/routine/event absence. Visible table pre/post comparison is only a drift detector. Concurrent DDL must be avoided; no prevention or complete DDL-consistency claim is made. MySQL 8.4 SQL compatibility with older MySQL/MariaDB is **NOT VERIFIED**. Source inspection confirms existing RestoreArtifactValidator rejects the new manifest because it requires all old integer stats and restore_order; the new exporter truthfully uses unknown row count. Restore validator/parser remain unchanged and hard-disabled execution remains in place; no restore correctness/eligibility claim.

Live source backup, actual mysqldump DB connection/exit, persisted live sequence, SQL/manifest/hash/download and History entries for all four sources are **NOT VERIFIED**. Prior admin metadata is supporting history only. Existing `backup_reader` was not recreated/modified. Current browser session is unconnected; neither browser nor Windows Clipboard has a usable previously generated reader secret. Interactive entry was requested; no password reset or root fallback is authorized/used.

Security boundary: fixed binary/options/endpoint/account/server; no privileged metadata queries; ephemeral plaintext option file outside web root with ACL verified before write; password absent argv/environment/log/job/manifest. Real NTFS test uses synthetic credentials only and removes its own exact directory. Hard process/machine crashes can leave an orphan private directory; secure erasure and protection against current-SID/admin compromise are not claimed. The fixed PowerShell command only creates/verifies a new temporary directory ACL; it does not change policy/trust or Secret Provider architecture. No account/grant/source database mutation, service/config change, backup deletion, production-package rebuild, commit/push/tag/release or protected-schema access in this round.

---

วันที่: 2026-10-16  
Runtime: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`

## Reused Pre-Change Evidence

| Test | Scope | Result | Evidence | Related files | Reason not repeated |
|---|---|---|---|---|---|
| PHP syntax baseline | PHP เดิม 11 ไฟล์ | PASS | `lint_files=11 failures=0` ก่อนเริ่ม implementation | repository PHP at V1.0 | ไฟล์เดิมที่ไม่เปลี่ยนและ dependency ไม่เปลี่ยน ได้แก่ root `index.php`, `BackupService`, database classes, `Flash`, `Request` ไม่ถูก lint ซ้ำใน changed-file lint; `Security.php` เปลี่ยนภายหลังและรวมอยู่ใน final changed-file lint |
| Working-tree baseline | Git state ก่อน Phase 1 | PASS | clean `main`; `git diff --check` exit 0 | repository | ใช้เป็นหลักฐานก่อนเปลี่ยน ไม่จำเป็นต้องจำลองซ้ำ |

## TDD Evidence

| Test cycle | Scope | RED evidence | GREEN evidence | Related source files | Reason for rerun |
|---|---|---|---|---|---|
| Security foundation | auth, loopback, forwarded-header rejection, session cookie policy, logout, CSRF | 6 failed because `Auth`/`Session` classes did not exist; existing CSRF case passed | 7/7 passed | `Auth.php`, `Session.php`, `Security.php` | First implementation of affected behavior |
| Configuration and logging | recursive local override; secret redaction/newline normalization | 2 failed because `Config`/`Logger` did not exist | 9/9 cumulative security tests passed | `Config.php`, `Logger.php`, `app/bootstrap.php`, `config/app.php` | Security test file changed with two new cases |
| Storage/download policy | containment, traversal, relative path, symlink escape, token, inventory, artifact integrity | 5 failed because policy/authorization classes did not exist; symlink case skipped by platform | 5 passed, 0 failed, 1 skipped | `BackupPathPolicy.php`, `DownloadAuthorization.php`, `public/download.php` | First implementation of storage/download boundary |
| Routing/shared views | five routes, safe 404, navigation, password sentinel | 4 failed because `Router`/`View` did not exist | 4/4 passed | `Router.php`, `View.php`, `views/*`, `public/index.php` | First implementation of router/view foundation |

## Final Targeted Verification

| Test case | Scope | Result | Evidence | Related files | Reason for rerun |
|---|---|---|---|---|---|
| Lightweight suite | All new shared security, storage, download, routing and view behavior | PASS with one platform skip | Final post-review run: `Tests: 22, Passed: 21, Failed: 0, Skipped: 1` | `tests/*`, all Phase 1 services/views | Required after integration and repeated once after review fixes changed shared path/redaction components |
| Changed PHP syntax | Only modified/new PHP | PASS | Final post-review run: `changed_php_files=30 lint_failures=0` | 30 changed/new PHP files | Implementation added/changed these files; rerun after review fixes changed `Security.php`, path policy and endpoints |
| Application routes | Dashboard, Backup, Restore, History, Settings over local HTTP | PASS | each returned HTTP 200, all five nav labels present, CSP present, no password value attribute | `public/index.php`, router, layout/views | HTTP integration had not been covered by class-level tests |
| Unknown route | traversal-shaped page input | PASS | HTTP 404 and safe shared 404 content | `Router.php`, `public/index.php` | Confirms front-controller integration of allowlist |
| Invalid download | guessed SQL path plus invalid token | PASS | HTTP 404 with exact generic body `Backup file is not available.` | `public/download.php`, authorization | Confirms endpoint rejects unauthenticated artifact request without path disclosure |

## Final Review Fix Evidence

| Finding | RED evidence | GREEN evidence | Final suite |
|---|---|---|---|
| Protected/public allowlist root could be returned by fallback | `backup path policy rejects a configured root that overlaps protected application directories` failed with `Unexpected was accepted` | focused test passed after constructor rejects roots that overlap `app`, `config`, or `public`; settings fallback also resolves the validated default | 21 passed, 0 failed, 1 skipped |
| Failed connection attempted password was absent from logger redaction list | `failed connection redaction includes submitted and saved database passwords` failed because `Security::connectionPasswordSecrets()` did not exist | focused test passed and both submitted/saved sentinels were redacted | 21 passed, 0 failed, 1 skipped |

HTTP routes were rerun after the path-policy fix because that service is initialized by every protected page: five routes returned HTTP 200 with navigation/CSP and no password value; invalid download remained HTTP 404 with the generic body (`http_failures=0`).

## Platform-Skipped / Not Verified

| Case | Status | Reason |
|---|---|---|
| Symlink/junction escape runtime test | NOT VERIFIED on this host | Windows environment denied creation of the test symlink. Canonical containment logic is covered for traversal and sibling-prefix cases, but the platform-specific escape could not be exercised. |
| Live MySQL/MariaDB backup | NOT VERIFIED | No disposable database was authorized; production databases, especially `jhcisdb`, were intentionally untouched. |
| Restore workflow | NOT VERIFIED / OUT OF SCOPE | Restore Engine belongs to a later phase. |
| Apache `.htaccess` enforcement | NOT VERIFIED | PHP built-in server was used for HTTP smoke tests and does not interpret `.htaccess`. |
| Reverse proxy deployment | NOT VERIFIED | No proxy environment was available; proxy headers are intentionally not trusted by application auth policy. |
| Full regression/stress/benchmark | NOT RUN | Prohibited by change-based testing policy and unrelated to the Phase 1 changes. |

## Database Safety Record

No test opened a MySQL/MariaDB connection, selected a database, executed a dump, or performed `DROP`, `ALTER`, `TRUNCATE`, `DELETE`, `UPDATE`, restore, migration, service change, or data-directory operation. Filesystem tests used randomly named disposable directories under the operating-system temp directory only.

---

## Phase 2 — Backup Engine & Sequential Queue

วันที่ตรวจสอบ: 2026-10-16  
Architecture: File-backed HTTP Job Executor  
Runtime: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`

### Phase 2 TDD Evidence

| Test cycle | RED evidence | GREEN/final evidence | Scope |
|---|---|---|---|
| Job persistence and guards | 4 failed because `BackupJobStore` / `BackupExecutionGuard` did not exist | 5/5 passed, including a second PHP process holding both locks on Windows | JSONL snapshots, corrupt trailing record recovery, secret-field exclusion, ID traversal, per-job/global `flock` |
| Artifact publisher | 4 failed because publisher did not exist; later regressions caught trailing data, foreign temp deletion, and SQL-before-manifest order | 5/5 passed | strict end marker, SHA-256/bytes/manifest, no overwrite, manifest-first publish, scoped rollback |
| Sequential runner | interface initially missing; 4 initial tests failed before implementation; connection-change case later failed before `interruptQueued()` existed | 5/5 passed | declared order, no overlapping calls, continue-on-error, start failure, duplicate rejection, orphan/connection interruption |
| Job API and UI | 5 failed before access/API/JS/status UI existed; connection-context and 4xx/500 contract additions failed before implementation | 5/5 passed | ownership, allowlist, secret-free response, real state labels, no fake percentage, Auth/CSRF/session-release contract |

### Phase 2 Final Targeted Verification

| Verification | Result | Evidence |
|---|---|---|
| Phase 2 focused suite | PASS | `Tests: 20, Passed: 20, Failed: 0, Skipped: 0` |
| Affected routing/view tests | PASS | `Tests: 4, Passed: 4, Failed: 0, Skipped: 0` — rerun because backup view/layout/index changed |
| Affected security tests | PASS | `Tests: 10, Passed: 10, Failed: 0, Skipped: 0` — rerun because Auth logout, bootstrap, CSRF/API integration, and session-owned job context changed |
| Affected storage/download integrity tests | PASS with platform skip | `Tests: 8, Passed: 7, Failed: 0, Skipped: 1` — rerun because backup artifact finalization/manifest integrity changed; Windows symlink creation remains unavailable |
| HTTP rejection smoke | PASS | Backup page 200; status without CSRF 419; foreign job rejected; create without connection 409 |
| HTTP behavioral smoke with isolated fixture session | PASS | create 201; 32-hex job ID; password sentinel absent from response; changed connection execute 409 and persisted state `interrupted`; second session status 404 |
| JavaScript syntax | PASS | `node --check public/assets/backup-jobs.js`, exit 0 |
| Changed/new Phase 2 PHP syntax | PASS | `phase2_php_files=22 lint_failures=0` |
| Diff whitespace and database-artifact guard | PASS | `git diff --check` exit 0; `sql_or_backup_status_entries=0`; `jhcisdb_status_entries=0` |
| Live database access | NOT RUN | HTTP fixture used an intentionally invalid host but execution was rejected on connection-context mismatch before `ConnectionFactory`; no MySQL/MariaDB connection was opened |

The HTTP fixture created one disposable job record under `storage/jobs/`; that exact record and its exact per-job lock file were removed after the smoke test. No file under `storage/backups/` was created, modified, read for content, or removed.

### Tests Intentionally Not Repeated / Not Verified

- No unfiltered full-regression command was run. Phase 1 filters were selected individually only where Phase 2 changed a shared dependency: Auth/bootstrap, layout/routing, and artifact/download integrity.
- Live MySQL/MariaDB dump behavior remains **NOT VERIFIED** because no separately authorized disposable database exists. In particular, `jhcisdb` and all other real databases were untouched.
- Restore, scheduler, automatic backup, CLI worker, stress/benchmark, and queue-framework behavior were not run because they are outside Phase 2 or explicitly prohibited.
- The symlink/junction escape runtime case remains skipped because this Windows host denies test symlink creation; the existing canonical containment tests still pass.

### Phase 2 Safety Record

Tests used fake backup operations, fixture SQL text, temporary OS directories, local PHP processes, and isolated HTTP sessions. No production/source database command was issued. No prior backup was deleted or overwritten. No commit, push, tag, release, branch rewrite, or Git-history change was performed.

---

## Phase 3 — Safe Restore Preflight & File Management

วันที่ตรวจสอบ: 2026-10-17  
Architecture: Conservative Structural Validation + No SQL Executor  
Runtime: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`

### Phase 3 TDD Evidence

| Test cycle | RED evidence | GREEN/final evidence | Scope |
|---|---|---|---|
| Artifact/provenance | 3 failed because Restore classes did not exist | focused artifact cases passed; later strict-manifest regression failed before type/schema validation | quick inspection without SQL hash, full SHA-256/bytes/final marker/restore-order, exact Phase 2 provenance, `unverified_origin`, no backfill |
| SQL structural safety | 5 failed because parser/target/gate did not exist | parser cases passed; review regressions then caught executable-comment, hidden delimiter, broad SET/ALTER/CREATE, mixed qualifier and unsafe version-comment cases before fixes | delimiter/quote/comment lexer, one-schema scope, dynamic/DEFINER/filesystem/global constructs, exact versioned SET allowlist, target policy, hard-closed gate |
| Restore jobs | 4 failed because job classes did not exist | job cases passed; later transition-jump and snapshot-digest tests failed before enforcement | append-only snapshots, ownership, duplicate/global preflight locks, queued→running→blocked/failed, no succeeded, interruption, same-pass parse/hash comparison |
| API/UI/history | route and history tests failed before Restore view/status existed | rendered route/history tests passed | shared layout, preflight-only UI, candidate link, `unverified_origin`, no fake progress or execute control |

### Phase 3 Final Targeted Verification

| Verification | Result | Evidence |
|---|---|---|
| Phase 3 focused suite | PASS | `Tests: 18, Passed: 18, Failed: 0, Skipped: 0` |
| Affected routing/view tests | PASS | `Tests: 4, Passed: 4, Failed: 0, Skipped: 0` — Restore changed from placeholder to a real shared-layout view |
| Affected security tests | PASS | `Tests: 10, Passed: 10, Failed: 0, Skipped: 0` — new Restore endpoint reuses Auth/Session/CSRF and safe logging |
| Affected storage/download tests | PASS with platform skip | `Tests: 8, Passed: 7, Failed: 0, Skipped: 1` — artifact containment/history/download boundary was reused; Windows symlink creation remains unavailable |
| Phase 3 PHP syntax | PASS | `phase3_php_files=25 lint_failures=0` |
| Restore JavaScript syntax | PASS | `node --check public/assets/restore-jobs.js`, exit 0 |
| Local HTTP smoke | PASS | Restore 200 with preflight/disabled notice and no execute action; History 200 with no-hash label; Restore status without CSRF 419 |
| Forbidden execution API scan | PASS | no `mysqli`, PDO, query/execute, shell/process execution API under `app/Restore` or `public/restore-job.php` |
| Diff/artifact guard | PASS | `git diff --check` exit 0; no SQL, backup artifact, or `jhcisdb` status entry |
| Independent read-only review | PASS after fixes | final reviewer result: `no Critical/Important remaining`; review specifically pressure-tested delimiter hiding, MySQL/MariaDB executable comments, snapshot stability, broad DDL/SET tails, engine-option ambiguity, and INSERT tails |

### Implemented Safety Evidence

- History reads file size, bounded manifest metadata, and a per-request Phase 2 provenance index only; it does not compute SQL SHA-256.
- Every preflight reopens the real SQL file, validates the strict manifest and final marker, streams parser input and SHA-256 through the same byte pass, and compares byte count/digest to the manifest-verified snapshot.
- The lexer emits every delimiter outside quoted strings/comments and rejects ambiguity, embedded MySQL/MariaDB executable comments, dynamic SQL, DEFINER, schema-qualified references, server-global/persistent SET, tablespace/directory/file operations, unknown statement shapes, and statements over 16 MiB.
- Restore jobs have no `succeeded` state or SQL-execution timestamp. A structurally valid job finishes `blocked` with four `not_verified` safety checks.
- Phase 1 backups and other files without exact Phase 2 succeeded provenance remain `unverified_origin`; no provenance file is generated or changed.
- The shared database-operation lock exists only as a future interface contract. `BackupExecutionGuard` and `BackupJobRunner` were not modified for Phase 3.

### Tests Intentionally Not Repeated / NOT VERIFIED

- Phase 2 backup engine, sequential runner, publisher, and queue suites were not rerun because Phase 3 did not modify those components. Provenance consumption is covered using real temporary Phase 2 `BackupJobStore` snapshots.
- Live Restore Integration is **NOT VERIFIED**: no isolated disposable MySQL/MariaDB instance or dedicated restore user was supplied.
- Target database existence/emptiness, dedicated credential separation, target-scoped privileges, version compatibility, post-restore integrity, failure recovery after real SQL execution, and shared backup/restore database-operation locking are **NOT VERIFIED**.
- A single SQL statement larger than 16 MiB is deliberately unsupported/fail-closed. General view bodies and trigger/routine/function/event definitions are also unsupported because Phase 3 cannot prove their full semantics. Dumps containing these or other safe-but-unrecognized constructs are ineligible rather than guessed safe.
- No full regression, stress test, scheduler, automatic backup, or Phase 4 UI test was run because it was outside the change-based scope.

### Phase 3 Database Safety Record

All tests used hand-written SQL fixtures, fake parser output at one runner boundary, append-only job fixtures, isolated PHP sessions, and randomly named operating-system temporary directories. No MySQL/MariaDB connection was opened, no SQL restore was executed, no source/production database (including `jhcisdb`) was read or modified, and no existing backup was deleted or overwritten. No commit, push, tag, release, scheduler, automatic restore, SQL executor, or execution-enabling configuration flag was created.

---

## Phase 4 — Modern UX/UI & Final Integration

วันที่ตรวจสอบ: 2026-10-17  
Architecture: Native PHP progressive enhancement  
Runtime: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`

### Phase 4 TDD Evidence

| Test cycle | RED evidence | GREEN/final evidence | Scope |
|---|---|---|---|
| Session-owned job history | 2 failed because `ownedJobIds()` did not exist | 2/2 passed, then included in final 8/8; reviewer also pressure-tested 27 IDs read-only and observed the latest 20 without session mutation | repository regression covers valid/unique/newest-first IDs; bounded/no-mutation behavior received additional read-only review evidence |
| Shared shell and factual overview | 3 failed for missing responsive menu, persisted dashboard facts, and grouped settings | 5/5 passed before the next UI cycle | skip link, active nav, mobile menu semantics, real job/safety data, secret-free settings |
| Backup selection and confirmation | 1 failed for missing search/selection/dialog hooks | 6/6 cumulative passed | search, select-visible, clear, selected count, explicit sequential confirmation, no percentage |
| Restore and History clarity | 2 failed for missing hard-closed gates/history columns | 8/8 passed; Phase 3 UI regression then failed on wording contracts and returned to 2/2 after the copy was corrected | no restore execution control, safety gates, preflight history, metadata-only integrity/provenance/eligibility |

### Phase 4 Final Targeted Verification

| Verification | Result | Evidence |
|---|---|---|
| Phase 4 focused suite | PASS | `Tests: 8, Passed: 8, Failed: 0, Skipped: 0` |
| Affected routing/shared views | PASS | `Tests: 4, Passed: 4, Failed: 0, Skipped: 0` |
| Affected Phase 2 API/UI contract | PASS | `Tests: 5, Passed: 5, Failed: 0, Skipped: 0` — persisted polling, allowlist/ownership, Auth/CSRF, and no fake percentage retained |
| Affected Phase 3 API/UI contract | PASS | `Tests: 2, Passed: 2, Failed: 0, Skipped: 0` — preflight-only/blocked UI and no-hash History label retained |
| Phase 4 PHP syntax | PASS | `phase4_php_files=11 lint_failures=0` |
| JavaScript syntax | PASS | `node --check` passed for `app.js`, `backup-jobs.js`, and `restore-jobs.js` |
| Restore reload status polling | PASS | Node VM behavior test first failed because a running job scheduled no poll, then passed: `PASS restore reload polls status without auto-preflight`; requests remain GET `action=status` only |
| Local HTTP smoke | PASS | Dashboard, Backup, Restore, History, Settings each returned 200 with all five navigation labels; `http_failures=0` |
| Desktop visual smoke | PASS | in-app browser at 1440×1000: shared navigation, dashboard hero/metrics, Restore safety banner/gates, and History table rendered without overlapping controls |
| Mobile accessibility smoke | PASS | in-app browser at 390×844: menu changed from collapsed to expanded, exposed all five routes, Escape collapsed it and returned focus to the menu button; table remained in a labeled horizontally scrollable region |
| Independent read-only review | PASS after fix pass | no Critical; Important no-JS mobile navigation fixed; 44px target contract re-graded and fixed; Restore running-status limitation promoted from declined scope and fixed with RED→GREEN behavior test |
| Diff/artifact guard | PASS | `git diff --check` exit 0 (line-ending warnings only); `database_artifact_status_entries=0` |

### Change-Based Scope / NOT VERIFIED

- Live MySQL/MariaDB backup remains **NOT VERIFIED** because no separately authorized disposable database exists. No database selection or dump request was sent.
- Live Restore Integration remains **NOT VERIFIED**. Isolation, dedicated restore credentials, target-scoped privileges, target existence/emptiness, version compatibility, SQL execution, and post-restore checks were not attempted.
- The Backup confirmation dialog markup, selection logic, and JavaScript syntax were verified; an end-to-end browser submit with real database choices is **NOT VERIFIED** because the safe test session had no database connection.
- Repository tests do not independently exercise the 20-ID cap/session-immutability and corrupt-job front-controller fallback; implementation received read-only review pressure tests, but these remain documented test-coverage gaps rather than claimed automated coverage.
- Phase 1 storage/download internals, Phase 2 runner/publisher/locks, and Phase 3 parser/job-transition suites were not rerun because Phase 4 did not modify those components. Only their affected UI/API integration contracts were rerun.
- Apache `.htaccess`, reverse proxy behavior, stress/load testing, scheduler, automatic backup, automatic retry, and full regression were not run.

### Phase 4 Database Safety Record

Tests and browser smoke checks used rendered view fixtures, bounded session arrays, existing read-only backup metadata, and the local PHP built-in server. No MySQL/MariaDB connection was opened; no source/production database including `jhcisdb` was read or modified; no SQL restore was executed; no backup file was created, changed, hashed by History, or deleted. No commit, push, tag, release, scheduler, queue framework, SQL executor, or Laragon/database configuration change was performed.

---

## Laragon Apache Subdirectory Deployment

วันที่ตรวจสอบ: 2026-10-17  
Target URL: `http://localhost:8081/backUP-phpmyadmin-fordev/`  
Apache/PHP: Apache 2.4.66 Win64 with mod_php 8.3.30

### Root Cause / RED Evidence

- Root `.htaccess` tested public resources against `%{DOCUMENT_ROOT}/public%{REQUEST_URI}`. With Laragon's `DocumentRoot C:/laragon/www` and the application mounted as a subdirectory, that expression did not point to this project's `public/` directory. The root page happened to return 200, while `assets/app.css`, `assets/app.js`, `job.php`, `restore-job.php`, and `download.php` were all resolved below the project root and returned Apache 404.
- Requests to `index.php?page=backup|restore|history|settings` executed the root redirect shim, redirecting to `public/` and dropping the page query. They returned dashboard HTML instead of the requested route.
- The root deny rules did not include dot-directories. Apache served `.git/config` directly with HTTP 200 and the repository metadata body.
- After routing reached the PHP endpoints, Apache mod_php emitted HTTP 500 for `http_response_code(419)`, even though the body was the expected CSRF rejection. The focused test therefore remained RED until the API response helpers emitted an explicit `419 Authentication Timeout` status line.
- Initial Apache smoke: `14 of 39 checks failed`; after the rewrite fix but before the explicit 419 response fix: `2 of 39 checks failed`.

### Apache Configuration Evidence

- Active listener/process: `httpd.exe` on TCP 8081 from `C:/laragon/bin/apache/httpd-2.4.66-260223-Win64-VS18`.
- Main `DocumentRoot`: `C:/laragon/www`; the default `*:8081` VirtualHost grants `AllowOverride All` for that directory.
- `rewrite_module`, `dir_module`, `alias_module`, and `php_module` are loaded. No Alias is configured for this project path.
- The separate `backUP-phpmyadmin.test` VirtualHost points to another checkout (`C:/laragon/www/backUP-phpmyadmin/public`) and is not selected by the requested `localhost` URL.
- Apache and the built-in compatibility test both used PHP 8.3.30. No PHP configuration mismatch required a configuration change.
- No Laragon/Apache global configuration, other VirtualHost, Alias, MySQL configuration, or service startup setting was changed. Apache restart was not required for the project `.htaccess` change.

### Final Change-Based Verification

| Verification | Result | Evidence |
|---|---|---|
| Apache configuration syntax | PASS | `httpd.exe -t` returned `Syntax OK` |
| Actual Apache subdirectory smoke | PASS | `42 checks` covering five routes, expected page controllers, CSS/JS, Backup/Restore API, session-bound CSRF, download rejection, cookie flags, and direct-file protection |
| Application pages | PASS | Dashboard, Backup, Restore, History, and Settings each returned HTTP 200 and rendered the expected `data-page` through the requested Apache URL |
| Assets | PASS | `assets/app.css` and `assets/app.js` returned HTTP 200 with CSS/JavaScript content types |
| Backup/Restore API CSRF boundary | PASS | missing token returned HTTP 419 JSON; a valid session token reached ownership enforcement and returned HTTP 404 for a nonexistent job |
| Download authorization boundary | PASS | an invalid artifact/token returned application HTTP 404 and the generic unavailable message |
| Sensitive direct paths | PASS | `app/`, `config/`, `storage/`, `views/`, `tests/`, `tools/`, `docs/`, phase/baseline documents, `.git/config`, and `.htaccess` were not directly readable (403/404) |
| Affected Phase 2 API/UI contract | PASS | `Tests: 5, Passed: 5, Failed: 0, Skipped: 0` |
| Affected Phase 3 API/UI contract | PASS | `Tests: 2, Passed: 2, Failed: 0, Skipped: 0` |
| Changed PHP syntax | PASS | `public/job.php` and `public/restore-job.php` linted without errors |
| Built-in server compatibility | PASS | temporary PHP 8.3.30 server on `127.0.0.1:8787`: five pages/assets returned 200, both CSRF checks returned 419 JSON, invalid download returned 404 |
| Diff whitespace guard | PASS | `git diff --check` exited 0 for the changed deployment files (line-ending warning only) |

### NOT VERIFIED / Safety Record

- Non-loopback/LAN login with configured administrator credentials and HTTPS cookie behavior are **NOT VERIFIED**; the requested localhost deployment used the intended direct-loopback authorization mode.
- A successful download of an existing backup was not performed; only the authorization failure boundary was tested to avoid reading or changing backup artifacts unnecessarily.
- Live MySQL/MariaDB connection and backup execution are **NOT VERIFIED**. No database, including `jhcisdb`, was contacted or modified.
- Live Restore Integration remains **NOT VERIFIED** and no SQL restore/preflight execution was started.
- The unrelated `backUP-phpmyadmin.test` VirtualHost and other Laragon projects were not functionally exercised because no Apache global or VirtualHost configuration was changed.
- No full regression was run. No backup engine, restore parser, scheduler, database configuration, existing backup file, commit, push, tag, or release was changed.

---

## Restore Integration Extension — Offline Implementation Milestones 1–9

วันที่ตรวจสอบ: 2026-10-17  
Design: `RESTORE_INTEGRATION_DESIGN.md`  
Plan: `RESTORE_INTEGRATION_IMPLEMENTATION_PLAN.md`  
Runtime: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`

### Offline TDD / Change-Based Results

| Verification | Result | Observed evidence |
|---|---|---|
| Typed SQL plan/compiler | PASS | `Tests: 4, Passed: 4, Failed: 0, Skipped: 0` |
| Exact source policy, catalog, backup adapter, identity and privilege attestation | PASS | `Tests: 6, Passed: 6, Failed: 0, Skipped: 0` |
| Ownership journal and cleanup decision | PASS | `Tests: 3, Passed: 3, Failed: 0, Skipped: 0` |
| Shared database-operation lock | PASS | `Tests: 2, Passed: 2, Failed: 0, Skipped: 0` |
| Restore Integration job lifecycle | PASS | `Tests: 4, Passed: 4, Failed: 0, Skipped: 0` |
| Typed executor/provisioner/cleanup using Fake Adapter | PASS | `Tests: 4, Passed: 4, Failed: 0, Skipped: 0` |
| Schema/row comparison primitives and truthful NOT VERIFIED state | PASS | `Tests: 3, Passed: 3, Failed: 0, Skipped: 0` |
| Auth/CSRF/API policy, scoped receipt and offline-only UI | PASS | `Tests: 3, Passed: 3, Failed: 0, Skipped: 0` |
| Restore Integration JavaScript behavior | PASS | `PASS restore integration UI remains live-disabled and sanitizes states` |
| Shared lock migration checker with isolated files | PASS | `quiescent`, exit 0 |
| PHP and JavaScript syntax | PASS | wrapper completed all selected PHP lint checks and `node --check public/assets/restore-integration.js` |
| Forbidden API/SQL scan | PASS | no `multi_query`, broad database discovery/drop, shell/mysql CLI execution path under Restore Integration production code |

### Directly Affected Existing Tests Reused

| Existing evidence | Result | Reason rerun |
|---|---|---|
| Phase 3 SQL safety | PASS — `7/7` | `StructuralSqlParser` now consumes the shared lexer |
| Phase 2 job store/guard | PASS — `5/5` | Backup global lock now delegates to the shared database-operation lock |
| Phase 2 sequential runner | PASS — `5/5` | Shared global serialization dependency changed |
| Routing/shared views | PASS — `4/4` | Added explicit Restore Integration route/navigation/view |
| Security foundation | PASS — `10/10` | Added authenticated CSRF policy and cleared Integration session ownership on logout |

### Implemented Offline Safety Evidence

- Source allowlist is exactly `autoprintdb`, `drugmap_db`, `drug_stock_check`, and `prepack_labels`; the policy rejects every other source name.
- Grant fixtures prove rejection of root, global grants, unescaped wildcard scopes, cross-role/source privileges, and protected-schema grants. Database underscores must be escaped under the active grant-pattern dialect before evidence can be accepted.
- Source CREATE/USE statements compile to non-executable metadata. Only typed allowlisted statements implement `ExecutableRestoreStatement`; schema-qualified, dynamic, DEFINER, database-drop, unknown and stored-program SQL is rejected fail-closed.
- Ownership journal records are append-only, revisioned and SHA-256 chained. Cleanup requires exact run, target, job, server, marker, held-lock, session ownership and creation evidence; prefixes and wildcard names grant no authority.
- Backup and Restore share one host-local operation lock. Existing Phase 2 per-job behavior remains unchanged.
- The mysqli Restore connection factory and Restore Integration runner throw `live_restore_execution_disabled`; there is no enabling configuration flag. Offline executor tests use `FakeRestoreConnection` only.
- `authorize`, `execute`, and `cleanup` API actions are hard-blocked. The UI exposes Offline Proposal/status only and has no Live Restore control or percentage.

### NOT VERIFIED / Remaining Offline Limits

- Laragon MySQL/MariaDB product/version, stable server identity, dedicated users, actual grants/roles/PUBLIC/`partial_revokes`, exact source capabilities and exact target absence are **NOT VERIFIED**.
- Live Backup, Live Restore, Live schema/data comparison, Live Cleanup, target creation/deletion and post-cleanup absence are **NOT VERIFIED** and were not authorized.
- `PostRestoreValidator` deliberately keeps mandatory data comparison `not_verified`: the current typed plan does not yet retain lossless typed row evidence from INSERT statements. No job can truthfully reach `succeeded` through this path.
- Stored routines, triggers, events and general view definitions remain unsupported/ineligible. No parser rule was weakened to accept them.
- The shared lexer currently loads an artifact into PHP memory before lexing. The per-statement ceiling remains 16 MiB, but bounded-memory behavior for very large eligible artifacts is **NOT VERIFIED**.
- No unfiltered full regression was run. Existing Phase 1–4 tests were rerun only where a shared dependency changed.

### Database and Delivery Safety Record

All new verification used literal SQL/grant fixtures, Fake/recording adapters, temporary filesystem storage, local file locks, rendered views and session fixtures. No MySQL/MariaDB connection or SQL command was initiated by the verification workflow. No credential was provisioned, no user/grant/database/service/configuration was changed, no existing backup was deleted or overwritten, and Milestones 10–11 were not started. No commit, push, tag or release was performed.

---

## Restore Integration Extension — Milestone 9 Remediation

วันที่ตรวจสอบ: 2026-10-17  
Scope: Readiness blockers only; Milestones 10–11 not started  
Runtime: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`

### Change-Based Verification

| Verification | Result | Observed evidence |
|---|---|---|
| Streaming typed SQL plan/compiler | PASS | `Tests: 6, Passed: 6, Failed: 0, Skipped: 0` — chunk-boundary state, exact streamed SHA-256/bytes, completion marker, truncation, statement/file limits and quoted/commented oversized input |
| Source/identity/privilege safety | PASS | `Tests: 7, Passed: 7, Failed: 0, Skipped: 0` — exact four-source catalog, native stable identity, separated scopes, role/PUBLIC/partial-revokes fail-closed evidence |
| Ownership and cleanup decisions | PASS | `Tests: 3, Passed: 3, Failed: 0, Skipped: 0` — chained journal, marker secrecy/recovery and exact ownership authorization |
| Shared operation lock | PASS | `Tests: 2, Passed: 2, Failed: 0, Skipped: 0` |
| Restore Integration job persistence | PASS | `Tests: 4, Passed: 4, Failed: 0, Skipped: 0` |
| Typed executor, sealed adapter contract and offline runner | PASS | `Tests: 9, Passed: 9, Failed: 0, Skipped: 0` — immutable validated statements, stateful-payload TOCTOU regression, new-target provisioning, retained unverified target, cleanup and lock-release failure path |
| Lossless row/schema evidence | PASS | `Tests: 5, Passed: 5, Failed: 0, Skipped: 0` — NULL/empty, binary bytes, Thai UTF-8, escapes, high-precision decimal text, datetime(6), multiple INSERTs, duplicate multiplicity, 4,200-row external-sort evidence and literal whitespace/case preservation |
| Auth/CSRF/receipt/UI safety | PASS | `Tests: 3, Passed: 3, Failed: 0, Skipped: 0` — hard-disabled live actions and corrupt/truncated durable receipt rejection |
| Directly affected Phase 3 SQL safety | PASS | `Tests: 7, Passed: 7, Failed: 0, Skipped: 0` |
| Directly affected Phase 2 lock/runner | PASS | guard/store `5/5`; sequential runner `5/5` |
| Directly affected routing/security | PASS | routing/shared views `4/4`; security foundation `10/10` |
| Restore Integration JavaScript | PASS | `PASS restore integration UI remains live-disabled and sanitizes states` |
| Focused wrapper, syntax and forbidden-path scan | PASS | 70 selected PHP tests plus 1 JavaScript behavior test; PHP/JS syntax passed; no multi-query, broad discovery/drop, shell or mysql-client execution path |

### Remediation Evidence

- File artifacts are read in bounded chunks (64 KiB default), hashed while streaming and parsed with state retained across chunks. A statement is limited to 16 MiB, an artifact to 64 MiB, a plan to 20,000 items and an INSERT to 10,000 rows. Large statement bodies are kept in a private file-backed spool rather than retained together in the plan.
- Artifact and target data are compared as a duplicate-preserving multiset digest: each typed row is length-delimited, SHA-256 hashed, externally sorted in bounded runs and folded into a deterministic table digest. NULL is distinct from zero-length bytes; non-NULL values are never converted through PHP float/int.
- Schema evidence now retains whitespace and character case inside quoted literals while canonicalizing SQL syntax outside literals. The regression distinguishes `DEFAULT 'a  b'` from `DEFAULT 'a b'` and `enum('A','a')` from `enum('a','a')`.
- Execution adapters accept only `ValidatedExecutableStatement`. The original statement payload is read exactly once, validated against the strict structural parser and frozen before adapter delivery; a stateful payload cannot swap safe SQL for later unsafe SQL.
- The production mysqli adapter, role-separated factory, attestation service and orchestration contract exist, but all production construction/execution entry points throw `live_restore_execution_disabled`. No flag, environment option or HTTP request can issue a live connection in this build.
- Provisioning failures after exact target creation record `target_may_exist` and retain the target when ownership cannot be proven. Cleanup remains exact-name only, rechecks server/selected database/marker and releases the shared lock even when cleanup itself throws.
- Authorization receipt journals reject malformed or non-newline-terminated records instead of falling back to an older usable record.

### Targeted Security Review

- An independent read-only reviewer was invoked and identified statement-payload TOCTOU, quoted-literal schema normalization, cleanup lock release, provisioning-retention reporting and receipt-tail handling. Regression tests were added and the findings were remediated.
- Final independent closure review is **NOT VERIFIED** because the reviewer tool exhausted its usage allowance before returning a final result. A self-review and focused static scan were performed instead; no additional Critical/Important issue was found in the authorized scope.

### NOT VERIFIED / Remaining Risks

- Milestone 10 readiness remains **NOT READY for live execution**. Actual Laragon MySQL/MariaDB product/version, native stable identity, dedicated credentials, grants, roles, MySQL `partial_revokes`, MariaDB PUBLIC semantics, source readability, exact target absence and isolation are **NOT VERIFIED**.
- Live Backup, Live Restore, live schema/data comparison and live cleanup are **NOT VERIFIED**. The mysqli constructor/factory/runner remain deliberately sealed.
- Stored programs, triggers, events, general view bodies, custom delimiters, schema-qualified references, dynamic SQL, DEFINER and unrecognized literals/statements remain unsupported/ineligible and fail closed.
- A single statement over 16 MiB, artifact over 64 MiB, INSERT over 10,000 rows or plan over 20,000 items is deliberately rejected. This is a safety limit, not a successful compatibility claim.
- Target values whose server representation cannot be proven byte-equivalent are reported `not_verified`/failed closed; no lossy semantic normalization is used.
- The legacy Phase 2 lock fallback remains for compatibility; migration to an exclusively shared lock path must be verified before any live authorization.

### Database and Delivery Safety Record

All tests used synthetic SQL, Fake/recording adapters, temporary files/directories and local file locks. No MySQL/MariaDB connection was opened, no SQL was sent, no database (including `jhcisdb`) was read or modified, no credential/user/grant/database was created or removed, and no existing backup was changed or deleted. No commit, push, tag or release was performed.

---

## Restore Integration Extension — Milestone 10B Readiness Preparation

วันที่ตรวจสอบ: 2026-10-17  
Scope: Offline readiness checker, runbook และ credential provisioning plan เท่านั้น  
Runtime: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`

### Implemented

- เพิ่ม pure typed Readiness Checker สำหรับ expected endpoint/native identity, MySQL version/dialect, dedicated credential separation, exact privilege scope, wildcard semantics, active roles/`partial_revokes`, source allowlist, target isolation, shared-lock readiness และ restore hard-disable
- เพิ่ม plan-only CLI ที่ไม่รับ credential/connection argument, ไม่เปิด database connection และสร้าง run ID/target proposal แบบ exact
- เพิ่ม runbook และ Dedicated Credential Provisioning Plan ที่แยก Credential Provisioning Authorization, Read-only Readiness Authorization และ Live Integration Authorization ออกจากกัน
- เพิ่ม exact endpoint guard สำหรับ `127.0.0.1:3306` และ explicit block สำหรับ `127.0.0.1:3333`

### Offline Run Proposal

Proposal นี้เป็นหลักฐาน plan-only ไม่ใช่ live authorization:

- Run ID: `b0108a9219ca1f50f8b5490f73d28f4c`
- `restore_test_autoprintdb_b0108a9219ca1f50f8b5490f73d28f4c`
- `restore_test_drugmap_db_b0108a9219ca1f50f8b5490f73d28f4c`
- `restore_test_drug_stock_check_b0108a9219ca1f50f8b5490f73d28f4c`
- `restore_test_prepack_labels_b0108a9219ca1f50f8b5490f73d28f4c`

ยังไม่มีการตรวจว่าชื่อ target เหล่านี้ไม่มีอยู่จริง และห้าม provision grant/create/drop โดยอาศัย proposal นี้จนกว่าจะได้รับ authorization ขั้นถัดไป

### Change-Based Verification

| Verification | Result | Observed evidence |
|---|---|---|
| Focused readiness checker/CLI | PASS | `Tests: 8, Passed: 8, Failed: 0, Skipped: 0` — fail-closed plan mode, exact scope, privilege/server identity binding, complete typed evidence, port 3333 drift, extra source, missing credential, target collision, lock/gate failure และ CLI argument refusal |
| Directly affected identity/privilege attestation | PASS | `Tests: 7, Passed: 7, Failed: 0, Skipped: 0` |
| Changed PHP syntax | PASS | 6 changed PHP files linted without errors |
| Plan-only command | PASS | exit 0; `overall_status=blocked`, live evidence checks `not_verified`, restore safety gate `pass`, live operations `hard_disabled` |
| Offline forbidden-operation scan | PASS | readiness production scope: `0` database/provisioning/socket/client operation hits, `0` protected-schema-name hits; explicit port-3333 block contract present exactly once |
| Existing live safety seals | PASS | mysqli constructor, connection factory, integration runner และ HTTP authorize/execute/cleanup paths ยังคืน/throw `live_restore_execution_disabled` |

ไม่ได้รัน Full Regression และไม่ได้ทดสอบ Phase 1–4 ซ้ำ เพราะไม่มี dependency อื่นได้รับผลกระทบ

### Reused Milestone 10A Evidence

- ใช้หลักฐาน OS/file metadata เดิมว่า Laragon binary เป็น MySQL 8.4.3, expected listener/config คือ port 3306 และมี listener อื่นที่ port 3333 ซึ่งต้องห้าม
- ใช้ artifact metadata เดิม: ไฟล์ที่พบสำหรับสาม source ไม่เกิน 64 MiB; directory ของ `prepack_labels` ยังไม่พบ
- ไม่ตรวจ process/listener/config/artifact ซ้ำ และไม่เชื่อมต่อ MySQL

### NOT VERIFIED / Remaining Blockers

- Native `@@server_uuid`, exact runtime version/dialect และ same-server identity จาก dedicated accounts ยัง **NOT VERIFIED**
- `backup_reader` และ `restore_runner` ยังไม่ได้ provision; credential availability/separation และ current-account resolution ยัง **NOT VERIFIED**
- Actual grants, wildcard escaping/effective semantics, active/default roles และ MySQL `partial_revokes` ยัง **NOT VERIFIED**
- Exact source capability ทั้งสี่ชื่อและ exact target absence/isolation ทั้งสี่ชื่อยัง **NOT VERIFIED**
- Shared legacy/shared lock atomic quiescence ยัง **NOT VERIFIED**
- Artifact set ของ `prepack_labels` ยังไม่ครบจากหลักฐาน metadata เดิม; live artifact compatibility/completeness ยัง **NOT VERIFIED**
- Independent security closure ของ remediation เดิมยัง **NOT VERIFIED**
- Live Backup, Restore, Compare และ Cleanup ยังคง **NOT AUTHORIZED / NOT VERIFIED**

### Database and Delivery Safety Record

การตรวจทั้งหมดใช้ fixture/typed objects, local PHP process และ static source scan เท่านั้น ไม่มี MySQL/MariaDB connection, SQL execution, backup execution, user/grant/database creation/deletion หรือ service/configuration change ไม่ได้เข้าถึงฐานข้อมูลใด และไม่ได้แตะ `jhcisdb` ไม่มี backup เดิมถูกแก้หรือลบ และไม่มี commit, push, tag หรือ release

---

## Seamless Editorial Application Layout

วันที่ตรวจสอบ: 2026-10-17  
Scope: Shared layout, six primary views, responsive CSS และ affected UI contracts เท่านั้น  
Apache URL: `http://localhost:8081/backUP-phpmyadmin-fordev/`

### Implemented UI Evidence

- Shared shell ใช้ compact page header, unified canvas, consistent content width/spacing และ thin editorial dividers
- Dashboard hero ถูกถอดออก; connection/CTA อยู่ใน page header, metrics เป็น divider strip และ Recent Jobs/Restore Safety เป็น continuous sections
- Backup เรียง Connection → Database Selection → Sequential Job Status โดยคง form actions, CSRF และ polling hooks
- Restore เรียง fail-closed status → preflight → findings → history โดยไม่มี live execution control
- Restore Lab เรียง offline safety → exact proposal → isolation readiness และคง `live_execution_enabled="false"`
- History ใช้ toolbar/table เต็มความกว้าง และ table overflow อยู่ใน labelled scroll region
- Settings เปลี่ยนจาก card grid เป็น Connection → Storage → Security → Environment sections แนวตั้ง
- Card isolation คงไว้เฉพาะ login/dialog และ controls ที่จำเป็น; production UI JavaScript ไม่ต้องเปลี่ยน

### Change-Based Verification

| Verification | Result | Observed evidence |
|---|---|---|
| Shared/page markup and accessibility contracts | PASS | `Phase4UiTest: 10/10` |
| Shared routing/navigation/password rendering | PASS | `RoutingAndViewTest: 4/4` |
| Affected Backup UI/API contract | PASS | `Phase2JobApiAndUiTest: 5/5` |
| Affected Restore UI/API contract | PASS | `Phase3ApiAndUiTest: 2/2` |
| Restore Lab UI/API safety contract | PASS | `RestoreIntegrationApiAndUiTest: 3/3` |
| Responsive/accessibility browser verification | PASS | Dashboard, Backup, Restore, Restore Lab, History และ Settings ที่ 1440x1000, 820x900, 390x844: exact route/active nav, one `h1`, editorial section count, no legacy card/hero, no page scrolling, no clipped heading/button/status, visible focus ≥2px, control height ≥44px, correct mobile navigation และ contained history table |
| Affected UI JavaScript behavior | PASS | Restore polling และ Restore Lab hard-disabled behavior scripts passed |
| PHP/JavaScript syntax | PASS | 7 modified PHP view files linted; shared/page UI scripts and responsive test passed `node --check` |
| Apache route smoke | PASS | ทั้งหก route ตอบ HTTP 200 ผ่าน Laragon Apache |
| Visual QA | PASS | ตรวจ Dashboard, Backup, Restore, Restore Lab, History และ Settings ใน browser ผ่าน Apache; ตรวจ Dashboard desktop 1440px และ mobile 390px เพิ่มเติมโดยไม่ submit form |

### Responsive Notes / Remaining Visual Limits

- History บนหน้าจอแคบใช้ horizontal scroll ภายใน labelled table region ตาม design; document ไม่เลื่อนในแนวนอน
- Browser visual QA ใช้ current empty/session states; populated jobs/databases และ long text ใช้ deterministic render fixtures/overflow assertions แต่ไม่ได้เริ่มงานจริง
- ไม่ทดสอบ authenticated remote/LAN appearance หรือ login submission เพราะ authentication behavior ไม่ได้เปลี่ยน
- ไม่รัน Full Regression และไม่ทดสอบ backend ที่ไม่ได้รับผลกระทบ

### Database and Delivery Safety Record

ไม่มี form ถูก submit ระหว่าง browser inspection ไม่มี MySQL/MariaDB connection หรือ database operation ไม่ได้อ่าน/สำรอง/แก้ไข/restore/drop ฐานข้อมูลใด รวมถึง `jhcisdb` ไม่ได้แก้ Backup/Restore engine, parser, executor, lock, authentication, CSRF, storage/download security หรือ safety gate และไม่มี commit, push, tag หรือ release

---

## Restore Integration — Dedicated Credential Provisioning Preparation

วันที่ตรวจสอบ: 2026-10-18
Scope: Offline typed credential plan, reviewed DBA procedure และ focused safety tests เท่านั้น  
Runtime: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`

### Implemented

- เพิ่ม typed/redacted provisioning plan สำหรับ run `b0108a9219ca1f50f8b5490f73d28f4c` โดยตรึง endpoint `127.0.0.1:3306`, block port 3333, exact account identities, exact four source scopes และ exact four target scopes
- database grant scope ทุกชื่อ escape `_` เป็น literal และไม่มี wildcard/prefix/global scope, role หรือ `GRANT OPTION`
- เพิ่ม plan-only CLI ที่ต้องรับ exact 32-hex run ID และปฏิเสธ live/credential arguments
- เพิ่ม reviewed DBA procedure สำหรับ account lock, server-generated credential, exact grants, verification, OS-protected storage และ ownership-verified rollback
- บันทึก `TRIGGER` เป็น dual-use metadata risk; ไม่เพิ่ม `EVENT`, `EXECUTE` หรือ global `SHOW_ROUTINE` จึงคง events/routines เป็น blocker หากพบหรือพิสูจน์ absence ไม่ได้

### Change-Based Verification

| Verification | Result | Observed evidence |
|---|---|---|
| New provisioning plan/CLI | PASS | `Tests: 4, Passed: 4, Failed: 0, Skipped: 0` — exact identities/scopes, wildcard escaping, least-privilege profiles, redacted non-executable output, required run ID และ live-argument rejection |
| Directly affected readiness checker | PASS | `Tests: 8, Passed: 8, Failed: 0, Skipped: 0` |
| Directly affected identity/privilege attestation | PASS | `Tests: 7, Passed: 7, Failed: 0, Skipped: 0` |
| Changed PHP syntax | PASS | provisioning class, CLI และ focused test: 3/3 files linted without errors |
| Plan-only command | PASS | exit 0; `mode=provisioning_preparation_only`, `authorization=not_authorized`, `live=hard_disabled`, endpoint 3306, reader/runner exact scope count 4/4 |
| Offline forbidden-operation scan | PASS | production provisioning scope: `0` mysqli/PDO/socket/account/GRANT/REVOKE/database operation hits; explicit port-3333 blocker exactly 1 |

ไม่ได้รัน Full Regression และไม่ได้ทดสอบ Phase 1–4 ซ้ำ เพราะ production change ใหม่เป็น pure plan projection/CLI เท่านั้น และ focused readiness/attestation dependencies ผ่านแล้ว

### NOT VERIFIED / Remaining Blockers

- Account ทั้งสองยังไม่ได้สร้าง/แก้ไข/ปลดล็อก; credential availability และ `CURRENT_USER()` exact resolution ยัง **NOT VERIFIED**
- Native server identity, endpoint binding, actual grants, roles, `partial_revokes`, `skip_name_resolve`, source capabilities, source object-family inventory, target absence และ shared-lock quiescence ยัง **NOT VERIFIED**
- `TRIGGER` เป็น MySQL dual-use privilege ที่จำเป็นกับ BackupService metadata path ปัจจุบัน แต่สามารถใช้สร้าง/ลบ trigger ได้ จึงต้องมี explicit live risk acceptance ก่อน provisioning
- events/routines ไม่ได้รับ privilege เพราะ `EVENT`/`EXECUTE`/global `SHOW_ROUTINE` ขัด read-only/exact-scope boundary; หาก source ใดมี object เหล่านี้หรือพิสูจน์ absence ไม่ได้ ให้ STOP
- Windows OS secret-provider integration ยังไม่ได้ implement/authorize; ห้าม fallback ไป plaintext config
- Live Backup, Restore, Compare, Cleanup และ Credential Rollback ยังคง **NOT AUTHORIZED / NOT VERIFIED**

### Database and Delivery Safety Record

การตรวจทั้งหมดใช้ typed objects, local PHP process และ static source scan ไม่มี MySQL/MariaDB connection หรือ SQL execution ไม่มี user/grant/database ถูกสร้าง แก้ไข หรือลบ ไม่มี backup/restore ไม่มี service/configuration change ไม่ได้เข้าถึงฐานข้อมูลใด รวมถึง `jhcisdb` และไม่มี commit, push, tag หรือ release

---

## Windows Secret Provider — Offline Implementation

วันที่ตรวจสอบ: 2026-10-18  
Scope: DPAPI CurrentUser adapter, authenticated envelope, private atomic store, fixed PowerShell worker และ provider orchestration เท่านั้น

### Implemented

- exact role/server/Windows identity domain และ versioned envelope ที่ bind role, `127.0.0.1:3306`, MySQL 8.4.3, native fingerprint, account/SID และ timestamp
- metadata-derived DPAPI entropy พร้อม protected inner metadata และ constant-time inner/outer comparison
- fixed worker operations ผ่าน bounded STDIN/STDOUT; ไม่มี secret ใน argument, environment หรือ plaintext temp file
- create-only per-role envelope store ภายใต้ `storage/private/restore-integration-secrets`, ACL hardening contract, same-directory exclusive temp, flush/rename และ failure cleanup
- provider lifecycle แบบ fail-closed; ไม่มี plaintext fallback และไม่มี wiring ไป MySQL factory/runner/API
- identity-only CLI ซึ่งปฏิเสธ secret/live argument

### Change-Based Verification

| Verification | Result | Observed evidence |
|---|---|---|
| New envelope/storage/worker/provider suites | PASS | `Tests: 18, Passed: 18, Failed: 0, Skipped: 0` — wrong SID/role/server, tampered metadata/ciphertext, missing secret, worker failure, timeout/output bound, ACL/atomic failure และ sentinel leakage |
| Directly affected executor hard-disable | PASS | `Tests: 9, Passed: 9, Failed: 0, Skipped: 0` |
| Directly affected HTTP/UI live-action hard-disable | PASS | `Tests: 3, Passed: 3, Failed: 0, Skipped: 0` |
| PHP syntax | PASS | 21 new/changed PHP files linted without errors |
| PowerShell static syntax | PASS | AST parser: `AST_ERRORS=0`; dynamic-command scan: 0 hits |
| Fixed boundary/static safety | PASS | live factory/runner/API wiring: 0 hits; MySQL/account/database/protected-schema operation scan in production secret-provider scope: 0 hits |
| Identity CLI argument safety | PASS | secret-like argument rejected with exit 2 before worker invocation |
| Native worker execution | BLOCKED / NOT VERIFIED | Superseded diagnosis: `RemoteSigned` เป็น Codex PowerShell 7 context; production Windows PowerShell 5.1 มีทุก scope `Undefined` และ effective `Restricted`; fixed script ยัง `NotSigned` และไม่ถึง DPAPI/ACL operation |

ไม่ได้รัน Full Regression ตาม Change-Based Testing policy และไม่ได้ทดสอบ backend/UI ที่ไม่เกี่ยวข้อง

### Remaining Blockers / Risks

- Actual DPAPI CurrentUser protect/unprotect, real NTFS ACL result และ Apache-context retrieval ยัง **NOT VERIFIED** เพราะ script execution ถูก policy ปฏิเสธ
- ต้องมี security-reviewed code-signing/deployment decision; implementation ไม่ใส่ `-ExecutionPolicy Bypass` และไม่เปลี่ยน machine policy
- ต้องยืนยัน CLI/Apache account และ SID ซ้ำหลัง deployment/restart ก่อน enrollment; identity drift ต้อง fail closed
- PHP copy-on-write ทำให้ memory zeroization เป็น best effort เท่านั้น จึงต้องจำกัด lifetime ของ revealed secret
- `TRIGGER` privilege/dump compatibility risk คงตาม provisioning plan; ไม่มีการเปลี่ยน grant หรือ parser policy
- Credential provisioning, live MySQL readiness, backup/restore/compare/cleanup ยังคง **NOT AUTHORIZED / NOT VERIFIED**

### Database and Delivery Safety Record

ไม่มี MySQL/MariaDB connection, SQL execution, credential provisioning, user/grant/database operation, backup/restore หรือ service/configuration change ไม่ได้เข้าถึงฐานข้อมูลใด รวมถึง `jhcisdb` และไม่มี commit, push, tag หรือ release

---

## Windows Secret Provider — Worker Protocol Fix

วันที่ตรวจสอบ: 2026-10-18  
Scope: fixed PowerShell worker JSON protocol และ deployment architecture decision เท่านั้น

### Change-Based Verification

| Verification | Result | Observed evidence |
|---|---|---|
| TDD RED | EXPECTED FAIL | ก่อนแก้ script: empty `{}` identity ได้ exit 9 แทน 0; negative cases 4/4 ผ่าน; ก่อนแก้ adapter: reordered valid identity/ciphertext JSON ถูก reject 2 cases |
| Real PowerShell 7 worker protocol | PASS | `Tests: 5, Passed: 5, Failed: 0, Skipped: 0` — empty object, missing fields, unexpected fields, malformed JSON และ invalid operation |
| Combined affected worker/process contracts | PASS | `Tests: 9, Passed: 9, Failed: 0, Skipped: 0` |
| Changed PHP syntax | PASS | adapter และ focused worker tests lint ผ่าน 3/3 files |
| PowerShell syntax | PASS | AST parser ไม่พบ syntax error |

### Architecture Boundary

- จัดทำ recommendation ให้ใช้ .NET 10 LTS self-contained `win-x64` helper แต่ยังเป็น design decision ที่รออนุมัติ
- ไม่มี .NET project/executable, deployment directory, certificate, trust-store หรือ production adapter change ถูกสร้างในรอบนี้
- PowerShell 5.1 production path ยังคง fail closed ภายใต้ effective `Restricted`

### Database and Delivery Safety Record

ไม่มี MySQL connection, credential provisioning, user/grant/database operation, backup/restore, Windows policy/certificate/trust-store change หรือการเข้าถึง `jhcisdb` และไม่มี commit, push, tag หรือ release

---

## Windows Secret Provider — Native .NET DPAPI Helper Offline Implementation

วันที่ตรวจสอบ: 2026-10-18  
Scope: .NET 10 `win-x64` helper, PHP native adapter, fixed ProgramData store และ signed-deployment gate เท่านั้น

### Implemented

- เพิ่ม native .NET 10 helper รองรับ exact operations `identity`, `protect`, `unprotect`, `harden-directory`, `harden-file`
- strict/bounded JSON STDIN/STDOUT, DPAPI `CurrentUser`, exact SID precheck, stable safe error codes และ ACL allowlist เฉพาะ fixed ProgramData secret paths
- publish profile เป็น Release, self-contained, single-file, `win-x64`, no trimming และ no NativeAOT
- เพิ่ม PHP adapter ที่เรียกเฉพาะ `C:\ProgramData\BackupPhpMyAdmin\SecretProvider\backup-secret-helper.exe` ด้วย allowlisted operation หนึ่ง argument; ไม่มี arbitrary executable/argument และไม่มี PowerShell fallback
- เพิ่ม production deployment verifier ที่ fail closed ด้วย `native_helper_deployment_unverified` จนกว่า signed artifact hash/publisher/thumbprint pins ผ่าน authorized review
- เพิ่ม `NativeSecretEnvelopeStore` ภายใต้ fixed ProgramData path และใช้ atomic create/ACL core เดียวกับ offline file store
- PowerShell sourceเดิมคงไว้เป็น historical protocol evidence เท่านั้น; production identity CLI เปลี่ยนเป็น native adapter

### Change-Based Verification

| Verification | Result | Observed evidence |
|---|---|---|
| TDD RED | EXPECTED FAIL | native unit projectไม่มี `Main`/protocol implementation; PHP adapter classไม่พบ; native store classไม่พบ ก่อน implement ตามลำดับ |
| .NET protocol/path unit tests | PASS | `Passed: 12, Failed: 0, Skipped: 0` บน .NET SDK 10.0.402 |
| Native executable protocol + synthetic DPAPI | PASS | `Tests: 4, Passed: 4` — identity, DPAPI CurrentUser round-trip, wrong SID และ malformed/missing/unexpected/invalid operation |
| PHP native adapter/trust gate | PASS | `Tests: 4, Passed: 4` — fixed path/operation, STDIN-only secret, unverified deployment blocks before process และ safe output failure |
| Affected envelope/provider/storage/process contracts | PASS | `Tests: 17, Passed: 17` — role/server/SID/tamper, no plaintext fallback, fixed native store, atomic/ACL failures, timeout/output bounds |
| PHP syntax | PASS | 11 changed PHP production/test/CLI files linted without errors |
| Production hard block | PASS | identity CLI returns `native_helper_deployment_unverified` exit 1; production executableและ secret directoryไม่ถูกสร้าง |
| Apache source/build isolation | PASS | direct request ไป `native/artifacts/.../backup-secret-helper.exe` ผ่าน Laragon Apache ตอบ HTTP 403 |
| Offline publish profile | PASS | executableขนาด 73,604,645 bytes; publish directoryมี executableหนึ่งไฟล์และ external PDBหนึ่งไฟล์; SHA-256 `83029226CF99540FFB2B1FF34704FE5B7AC0AC3ABA346F5BEDAE4BFD43C5186F` |
| Authenticode/certificate readiness | BLOCKED | offline artifact `NotSigned`; usable code-signing certificates = 0; production pins remain empty |
| Forbidden wiring/static scan | PASS | production PowerShell references 0; MySQL/account/database/protected-schema operation referencesใน changed production scope 0 |

ไม่ได้รัน Full Regression และไม่ได้ทดสอบ backend/UI/Phase 1–4 ที่ไม่เกี่ยวข้อง

### NOT VERIFIED / Remaining Blockers

- Signed production deployment, valid Authenticode chain/timestamp, expected publisher/thumbprint และ post-signing artifact hash ยัง **NOT VERIFIED / BLOCKED**
- Protected ProgramData executable ACL, secret-directory ACL และ reparse-point checksบน production paths ยัง **NOT VERIFIED** เพราะยังไม่ได้ deploy
- CLI/Apache SID parity หลัง deploy/restart และ retrieval ภายใต้ Apache security context ยัง **NOT VERIFIED**
- Offline artifact hashข้างต้นเป็น unsigned test artifactเท่านั้น ห้ามนำไปตั้ง production pin หรือใช้กับ credentialจริง
- Credential provisioning, live MySQL readiness, backup/restore/compare/cleanup ยัง **NOT AUTHORIZED / NOT VERIFIED**

### Database and Delivery Safety Record

ไม่มี MySQL/MariaDB connection, SQL execution, credential provisioning, account/grant/database operation, backup/restore, Windows policy/trust-store change หรือ production ProgramData deployment ไม่ได้เข้าถึงฐานข้อมูลใดรวมถึง `jhcisdb` และไม่มี commit, push, tag หรือ release

---

## Native .NET Helper — Signed Deployment Preparation

วันที่ตรวจสอบ: 2026-10-18  
Scope: certificate strategy, deterministic build contract, signature verification และ dry-run/read-only deployment tools เท่านั้น

### Prepared

- เลือก organization-managed code signing เป็นตัวเลือกหลัก และ internal/private CA เป็น fallback ที่เหมาะกับ local/internal deployment; self-signed certificate จำกัดไว้ development เท่านั้น
- กำหนด Code Signing EKU OID `1.3.6.1.5.5.7.3.3`, Digital Signature, SHA-256 Authenticode, RFC 3161 timestamp, non-exportable key และ publisher/thumbprint/post-signing hash pins
- เพิ่ม .NET SDK pin `10.0.402`, locked NuGet dependencies, deterministic/CI Release publish, fixed `win-x64`, single-file, self-contained, no trim/AOT
- เพิ่ม evidence validator, real Authenticode verifier, read-only preflight, JSON dry-run deployment plan, ACL verifier และ post-deployment identity validator
- identity validator ต้องผ่าน signature, publisher, thumbprint, hash และ ACL ก่อนเรียก fixed helper `identity`
- production deployment scriptsไม่มี certificate/trust/path/ACL/file mutation และไม่มี signing-policy bypass

### Change-Based Verification

| Verification | Result | Observed evidence |
|---|---|---|
| TDD RED | EXPECTED FAIL | focused tests 4/4 failed ก่อนมี evidence validator, signature verifier, build tool และ deployment plan |
| Signed deployment preparation tests | PASS | `Tests: 7, Passed: 7, Failed: 0, Skipped: 0` — valid evidence, wrong signature/publisher/thumbprint/EKU/timestamp/hash/reparse, unsigned artifact, malformed evidence, invalid SID, dry-run contract และ absent production fail-closed |
| Directly affected .NET tests | PASS | locked restore succeeded; `Passed: 12, Failed: 0, Skipped: 0` |
| Reproducible publish | PASS | independent publishes produced one executable each and identical SHA-256 `7E7AFB004B47A9CBA26DEE88CF76A1965055788DFBD24063A04E381AE410E1E7` |
| PowerShell syntax | PASS | 9 new `.ps1/.psm1` files parsed with `AST_TOTAL_ERRORS=0` |
| Forbidden mutation/database scan | PASS | 0 hits for policy bypass, certificate creation/import, signing execution, production copy/move/ACL mutation, MySQL/account/database operations และ `jhcisdb` |
| Signing/deployment readiness | BLOCKED | usable code-signing certificates = 0; production executable/secret directory absent; production pins remain empty |
| PHP syntax | PASS | focused PHP deployment test linted without errors |

ไม่ได้รัน Full Regression และไม่ได้รัน secret-provider/backend/UI suitesที่ไม่เปลี่ยน

### NOT VERIFIED / Remaining Authorization Gates

- certificate issuance/import, internal CA trust distribution, private-key custody/HSM และ certificate renewal/rotation ยัง **NOT AUTHORIZED / NOT VERIFIED**
- actual Authenticode signing, RFC 3161 timestamp, publisher/thumbprint validation และ post-signing hash pin update ยัง **BLOCKED**
- ProgramData directory creation, executable deployment, NTFS ACL mutation/verification และ CLI/Apache SID parity ยัง **NOT AUTHORIZED / NOT VERIFIED**
- production secret enrollment, MySQL readiness, credential provisioning, backup/restore/compare/cleanup ยังคง **NOT AUTHORIZED / NOT VERIFIED**

### Database and Delivery Safety Record

ไม่มี MySQL/MariaDB connection, SQL execution, certificate creation/import, trust-store/policy change, ProgramData deployment, ACL mutation, secret enrollment, credential/account/grant/database operation, backup/restore หรือการเข้าถึง `jhcisdb` และไม่มี commit, push, tag หรือ release

---

## Runtime Data Separation — Offline Implementation

วันที่ตรวจสอบ: 2026-10-18  
Scope: centralized runtime-data paths, production path validation, development compatibility และเอกสาร deployment เท่านั้น

### Implemented

- เพิ่ม `runtime_data_mode` และ `runtime_data_root`; development default ยังคงเป็น `<project>\storage`
- derive backup, job, log, restore job, Restore Integration job/ownership, shared database-operation lock, temporary และ application session paths จาก root เดียว
- production mode รับเฉพาะ exact `C:\ProgramData\BackupPhpMyAdmin\RuntimeData`, ต้องมี directory tree ล่วงหน้า และ reject relative/UNC/traversal/unapproved/source-overlap/missing/reparse-backed paths แบบ fail closed
- development bootstrap สร้างเฉพาะ directory ที่ขาดโดยไม่ move/delete/rewrite artifact เดิม; production bootstrap ไม่สร้าง ProgramData
- Backup/Restore lock aliases ชี้ exact `RuntimeData\database-operations\global.lock` เดียวกัน

### Change-Based Verification

| Verification | Result | Observed evidence |
|---|---|---|
| TDD RED | EXPECTED FAIL | focused suite ล้มเหลวเพราะ `RuntimeDataPaths` และ `RuntimeDataBootstrap` ยังไม่มี ก่อนเพิ่ม implementation |
| Runtime path focused suite | PASS | `Tests: 9, Passed: 9, Failed: 0, Skipped: 0` — development default, explicit production root, invalid/UNC/traversal/missing/unapproved root, source overlap, real NTFS junction rejection, exact shared lock, no existing-data mutation และ application session path |
| Shared database-operation lock dependency | PASS | `Tests: 2, Passed: 2, Failed: 0, Skipped: 0` |
| Storage/download dependency | PASS with platform skip | `Tests: 8, Passed: 7, Failed: 0, Skipped: 1`; existing symlink fixtureยังใช้ไม่ได้บน environment นี้ แต่ junction rejectionใหม่ทดสอบจริงและผ่าน |
| Changed PHP syntax | PASS | 6 production/config/test PHP files linted; `LINT_FAILURES=0` |
| Temporary fixture cleanup | PASS | ไม่พบ `runtime-data-*` test residue หลัง suite |
| Existing project storage guard | PASS | ไม่มี directory `restore-integration-jobs`, `restore-integration-ownership`, `database-operations`, `temporary` หรือ `sessions` ถูกสร้างใน project storage ระหว่าง verification |

ไม่ได้รัน Full Regression และไม่ได้รัน Apache/browser/backend/database suites ที่ไม่เกี่ยวข้อง ไม่มีการสั่ง backup/restore

### NOT VERIFIED / Remaining Deployment Gates

- ProgramData root/directory existence, NTFS owner/ACL และ dedicated Apache SID ยัง **NOT VERIFIED**; production mode จะ fail closed จนกว่า authorized deployment สร้าง path ครบ
- Side-by-side Apache service, production application copy, PHP session ภายใต้ service identity และ DPAPI synthetic test ยัง **NOT AUTHORIZED / NOT VERIFIED**
- Native Helper Authenticode/pins และ production credential enrollment ยังคง **BLOCKED**

### Database and Delivery Safety Record

ไม่มี MySQL/MariaDB connection, SQL execution, ProgramData mutation, Windows account/service/ACL change, certificate/trust change, backup/restore หรือการเข้าถึง `jhcisdb` และไม่มี commit, push, tag หรือ release

---

## Dedicated Apache Side-by-Side — Offline Deployment Preparation

วันที่ตรวจสอบ: 2026-10-18  
Scope: standalone Apache/PHP configuration, read-only deployment preflight, service/ACL/migration/rollback/patch runbook เท่านั้น

### Prepared

- fixed service contract: `BackupPhpMyAdminApache`, `.\BackupPhpRuntime`, `127.0.0.1:8788`
- standalone DocumentRoot `C:\Program Files\BackupPhpMyAdmin\Application\public`; ไม่มี Laragon Include/Alias/VirtualHost หรือ filesystem access ไป `C:\laragon\www`
- dedicated PHP ini แยก session/temp/log ภายใต้ exact RuntimeData และเปิดเฉพาะ `mysqli`/`sodium`
- semantic config validator, strict bounded preflight-evidence validator และ read-only local preflight collector
- exact owner/ACL matrix, path-squatting/reparse controls, copy-only migration, non-destructive rollback และ Apache/PHP patch lifecycle

### Change-Based Verification

| Verification | Result | Observed evidence |
|---|---|---|
| TDD RED | EXPECTED FAIL | focused tests `0/3` ผ่านก่อนมี deployment contract, config validator และ preflight tools |
| Dedicated deployment focused suite | PASS | `Tests: 3, Passed: 3, Failed: 0, Skipped: 0` — fixed isolation contract, 12 fail-closed readiness blockers และ forbidden mutation/database operations |
| Apache 2.4.66 syntax | PASS | temporary materialization กับ installed dedicated-compatible binary returned `Syntax OK`; RED probeก่อนแก้พบ Windows-invalid unconditional `User` directive |
| PHP 8.3.30 isolated ini | PASS | real PHP process loaded `mysqli=true`, `sodium=true`, isolated session/temp paths และ `display_errors` off; RED probeก่อนแก้พบ misplaced extension directives/open_basedir runtime omission |
| PHP syntax | PASS | `DedicatedApacheConfigValidator.php` และ focused PHP test: no syntax errors |
| PowerShell syntax | PASS | 3 focused `.ps1` files parsed; `3/3 PASS` |
| Forbidden operation scan | PASS | dedicated deployment tools: 0 hits for account/service/ACL/file mutation, service restart, MySQL/database operation หรือ protected database name |
| Current read-only machine preflight | BLOCKED as designed | destination collisions `false`; NTFS `true`; reparse-free `true`; port 8788 available `true`; account/runtime tree/source manifest/config deployment evidence/shared-Laragon baseline remain `false`/missing; exit `9` |

ไม่ได้รัน Full Regression, ไม่ start/restart Apache, ไม่ทำ HTTP smoke บน 8788 และไม่ทดสอบ backend/database suites ที่ไม่เปลี่ยน

### NOT VERIFIED / Remaining Deployment Gates

- account creation, exact SID, service-logon right, non-interactive restrictions และ service registration/start ยัง **NOT AUTHORIZED / NOT VERIFIED**
- Program Files/ProgramData directory creation, owner/ACL/effective-rights และ final reparse/no-follow validation ยัง **NOT AUTHORIZED / NOT VERIFIED**
- reviewed source package manifest/hash, production copy และ before/after shared-Laragon hash evidence ยัง **NOT VERIFIED**
- actual loopback HTTP pages/APIs, Apache-context PHP session, job interruption behavior และ synthetic DPAPI CurrentUser ภายใต้ service SID ยัง **NOT VERIFIED**
- Native Helper Authenticode/pins, secret enrollment, credential provisioning และ live database integration ยังคง **BLOCKED / NOT AUTHORIZED**

### Safety Record

ไม่มี Windows account/service/ACL/Program Files/ProgramData mutation, ไม่มี Apache start/restartหรือ Laragon config change, ไม่มี MySQL/MariaDB connection, backup/restore, credential/database operation หรือการเข้าถึง `jhcisdb` และไม่มี commit, push, tag หรือ release

---

## Phase 1 — Complete Manual Backup Offline Verification

วันที่ตรวจสอบ: 2026-10-18
Scope: exact source policy, BackupService data fidelity/snapshot constraints, directly affected sequential jobs, shared lock และ artifact publisher เท่านั้น

### Implemented

- ใช้ `AuthorizedSourcePolicy` กลางร่วมกับ Restore Integration โดยอนุญาต exact names เพียง `autoprintdb`, `drugmap_db`, `drug_stock_check`, `prepack_labels`
- database discovery ใช้ exact `information_schema.SCHEMATA` predicate; ไม่มี unrestricted `SHOW DATABASES` ใน production paths
- selection, job create/read, `MysqliBackupOperation`, legacy controller และ `BackupService` ตรวจ source ซ้ำ; forged persisted source ถูก reject ก่อน executor connection
- SQL value encoding แยก `NULL`, empty/text/temporal, validated numeric lexeme และ binary/bit/geometry เป็น hex; unknown field type fail closed
- unbuffered row streaming จำกัด batch 100 rows/4 MiB, reject oversized single-row statement และ free result ทุก error path
- รองรับ successful artifact เฉพาะ base tables ที่มี complete InnoDB metadata; view, MyISAM/unknown engine, trigger, procedure, function และ event เป็น unsupported
- กำหนด source session time zone เป็น UTC แล้วใช้ `REPEATABLE READ` + `START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY`, rollback หลังอ่าน และตรวจ schema fingerprint ก่อน/หลัง
- database default charset/collation และ exact foreign-key DDL จาก `SHOW CREATE TABLE` ถูกเก็บไว้; cross-schema FK ถูก reject
- completion marker, temporary file, manifest bytes/SHA-256 และ create-only atomic publisher เดิมยังเป็น success gate

### Change-Based Verification

| Verification | Result | Observed evidence |
|---|---|---|
| TDD RED | EXPECTED FAIL | source policy `0/5`, SQL fidelity `0/4`, snapshot `0/3`, engine snapshot probe `0/1`; additional metadata/result-cleanup/FK/view safety probes fail ก่อนแก้แต่ละ behavior |
| New manual-backup focused tests | PASS | `Tests: 19, Passed: 19, Failed: 0, Skipped: 0` |
| Directly affected Phase 2 job/store/API tests | PASS | `Tests: 15, Passed: 15, Failed: 0, Skipped: 0` |
| Existing artifact publisher + shared lock | PASS | `Tests: 7, Passed: 7, Failed: 0, Skipped: 0` |
| Shared Restore source-policy dependency | PASS | `Tests: 7, Passed: 7, Failed: 0, Skipped: 0` |
| Focused aggregate | PASS | `Tests: 48, Passed: 48, Failed: 0, Skipped: 0` |
| Changed PHP syntax | PASS | `19` changed/new PHP files; failures `0` |
| Production discovery scan | PASS | `SHOW DATABASES` hitsใน `app/`, `public/`, `config/` = `0` |
| Local live-readiness metadata inspection | BLOCKED | backup-reader envelope absent, signed production helper absent, development backup root present, shared lock file absent, persisted job count `0`; ไม่เปิด MySQL socket |

ไม่ได้รัน Full Regression และไม่ได้รัน UI/Restore executor/DPAPI/Deployment suites ที่ไม่เกี่ยวข้อง

### Snapshot Policy / NOT VERIFIED

- InnoDB row data ใช้ consistent read contract; schema/DDL ไม่ใช่ MVCC snapshot จึงใช้ pre/post fingerprint และ manifest ระบุ `ddl_locking=false`
- `AUTO_INCREMENT` metadata ถูกอ่านก่อน snapshot และไม่ใช้เป็น DDL-drift signal; fidelity ภายใต้ concurrent inserts ยัง **NOT VERIFIED**
- visibility ของ view/stored-program inventory ภายใต้ dedicated least-privilege account ยัง **NOT VERIFIED**; engine จึง fail closed เมื่อมองเห็น object เหล่านี้ และ Live Gate ต้องพิสูจน์ metadata visibility เพิ่ม
- live Laragon MySQL 8.4.3 identity, `CURRENT_USER()`, exact grants/roles/`partial_revokes`, source existence, concurrent-write behavior, real artifact download และทั้งสี่ source ยัง **NOT VERIFIED**
- production source package/manifest เดิมไม่ครอบคลุม working-tree changes รอบนี้และต้อง regenerate/review แยกก่อน deployment

### Live Backup Gate

สถานะ: **OFFLINE COMPLETE / LIVE BLOCKED**

เหตุผล: ไม่มี provisioned/verified `backup_reader` secret, signed Native Helper deployment และ explicit Live Backup Authorization ห้ามใช้ root ทดแทน จึงไม่มี MySQL connection, `SHOW`/`SELECT`, backup execution หรือ database operation ใดเกิดขึ้น

### Database and Delivery Safety Record

ไม่มี MySQL/MariaDB connection, database query, credential provisioning, account/grant/database mutation, backup/restore execution, Windows/Laragon mutation หรือการเข้าถึงฐานข้อมูลใดรวมถึง `jhcisdb`; ไม่มี commit, push, tag หรือ release

---

## Dedicated Apache — Production Source Package Preparation

วันที่ตรวจสอบ: 2026-10-18  
Scope: exact production-source allowlist, content-addressed project-local staging package และ deterministic source manifest เท่านั้น

### Generated Candidate

- build ID: `wt-b5246f66b9fd94a3`
- package version: `2026.10.18.1`
- staging application: `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application`
- artifact count: `166`
- total size: `457,533 bytes`
- deterministic manifest digest: `79fbd7a1fadc10c2737b55bf1f6b0bcba20f1ed5c96855a2a484204d80793f96`
- exact JSON manifest SHA-256: `9d9051d5b81cd9ec03703a89cbb15698bc99a67793f43323af24b39e1500f6d3`
- status: **GENERATED / NOT APPROVED**

Exact allowlist รวม runtime PHP sourceใต้ `app` ยกเว้น deployment tooling, safe config template, public PHP endpoints/assets และ views. Exclude `.git`, root/deployment docs, `app/Deployment`, `config/local.php`, storage/backups/jobs/logs/history/private data, tests, tools, native/build artifacts, SQL dumps, executable/archive/key/certificate formats และไฟล์ที่ไม่ได้ระบุเป็นรายชื่อ

### Change-Based Verification

| Verification | Result | Observed evidence |
|---|---|---|
| TDD RED | EXPECTED FAIL | focused suite `0/2` ผ่านก่อนมี `SourcePackageBuilder` |
| Builder focused suite | PASS | `Tests: 2, Passed: 2, Failed: 0, Skipped: 0` — exact allowlist, deterministic evidence, exclusions, secret rejection, invalid/duplicate path และ immutable collision |
| Secret-scanner regression | RED -> GREEN | initial real build rejected safe `['secret' => '[REDACTED]']`; regression testพิสูจน์ failure แล้วแก้ให้ยอมรับเฉพาะ explicit safe placeholders โดย literal secretอื่นยังถูก reject |
| Independent manifest validation | PASS | embedded digest, exact allowlist ordering, artifact count/size และทุก source/package SHA-256 ตรงกัน; errors `0` |
| Existing deployment preflight source gate | PASS | `source_manifest_trusted=True`, `source_integrity_verified=True` เมื่อ supply exact manifest-file SHA-256; overall preflightยัง exit `9` จาก Windows deployment blockers |
| Package inventory | PASS | actual `166`, extra `0`, missing `0`, forbidden paths `0`, forbidden extensions `0`, executable/archive magic `0` |
| Secret/sensitive scan | PASS | unsafe filename `0`, private-key/access-key/literal-secret content `0` |
| Packaged PHP parse | PASS | `160` PHP files, failures `0` ผ่าน `TOKEN_PARSE` |
| Packaged JavaScript syntax | PASS | `4` JavaScript files, failures `0` ผ่าน `node --check` |
| Reproducible create-only rerun | PASS | build ID และ manifest hashคงเดิม; existing packageถูก verifyและไม่ overwrite |
| Runtime side-effect attribution | VERIFIED EXTERNAL TO PACKAGER | ระหว่าง final audit พบ development runtime directories/session ถูกสร้างเวลา `2026-10-18 12:52:46 +0700`; shared Laragon access logยืนยัน browser `GET /backUP-phpmyadmin-fordev/` เวลาเดียวกัน และ focused package-test rerunไม่สร้าง/แก้ session เพิ่ม จึงคงไฟล์ไว้และไม่ลบ runtime artifactที่แอปเป็นเจ้าของ |

ไม่ได้รัน Full Regression, Apache/MySQL/HTTP, backup/restore หรือ Windows deployment test

### Remaining Review and Deployment Gates

- manifest และ digestข้างต้นยังไม่ได้รับ separate human approval; ห้ามถือว่าเป็น trusted production authorizationโดยอัตโนมัติ
- dedicated Windows account/SID, service-logon right, Program Files/ProgramData tree/ACL, runtime copy, service registration/start และ HTTP/session verificationยัง **NOT AUTHORIZED / NOT VERIFIED**
- Native Helper Authenticode/pins, secret enrollment, credential provisioning และ live database integrationยัง **BLOCKED / NOT AUTHORIZED**

### Safety Record

ไม่มี Program Files/ProgramData, Windows account/service/ACL, Apache/Laragon, certificate/trust หรือฐานข้อมูลถูกเปลี่ยน ไม่มี MySQL connection, backup/restore, secret enrollment หรือการเข้าถึง `jhcisdb`; existing backup/job/historyไม่ถูก move/delete/rewrite และไม่มี commit, push, tag หรือ release

