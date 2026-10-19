# Current Local MySQL Manager Architecture

Full maintained architecture and configuration reference: [ARCHITECTURE.md](ARCHITECTURE.md). This short document is a compact workflow contract; the acceptance report and TEST_BASELINE retain historical evidence separately.

## Runtime graph

- Root `index.php` / Apache rewrite -> `public/index.php` -> `app/bootstrap.php`, Config, RuntimeDataBootstrap, Session, direct-loopback Auth, CSRF -> Router -> Dashboard / Export / Import / History / Settings.
- `public/job.php` -> BackupJobAccess/Store/Runner/ExecutionGuard -> MysqliBackupOperation -> BackupService -> MysqldumpEngine -> fixed MysqldumpCommand/Process -> publisher.
- `public/import-job.php` -> ImportAccess/JobStore/ArtifactVerifier/Policy/Runner -> MysqlImportClient + MysqliImportDatabase -> EffectiveSchemaComparison. ImportResultPresentation reads separate bound acceptance reports without changing job state.
- `public/download.php` -> loopback/session token -> BackupPathPolicy / DownloadAuthorization -> real SHA-256/marker check.
- Shared shell/navigation: `views/layout.php`, `views/partials/navigation.php`; scripts: app.js, backup-jobs.js, local-import.js.

One official Export engine and one official Import client. Interfaces support focused fake tests; they are not extra production engines. No SQL rewriting/custom parser. Retired Restore/Restore Lab routes and their APIs are removed; use Import, not legacy URLs.

## Credentials, server and sources

Local Laragon MySQL 8.4.3, default127.0.0.1:3306. Settings accepts loopback endpoints only, never port3333. Native client hashes/version remain pinned; server UUID/CURRENT_USER bind to the explicitly saved profile. Endpoint/identity edits need confirmation.
Sources follow account-visible schema discovery, not the former four-schema test scope. SQL catalog excludes jhcisdb before returning results; it is always prohibited. System schemas are displayed separately and are not Export/Import eligible. Select All uses only currently eligible visible names; Windows-unsafe names are displayed as unsupported.

Export/Import use the normal account selected in Settings, including deliberately saved root/empty password. Unsaved defaults never connect. Private LocalAppData plaintext JSON has SID/SYSTEM ACL, atomic CAS and no Session password. Blank preserves; explicit Replace/Clear permits empty. Import proves SELECT/INSERT/CREATE/REFERENCES on its generated target; active roles/partial revokes fail closed. Export binds confirmed Session selection, rejects forged job names, rechecks account visibility before table access and enforces selected scope before native argv construction.

No application password/login enables network access. MySQL credentials enter the scoped workflow; no reset/provisioning HTTP action. The CLI provisioning tool defaults to plan-only; executing --apply requires separate exact authorization and fresh collision/identity/grant checks. No administrative credentials enter jobs/logs/manifests. The one-off historical reset tool was removed after its authorized operation; persistent receipts remain untouched.

## Artifacts and execution

Trusted producer contract: pinned official mysqldump version/options/profile, successful application provenance and matching manifest bytes/SHA-256. Only positional source (no --databases/--all-databases), InnoDB structure+rows, no views/routines/triggers/events/non-InnoDB/cross-schema FK. Not a general SQL validator; arbitrary dumps/uploads are not eligible.

Before Import, copy to immutable restricted-ACL private staging and verify streaming hash. mysql.exe uses fixed argv, binary mode, no system command/local infile/reconnect, bounded process output/time and protected temporary credentials; no raw diagnostics to HTTP. The official client handles SQL.
Execution requires saved server/account binding, target grant proof, target absence, CSRF/session ownership, IMPORT confirmation and shared lock. Creates the exact new target without IF NOT EXISTS, records ownership before mysql.exe. No account creation/GRANT, retry/overwrite or DROP/cleanup. Legacy jobs cannot be resumed through the new web workflow. Retain partial targets for investigation.

## Verification and historical evidence

Native exit0, schema, live-source values and snapshot values are separate. The active comparison checks effective schema/DDL and row counts with pre/post source metadata stability; it does not prove every restored value or absence of concurrent writes/DDL.
The separate historical read-only acceptance tool/helper compares bounded lossless row multisets, duplicates, NULL/binary/numeric/time values for supported types. It is retained with its focused tests and the current EffectiveSchemaComparison utility.
The existing original job stays failed. Its separate report proves nine tables/63 rows against the source at inspection time; SQL-snapshot value fidelity remains NOT VERIFIED. Source drift must not be mislabeled as Import corruption. See LOCAL_IMPORT_ACCEPTANCE_2026-10-19.md and TEST_BASELINE.md.

## Storage compatibility and retained shared utilities

Runtime root defaults to project storage; do not migrate/delete existing data. Current paths:
- backups, jobs, logs, sessions, temporary/private
- restore-jobs/local-import (Import jobs/reviews)
- restore-integration-ownership/local-import (exact creation journal)
- database-operations/global.lock (same Export/Import exclusion)
- restore-integration-jobs remains a reserved legacy directory mapping, not active execution.

Production-mode path validation is retained for config compatibility; no dedicated Windows deployment is required.
BackupProvenanceIndex remains in app/Restore because actual Import/History use it. RestoreArtifactValidator retains metadata-only legacy History inspection (no custom Restore preflight/executor). Old SQL files remain downloadable only through existing authorization/integrity rules; unknown/custom profiles are not importable. No provenance backfill.

## Local threat boundary

Direct loopback only, CSRF and session-owned jobs/downloads; app/config/storage/tools/tests/docs denied by Apache. NTFS private temporary storage does not defend against the same Windows account/admin controlling PHP/source. Crash remnants require exact ownership review; never broad wildcard cleanup.
Removal inventory/verification: PROJECT_CLEANUP.md. Historical test entries may name retired files; these are old evidence, not currently runnable dependencies.
