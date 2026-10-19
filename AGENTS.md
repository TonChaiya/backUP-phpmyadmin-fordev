# AGENTS.md

## Purpose and source of truth

Maintain the existing localhost-only PHP MySQL Export/Import application on Laragon. Read [README.md](README.md), [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md), [docs/LOCAL_USER_GUIDE.md](docs/LOCAL_USER_GUIDE.md) and the latest [TEST_BASELINE.md](TEST_BASELINE.md) entry before changes. The current working tree, including uncommitted work, is authoritative; do not restore retired systems from Git history.

## Stack and active boundaries

- PHP 8.3 on Windows/Laragon Apache; existing vanilla JavaScript/CSS/PHP views, no frontend framework or Composer build required.
- Official `mysqldump.exe` exports; official `mysql.exe` imports. Current executable paths, SHA-256, version **8.4.3** and server UUID are pinned in source; compatibility with arbitrary 8.4 clients is not assumed.
- `public/index.php`: page routing, connection/settings actions and metadata-only History. `public/job.php`: sequential Export jobs. `public/import-job.php`: Import prepare/execute/status. `public/download.php`: authorized artifact download.
- `app/Backup/`: native export adapter, publication/integrity, sequential jobs. `app/Import/`: trusted artifacts, new-target policy, native import, jobs and verification presentation. `app/Database/`: exact source policy, connections, shared lock.
- `app/Http/`: router/controllers. `app/Support/`: bootstrap support, paths, sessions/CSRF, loopback access, logging/download. `app/Restore/`: only two retained History/provenance utilities, **not** a Restore engine.
- `config/app.php` plus optional ignored `config/local.php`; never overwrite the user's local file. `views/` and `public/assets/` share the current UI.
- `tools/provision-local-import.php` defaults to plan-only; `--apply` needs explicit authorization for exact names/actions. The retained read-only acceptance inspector also requires separate live authorization. Do not run either against MySQL as an offline check.

## Development rules

Reuse existing implementations and naming; trace callers before deletion. Prefer the smallest maintainable change. Keep existing UI/data attributes/API URLs coherent. Do not add a custom SQL serializer/parser/executor, duplicate route/workflow, scheduler, framework/container, DPAPI/.NET helper, signing/deployment system or Windows service. Removed architectures are historical, not unfinished features.

## Database and data safety

- **Never access `jhcisdb`.** No queries, inspection, export/import, writes or cleanup.
- Exact read-only sources: `autoprintdb`, `drugmap_db`, `drug_stock_check`, `prepack_labels`. No other source or remote/port3333 fallback.
- Preserve source rows/schema. Use `backup_reader@localhost` for Export and separate exact-target credentials for Import; never root as the routine executor.
- Import only into a new, empty, exactly owned target with server/account/grant checks and explicit confirmation. A different workflow needs explicit authorization and a safety design; do not simply relax checks.
- Never automatically reset credentials, create accounts, expand grants, retry an attempted Import, DROP by prefix/wildcard or delete a target without proven ownership and authorization.
- Preserve all existing SQL/manifests, storage, jobs/history/ownership, local credentials and `config/local.php`. Keep legacy runtime path names; no implicit migration.

## Security

Direct loopback peer only; forwarded headers must not authorize LAN clients. Preserve Session/CSRF/job ownership, single-operation locking, artifact SHA-256/completion/provenance and download tokens. Never expose SQL/private paths directly through HTTP. Never place real credentials/private keys in source, Git, logs, job JSON/manifests, browser/API output or process arguments. Existing protected temporary option files are transient plaintext under restricted NTFS ACL, not encrypted permanent storage. Same-account/admin compromise is outside this local model.

Do not call native clients or connect to MySQL without authorization. Website access is not database-administrator authorization.

## Testing and evidence

Passed + unchanged = reuse recorded evidence. Changed = focused checks of changed code and its actual dependencies. No automatic full regression or repeated live Export/Import/comparison.
- Examples: `php tests/run.php ImportSchemaComparisonTest`, `php tests/run.php LocalManagerPresentationTest`, `node tests/js/local-import-presentation.test.js`.
- `php tests/run.php CleanupDependencyTest` includes isolated route smoke; it must not use original storage or MySQL.
- Lint changed PHP with `php -l path/to/file.php`; check changed JS with `node --check path/to/file.js`; run `git diff --check`.
- A bare `php tests/run.php` selects all suites; do not use it by default. Some process tests exercise Windows temp ACL/native process boundaries; inspect a selected test before running.
- Record actual results, skips and NOT VERIFIED in TEST_BASELINE. Never convert row counts or current-source equality into SQL-snapshot fidelity. Original failed jobs stay failed without a supported transition. Overall historical Import acceptance remains **PARTIAL**.

## Git and continuation

Commit/push only within user-authorized scope. Stage explicit reviewed paths, never `git add .` or `git add -A`. No force push, automatic history rewrite, branch deletion or tag/release. Ignore is not untracking: runtime files already in Git require authorized index-only removal, never local data deletion. Old commits can retain historical SQL; do not claim current-tree hygiene erases history.

For every change: (1) read architecture/evidence, (2) trace affected dependencies, (3) change minimal code, (4) verify only affected behavior safely, (5) update affected documentation/evidence. Report unresolved risks accurately and stop at the requested scope.
