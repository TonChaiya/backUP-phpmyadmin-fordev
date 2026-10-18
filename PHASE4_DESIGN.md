# Phase 4 Design Specification — Modern UX/UI & Final Integration

วันที่: 2026-10-17  
สถานะ: Approved direction — Native PHP progressive enhancement

## 1. Scope and intent

Phase 4 makes the existing manual backup and fail-closed restore-preflight workflows easier to understand and operate on desktop, tablet, and mobile. It changes presentation and read-only aggregation only. Phase 1 authentication/session/CSRF/storage/download controls, the Phase 2 sequential backup executor, and the Phase 3 no-SQL-executor safety gate remain authoritative and unchanged.

No scheduler, automatic retry, queue framework, SQL restore executor, production-database write, dependency framework, or fabricated metric is introduced.

## 2. Information architecture

Every authenticated page uses one shared shell and the existing five-route navigation:

1. Dashboard — operational overview and truthful quick actions.
2. Backup — connection, searchable database selection, confirmation, and persisted job state.
3. Restore — clearly disabled execution, artifact preflight, parser findings, safety gates, and preflight history.
4. History — searchable backup inventory with integrity, provenance, eligibility, download, and preflight actions.
5. Settings — database connection summary, storage, security/access, and runtime environment without secrets.

The shell provides skip navigation, a compact responsive menu, active-page indication, consistent page title/description, flash announcements, and a content-width boundary. Login keeps the same visual language without protected navigation.

## 3. Shared visual system

Design tokens live only in `public/assets/app.css`:

- neutral canvas and surface layers;
- navy/indigo primary actions;
- semantic success, warning, danger, information, and neutral status colors;
- Thai-readable system font stack with clear line height;
- 4/8/12/16/24/32/48 spacing scale;
- 8/12/16 radii, restrained one-level shadows, and consistent borders;
- 44px minimum interactive targets and visible `:focus-visible` treatment;
- subtle transitions disabled under `prefers-reduced-motion: reduce`.

Buttons, fields, status pills, tables, empty states, loading text, and the native confirmation dialog share these tokens. Inline SVG icons are decorative, use a consistent 20px stroke style, and never replace visible labels.

## 4. Responsive behavior

- Desktop (`> 960px`): horizontal navigation and two-column workspaces where useful.
- Tablet (`641–960px`): single-column operational flows and wrapped metrics/actions.
- Mobile (`<= 640px`): menu button controls the navigation, controls become full-width where needed, and tables retain horizontal scroll with a visible region label.

JavaScript is progressive enhancement. Navigation, forms, links, status text, and safety warnings retain meaningful HTML. Keyboard users can open/close the menu and confirmation dialog; focus is returned to the initiating action. Dynamic job messages use polite live regions. Disabled and loading states include text, not color alone.

## 5. Dashboard

The dashboard uses a landing-style hero with two truthful calls to action: start a manual backup and inspect restore safety. It shows only existing data:

- database connection status and session-visible database count;
- current backup-file count and latest backup time;
- recent session-owned backup jobs read from persisted Phase 2 state;
- current Phase 3 restore safety-gate result.

There is no success-rate, duration estimate, percentage, or other derived claim unsupported by persisted data.

## 6. Backup workflow

The existing `job.php`, `BackupJobRunner`, global concurrency lock, and polling lifecycle remain unchanged. The page adds:

- database search/filter;
- select-visible and clear-selection controls;
- an explicit selected-count summary;
- a confirmation dialog listing whether the request targets selected databases or all authorized databases and the exact count;
- clearer queued/running/succeeded/failed/interrupted status rows and persisted summary counts.

Submitting while an active non-terminal job exists stays blocked by the Phase 2 API/session protection. Reloading resumes status for the active persisted job and never creates a job automatically. No percentage progress is shown.

## 7. Restore workflow

The Restore page leads with a persistent warning that SQL execution does not exist and isolation remains unverified. It presents:

- hard-closed safety-gate checks;
- eligible Phase 2-provenance artifacts only in the preflight selector;
- artifact integrity/provenance, parser findings, target validation, and final blocked/failed state from the existing preflight job;
- recent session-owned preflight history from persisted Phase 3 state.

There is no execute button, success state, configuration switch, or wording suggesting SQL execution began. Unsupported syntax remains ineligible and fail-closed.

## 8. History

History remains a read-only inventory. Each row shows database, file/date/size, lightweight integrity state, provenance state, restore eligibility, and available download/preflight actions. Search and status filters run client-side over already-authorized rows.

Opening History performs metadata inspection only. It does not hash large SQL files. Full file bytes, completion marker, manifest checksum, provenance, parser, and target checks remain mandatory immediately before Restore preflight.

## 9. Settings

Settings groups four read-only or existing controls:

- database connection status, host, port, and username (never password);
- existing allowlisted storage path form;
- authentication/access mode, session/CSRF/download protection summary;
- PHP/runtime and restore-execution state.

No Laragon, MySQL/MariaDB, web-server, or operating-system configuration is modified.

## 10. Read-only job presentation contract

`BackupJobAccess::ownedJobIds()` and `RestoreJobAccess::ownedJobIds()` expose validated, bounded, session-owned identifiers only. The front controller reads those IDs through the existing job stores, ignores missing/corrupt historical files without exposing technical details, and supplies presentation arrays to views. This does not alter job state, transitions, execution locks, or executor behavior.

## 11. Error, empty, and loading states

- Empty states state the next safe action.
- Dynamic requests show explicit waiting text and disable only the affected controls.
- API errors remain user-safe and are announced in live regions.
- Persisted terminal failures remain visible with their safe per-database or preflight reason.
- A missing historical job is omitted from the overview; it does not change the active job contract or reveal filesystem details.

## 12. Change-based verification

Automated tests cover session-owned history access, page semantics and truthful data rendering, absent secrets/restore execution controls, and retained route/security contracts. JavaScript syntax and PHP lint are checked. Browser smoke checks cover desktop/mobile shell, search/selection/confirmation, history filters, focus/menu behavior, and restore disabled messaging.

Live backup and live restore integration remain `NOT VERIFIED`; no database is contacted. Existing Phase 1–3 tests are rerun only as the affected integration suite warrants, and results are appended to `TEST_BASELINE.md` without claiming unexecuted checks.

