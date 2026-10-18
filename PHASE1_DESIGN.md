# Phase 1 Design Specification

วันที่: 2026-10-16  
สถานะ: Approved approach — Incremental Native-PHP Foundation

## 1. Scope and Principles

Phase 1 establishes a secure, maintainable foundation around the existing manual MySQL/MariaDB backup implementation. It does not add a scheduler, automatic backup, a backup queue, a restore engine, database migrations, or production-database write operations.

The existing `BackupService` remains the authoritative dump implementation. Changes around it must preserve sequential processing: the selected database list is processed by one synchronous `foreach`, one database at a time.

## 2. Current Architecture

- `public/index.php` combines request parsing, session state, database connection, backup orchestration, storage settings, error handling, and all page markup.
- `public/download.php` directly resolves a session-selected root and streams the requested path.
- `app/bootstrap.php` starts a default PHP session and loads configuration/autoloading.
- `BackupService` creates SQL dumps through `mysqli`, uses a temporary file, verifies a completion marker, atomically renames the completed dump, and creates a SHA-256 manifest.
- CSRF helpers and HTML escaping exist, but there is no authentication or global authorization gate.
- The database password is stored server-side in the session and is also rendered back into HTML fields.
- Styling is centralized in `public/assets/app.css`, but there is no shared layout/navigation or multi-page route convention.

## 3. Target Architecture

The target keeps a small native-PHP stack:

1. `app/bootstrap.php` loads configuration, applies secure session settings, starts the session, prepares private runtime directories, and returns configuration.
2. `public/index.php` is the front controller. It enforces the access policy, dispatches POST actions to focused controllers, resolves a page through an allowlisted router, and renders a view.
3. Controllers coordinate existing services. They do not contain HTML and do not duplicate SQL dump logic.
4. Views contain presentation only and render through one shared layout and navigation partial.
5. Security services own authentication, direct-loopback fallback, CSRF, path policy, download authorization, session lifecycle, and technical logging.
6. `public/download.php` uses the same authentication and storage policy as the front controller and only serves authorized, listed SQL backup artifacts.

## 4. Proposed File Structure

```text
app/
  Backup/BackupService.php              # existing dump engine; preserve
  Database/                              # existing connection/repository
  Http/
    Router.php                           # allowlisted page routing
    Controller/
      AuthController.php                 # login/logout actions
      BackupController.php               # connect/manual backup actions
      SettingsController.php             # validated storage selection/open folder
  Support/
    Auth.php                             # configured auth and loopback policy
    BackupPathPolicy.php                 # canonical allowlist containment
    DownloadAuthorization.php            # signed/listed SQL downloads
    Logger.php                           # private technical log with redaction
    Session.php                          # cookie/session hardening and invalidation
    View.php                             # shared layout renderer
  bootstrap.php
config/
  app.php                                # safe defaults
  local.php.example                      # administrator configuration template
public/
  index.php                              # front controller
  download.php                           # protected file response
  assets/app.css                         # shared tokens and responsive shell
views/
  layout.php
  partials/navigation.php
  pages/login.php
  pages/dashboard.php
  pages/backup.php
  pages/history.php
  pages/placeholder.php
  pages/settings.php
tests/
  bootstrap.php
  run.php
  SecurityFoundationTest.php
  RoutingAndViewTest.php
tools/
  generate-admin-credentials.php
PHASE1_AUDIT.md
TEST_BASELINE.md
```

## 5. Routing and Controller/View Separation

The route convention is `index.php?page=<name>`, with the allowlist `dashboard`, `backup`, `restore`, `history`, and `settings`. Unknown routes return a safe 404 page through the shared layout.

POST requests carry an explicit `action`. Authentication actions are handled before the global authorization gate only where necessary for login. All state-changing application actions require a valid CSRF token. Controllers redirect after POST to prevent resubmission.

Dashboard summarizes the session-visible database count, backup count, and latest backup. Backup keeps the existing connection/database selection/manual sequential backup flow. History lists current backup files. Restore is a non-operational placeholder. Settings provides the validated storage-root foundation. No restore or queue behavior is introduced.

## 6. Shared Layout and Navigation

All application pages render through `views/layout.php`, which supplies metadata, shared CSS, status messages, page heading, and navigation. Navigation uses the same allowlisted route names and marks the active page with `aria-current="page"`. Login has the same design tokens but omits protected navigation.

The CSS retains the restrained SaaS visual direction, centralizes spacing/color/focus tokens, avoids nested card stacks, supports narrow screens, and preserves visible keyboard focus. Success, warning, error, and informational states share one component pattern.

## 7. Authentication and Access Control

Authentication is configured in ignored `config/local.php` using an administrator username and a `password_hash()` result. `password_verify()` checks credentials and login regenerates the session ID.

When no password hash is configured:

- Direct requests whose `REMOTE_ADDR` is `127.0.0.1` or `::1` are permitted.
- LAN and remote addresses receive HTTP 403 with setup guidance.
- `X-Forwarded-For` and similar headers are never used to establish loopback trust.

When authentication is configured, every application route and download requires an authenticated session. Login and logout are explicit actions. The deployment guide states that reverse proxies require deliberate web-server access rules because the application intentionally trusts only the direct peer address.

`tools/generate-admin-credentials.php` generates a random one-time password and its `password_hash()` value without accepting a secret as a command-line argument. The administrator copies only the hash into `config/local.php` and stores the generated password securely.

## 8. Secure Session Management

Before `session_start()` the application enables `session.use_strict_mode`, cookies-only sessions, `HttpOnly`, and `SameSite=Strict`. The `Secure` flag is enabled when HTTPS is detected directly. HTTP loopback remains usable under Laragon; remote production deployment must use HTTPS.

Successful login regenerates the session ID and records only authentication state. Logout clears session data, expires the session cookie, and destroys the session. CSRF remains mandatory for login, logout, connection, backup, storage update, and folder-open actions.

Database connection credentials remain server-side in the session to preserve the existing multi-step workflow, but the password is never rendered into HTML, hidden inputs, flash messages, logs, or URLs.

## 9. Backup Storage Path Policy

`allowed_backup_roots` is a configuration allowlist. The default contains only `storage/backups`; additional absolute roots must be declared in ignored local configuration.

The path policy:

- accepts absolute paths only;
- canonicalizes existing roots and the nearest existing parent of a proposed directory;
- compares path segments using Windows case-insensitive semantics where applicable;
- rejects traversal, sibling-prefix confusion, project code/public directories, filesystem roots, and paths outside the allowlist;
- creates a requested directory only after its existing ancestor is proven to be inside an allowed root;
- resolves the final directory again after creation to prevent symlink/junction escape.

Every request revalidates the session-selected backup root. Invalid saved state falls back to the configured default rather than being trusted.

## 10. Secure Download Authorization

A download link contains a relative backup path and an HMAC token bound to that path and the current authenticated session. The endpoint requires global authorization, validates the token in constant time, validates the active backup root through the path policy, and confirms the requested path is one of the `.sql` files returned by the backup inventory.

The final path must remain canonically contained in the active root, be a regular `.sql` file, and have the backup completion marker plus a matching manifest checksum when a manifest is present. Files merely located under the root are not automatically downloadable. Responses include `nosniff`, private/no-store cache policy, and safe attachment filenames.

## 11. Error Handling and Logging

Browser responses and flash messages use stable Thai user-safe messages. Technical exception class, location, and message are written to `storage/logs/app.log`, outside the public directory. Log creation uses restrictive best-effort permissions and newline normalization. Known secrets, especially the active database password, are redacted before logging. Stack traces, credentials, filesystem configuration, and raw driver errors are never sent to the browser.

If logging itself fails, the request still receives the safe error response; the application must not disclose the logging failure or secret context.

## 12. PHP 8.3 and Laragon Compatibility

- Runtime target: PHP 8.3 with `mysqli`, `session`, `json`, and `openssl`/hash support.
- No framework, Composer package, database migration, or web-server configuration mutation is required.
- Windows paths, drive-letter case, directory separators, and Laragon HTTP loopback are explicitly supported.
- Apache `.htaccess` continues to deny direct storage access. Security does not rely solely on `.htaccess`; the documented document root remains `public/`.
- The existing MySQL/MariaDB dump SQL remains unchanged in Phase 1.

## 13. Files to Create or Modify

Create the files listed in Section 4 that do not currently exist. Modify `.gitignore`, `README.md`, `app/bootstrap.php`, `config/app.php`, `public/index.php`, `public/download.php`, and `public/assets/app.css`. Modify `BackupService.php` only if a narrowly scoped read-only verification method is required; do not rewrite dump generation.

Existing tracked SQL and JSON backup artifacts are not modified or removed.

## 14. Implementation Steps

1. Add tests and a lightweight runner; establish RED cases for auth, session options, CSRF, path containment, download authorization, routing, and password-free rendering.
2. Add safe configuration merge, session lifecycle, authentication, credential generation, private logging, and ignored local configuration.
3. Add canonical storage policy and signed/listed SQL download authorization.
4. Add allowlisted routing, controllers, shared views/navigation, and split the existing backup UI while preserving synchronous backup behavior.
5. Update styles and documentation, add audit/baseline records, and apply Git ignore rules without untracking existing files.
6. Run change-based tests, lint changed PHP, inspect the Phase 1 diff, and perform a focused security review.

## 15. Risks and Rollback Plan

Risks include Windows path edge cases, session behavior under HTTP/HTTPS differences, proxy deployments appearing non-loopback, and regressions while splitting the existing monolithic page. Database compatibility cannot be fully proved without a real or disposable database and is therefore not claimed by Phase 1 tests.

Rollback is file-level because no database, Git history, service, or Laragon configuration changes are permitted. Before release, the user may discard the uncommitted Phase 1 source changes to return to the current V1.0 state. Existing backup artifacts remain untouched throughout rollback. Local `config/local.php` is independent and ignored.

## 16. Change-Based Test Matrix

| Area | Test scope | Evidence | Database impact |
|---|---|---|---|
| Authentication | configured success/failure; unconfigured loopback allow; unconfigured remote deny; ignore forwarded headers | lightweight PHP assertions | none |
| Session | cookie option policy; login ID regeneration behavior; logout invalidation | isolated CLI session/tests | none |
| CSRF | valid, invalid, missing token | existing helper through test runner | none |
| Storage | allowed root; traversal; sibling prefix; absolute-path requirement; conditional symlink/junction escape | disposable temp directories only | none |
| Download | valid signed listed SQL; invalid token; non-SQL; unlisted path; escape attempt | disposable temp files only | none |
| Routing/navigation | five allowed pages; unknown 404; active navigation; shared layout | router and rendered HTML assertions | none |
| Credential exposure | render connection state containing a sentinel password and assert sentinel absent from HTML | view rendering test | none |
| Backup orchestration | static/focused check that selected databases remain processed in one synchronous loop; no engine rewrite | diff inspection and controller-level test doubles where safe | none |
| PHP syntax | changed PHP files only; baseline unchanged files are not needlessly relinted | Laragon PHP 8.3 `-l` output | none |
| Live MySQL backup | not run because no disposable server/database was authorized | marked NOT VERIFIED | none |
| Restore | out of Phase 1 | marked NOT VERIFIED | none |

