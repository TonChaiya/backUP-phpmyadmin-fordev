# Phase 4 Modern UX/UI & Final Integration Implementation Plan

สถานะ: Implemented and verified on 2026-10-17 (no commit by explicit project constraint)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a responsive, accessible shared UI for the existing Phase 1–3 workflows without changing backup or restore execution behavior.

**Architecture:** Keep the native-PHP front controller and views, add only bounded read-only job-history accessors, compose real presentation data in `public/index.php`, and progressively enhance interactions with dependency-free JavaScript. Centralize the visual system in the existing CSS asset.

**Tech Stack:** PHP 8.3, server-rendered HTML, CSS, vanilla JavaScript, existing file-backed job stores.

**Spec:** `PHASE4_DESIGN.md`

## Global Constraints

- Do not connect to or modify any real database, including `jhcisdb`.
- Do not add SQL restore execution, scheduler, automatic retry, queue framework, or configuration bypass.
- Preserve Phase 1 authentication/session/CSRF/storage/download controls and Phase 2/3 state machines.
- Do not commit, push, tag, release, delete existing backups, or modify Laragon/MySQL/MariaDB configuration.
- Use change-based tests and label live backup/restore integration `NOT VERIFIED`.
- Do not display invented percentages, success rates, estimates, or secret values.

## Review Focus

- A corrupt or missing old job file must not break Dashboard/Restore rendering or leak a filesystem error.
- A mobile navigation toggle must expose correct accessible state and leave every route reachable without pointer-only interaction.
- Filtering must not change which databases are submitted by “all”; the server remains the source of authorized database scope.
- Reloading a page with an active job must resume status only and must never auto-create a second job.
- Restore wording and controls must never imply SQL execution or a succeeded restore.

---

### Task 1: Read-only session job history

**Files:**
- Modify: `app/Backup/BackupJobAccess.php`
- Modify: `app/Restore/RestoreJobAccess.php`
- Test: `tests/Phase4UiTest.php`

**Interfaces:**
- Produces: `BackupJobAccess::ownedJobIds(): array<int,string>` and `RestoreJobAccess::ownedJobIds(): array<int,string>` returning newest-first validated session IDs.
- Consumes: existing bounded session ID arrays.

- [ ] Write tests proving invalid/duplicate IDs are excluded, order is newest-first, and the result remains bounded.
- [ ] Run `php tests/run.php Phase4Ui` and verify the tests fail because the methods do not exist.
- [ ] Implement the two read-only accessors without mutating session or job files.
- [ ] Run `php tests/run.php Phase4Ui` and verify the focused tests pass.

### Task 2: Shared shell and truthful page models

**Files:**
- Modify: `public/index.php`
- Modify: `views/layout.php`
- Modify: `views/partials/navigation.php`
- Modify: `views/pages/dashboard.php`
- Modify: `views/pages/settings.php`
- Create: `public/assets/app.js`
- Test: `tests/Phase4UiTest.php`

**Interfaces:**
- Consumes: Task 1 job-ID accessors and existing `BackupJobStore`, `RestoreJobStore`, `RestoreSafetyGate` public views.
- Produces: `recentBackupJobs`, `recentRestoreJobs`, connection/runtime/safety presentation arrays, shared responsive navigation hooks.

- [ ] Add render tests for real dashboard job/safety data, settings secret exclusion, active navigation, skip link, and menu semantics.
- [ ] Run the focused suite and verify expected failures against the current views.
- [ ] Compose bounded read-only page data and implement the shell/navigation/hero/settings markup plus shared enhancement script.
- [ ] Run the focused suite and affected routing/security tests.

### Task 3: Backup interaction redesign

**Files:**
- Modify: `views/pages/backup.php`
- Modify: `public/assets/backup-jobs.js`
- Test: `tests/Phase4UiTest.php`

**Interfaces:**
- Consumes: existing `job.php` create/execute/status API and Phase 2 public job view.
- Produces: searchable selection UI, selected count, native confirmation dialog, and clearer persisted states without changing API payload semantics.

- [ ] Add render tests for search, selected-count live text, bulk-selection controls, confirmation dialog, and no percentage UI.
- [ ] Run focused tests and verify they fail for missing controls.
- [ ] Implement markup and progressive enhancement; confirmation must occur before the existing create request.
- [ ] Run focused PHP tests and JavaScript syntax checks.

### Task 4: Restore and History clarity

**Files:**
- Modify: `public/index.php`
- Modify: `views/pages/restore.php`
- Modify: `views/pages/history.php`
- Modify: `public/assets/restore-jobs.js`
- Modify: `public/assets/app.js`
- Test: `tests/Phase4UiTest.php`

**Interfaces:**
- Consumes: existing lightweight artifact inspection, Restore Safety Gate, Phase 3 preflight job view, and signed download URLs.
- Produces: explicit integrity/provenance/eligibility columns, client-side inventory filters, safety-gate view, and preflight history.

- [ ] Add render tests proving no SQL execute/success control, explicit hard-closed safety state, metadata-only history labels, and preflight/download actions.
- [ ] Run focused tests and verify expected failures.
- [ ] Implement the view models, markup, and filters without invoking SHA-256 during History rendering.
- [ ] Run focused and Phase 3 API/UI tests plus JavaScript syntax checks.

### Task 5: Central visual system and responsive/accessibility integration

**Files:**
- Modify: `public/assets/app.css`
- Modify: `views/pages/login.php`
- Modify: `views/pages/placeholder.php`
- Test: `tests/Phase4UiTest.php`

**Interfaces:**
- Consumes: semantic classes/hooks from Tasks 2–4.
- Produces: centralized tokens, component styles, responsive layouts, focus-visible and reduced-motion behavior.

- [ ] Add behavior-oriented HTML accessibility assertions where server rendering owns the contract.
- [ ] Run focused tests and record RED where new semantics are absent.
- [ ] Implement the CSS system and any minimal semantic markup required by the tests.
- [ ] Run focused tests, affected integration tests, PHP lint, JavaScript syntax checks, and browser smoke checks at desktop/mobile widths.

### Task 6: Documentation and final evidence

**Files:**
- Modify: `README.md`
- Modify: `TEST_BASELINE.md`

**Interfaces:**
- Consumes: verified commands and observed browser behavior from Tasks 1–5.
- Produces: operator-facing Phase 4 notes and an honest PASS/FAIL/SKIP/NOT VERIFIED record.

- [ ] Update README only for changed operator-visible behavior and retained restore limitation.
- [ ] Run the complete affected suite and syntax checks fresh.
- [ ] Append exact evidence to `TEST_BASELINE.md`, explicitly retaining live backup and live restore as `NOT VERIFIED`.
- [ ] Request a read-only final code review and address Critical/Important findings with RED→GREEN tests.

