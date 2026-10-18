# Phase 1 Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Secure and modularize the existing manual backup manager without changing its SQL dump engine or touching a real database.

**Architecture:** Keep the existing native-PHP services and wrap them with a secure bootstrap, focused HTTP controllers, an allowlisted query-string router, and shared views. Authentication, canonical backup-path policy, signed/listed downloads, and private logging are independent support services tested with disposable local fixtures.

**Tech Stack:** PHP 8.3, mysqli, PHP sessions, native hash/password APIs, HTML/CSS, Apache/Laragon.

**Spec:** `PHASE1_DESIGN.md`

## Global Constraints

- Phase 1 only; no scheduler, automatic backup, backup queue, restore engine, migrations, releases, or dependency framework.
- Do not modify any real database, especially `jhcisdb`.
- Do not read, change, remove, untrack, or disclose existing SQL backup contents.
- Do not commit, push, tag, rewrite history, or release.
- Preserve `app/Backup/BackupService.php` dump generation unless a minimal read-only verification seam is essential.
- Tests use only CLI state and disposable filesystem fixtures.

## Review Focus

- A remote peer with a spoofed `X-Forwarded-For: 127.0.0.1` must remain unauthorized when auth is not configured.
- A sibling path sharing the allowed-root prefix must not pass containment.
- A valid session must not make an unlisted or non-SQL file downloadable.
- A sentinel database password held in server-side state must never appear in rendered HTML or logs.
- An unknown page must not include arbitrary files and must return the shared safe 404 view.

---

### Task 1: Lightweight tests and core security services

**Files:**
- Create: `tests/bootstrap.php`
- Create: `tests/run.php`
- Create: `tests/SecurityFoundationTest.php`
- Create: `app/Support/Session.php`
- Create: `app/Support/Auth.php`
- Modify: `app/Support/Security.php`

**Interfaces:**
- Produces: `Session::cookieOptions(bool): array`, `Session::start(bool): void`, `Session::regenerate(): void`, `Session::destroy(): void`.
- Produces: `Auth::__construct(array)`, `isConfigured(): bool`, `isDirectLoopback(string): bool`, `isAuthorized(string): bool`, `attempt(string,string): bool`, `logout(): void`.
- Produces: existing CSRF API plus request-safe token validation.

- [ ] Write focused tests for configured login, wrong credentials, loopback fallback, remote denial, forwarded-header irrelevance, secure cookie options, logout, and CSRF.
- [ ] Run `php tests/run.php SecurityFoundationTest` and verify RED because the new classes do not exist.
- [ ] Implement the minimum session/auth/security behavior.
- [ ] Run the focused test and verify all Task 1 cases pass.

### Task 2: Configuration, logging, and administrator setup

**Files:**
- Create: `config/local.php.example`
- Create: `tools/generate-admin-credentials.php`
- Create: `app/Support/Logger.php`
- Modify: `config/app.php`
- Modify: `app/bootstrap.php`
- Modify: `.gitignore`
- Modify: `tests/SecurityFoundationTest.php`

**Interfaces:**
- Consumes: `Session::start()` from Task 1.
- Produces: recursive safe local configuration merge; `Logger::exception(Throwable,array): void`.

- [ ] Add failing tests for secret redaction and local configuration behavior.
- [ ] Run the Task 1/2 focused tests and verify the new assertions fail for the intended missing behavior.
- [ ] Implement configuration merge, private log setup/redaction, ignored local config, and random credential generation.
- [ ] Run the focused tests and verify they pass.

### Task 3: Canonical storage and download authorization

**Files:**
- Create: `app/Support/BackupPathPolicy.php`
- Create: `app/Support/DownloadAuthorization.php`
- Create: `tests/StorageAndDownloadTest.php`
- Modify: `public/download.php`

**Interfaces:**
- Produces: `BackupPathPolicy::__construct(array)`, `resolve(string,bool): string`, `defaultRoot(): string`.
- Produces: `DownloadAuthorization::__construct(string)`, `token(string): string`, `authorize(string,string,array): bool`.
- Consumes: authenticated session and validated backup root from Tasks 1–2.

- [ ] Write failing disposable-filesystem tests for allowed roots, traversal, sibling prefixes, optional symlink escape, signed listed SQL, invalid tokens, non-SQL paths, and unlisted files.
- [ ] Run `php tests/run.php StorageAndDownloadTest` and verify RED for missing classes.
- [ ] Implement canonical path policy and download authorization.
- [ ] Run the focused tests and verify they pass or explicitly report a platform-skipped symlink case.
- [ ] Protect `public/download.php` with auth, path policy, inventory membership, safe headers, and generic errors.

### Task 4: Routing, controllers, and shared UI

**Files:**
- Create: `app/Http/Router.php`
- Create: `app/Http/Controller/AuthController.php`
- Create: `app/Http/Controller/BackupController.php`
- Create: `app/Http/Controller/SettingsController.php`
- Create: `app/Support/View.php`
- Create: `views/layout.php`
- Create: `views/partials/navigation.php`
- Create: `views/pages/login.php`
- Create: `views/pages/dashboard.php`
- Create: `views/pages/backup.php`
- Create: `views/pages/history.php`
- Create: `views/pages/placeholder.php`
- Create: `views/pages/settings.php`
- Create: `tests/RoutingAndViewTest.php`
- Modify: `public/index.php`
- Modify: `public/assets/app.css`

**Interfaces:**
- Consumes: auth/session/path/download primitives from Tasks 1–3 and existing database/backup services.
- Produces: `Router::resolve(?string): array{name:string,status:int,view:string}` and `View::render(string,array,string,array): string`.

- [ ] Write failing tests for the five page routes, shared navigation, safe 404 behavior, active route state, and absence of a sentinel database password from rendered HTML.
- [ ] Run `php tests/run.php RoutingAndViewTest` and verify RED for missing routing/view behavior.
- [ ] Implement router, renderer, shared layout/navigation, and page views.
- [ ] Implement focused controllers and the front controller, keeping selected backups in a synchronous `foreach`.
- [ ] Run routing/view tests and verify they pass.

### Task 5: Documentation, audit record, and change-based verification

**Files:**
- Create: `PHASE1_AUDIT.md`
- Create: `TEST_BASELINE.md`
- Modify: `README.md`

**Interfaces:**
- Consumes: final behavior and actual command evidence from Tasks 1–4.
- Produces: setup/deployment instructions, confirmed-risk record, test evidence, rerun reasons, and unverified cases.

- [ ] Document administrator setup, local-only fallback, proxy limitation, secure document root, storage allowlist, and rollback.
- [ ] Record the eight tracked SQL files as an exposure risk without reading their contents or removing them.
- [ ] Run the complete lightweight test runner once because all new security/routing components share it.
- [ ] Lint only changed PHP files because the unchanged baseline already passed.
- [ ] Check `git diff --check`, changed-file scope, tracked SQL count, and absence of tracked new backup/local-config artifacts.
- [ ] Record exact results in `TEST_BASELINE.md`; mark live database backup and restore as NOT VERIFIED.
- [ ] Perform a focused Phase 1 diff/security review and resolve Critical/Important findings with RED→GREEN tests.

