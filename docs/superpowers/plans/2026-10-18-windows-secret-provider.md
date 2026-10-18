# Windows Secret Provider Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement an offline-production Windows DPAPI CurrentUser secret provider for the two Restore Integration credential roles without enabling any MySQL operation.

**Architecture:** Immutable typed role/binding/identity objects feed a versioned authenticated envelope orchestrated by `WindowsSecretProvider`. A fixed PowerShell worker performs DPAPI/identity/ACL operations through a bounded STDIN/STDOUT protocol; a private file store performs create-only atomic writes. Narrow interfaces allow deterministic fake-based tests.

**Tech Stack:** PHP 8.3 strict types, Windows PowerShell 5.1/.NET DPAPI, NTFS ACL, JSON, SHA-256, existing custom PHP test runner.

**Spec:** `docs/WINDOWS_SECRET_PROVIDER_DESIGN.md`

## Global Constraints

- DPAPI scope is `CurrentUser`; machine scope and plaintext fallback are forbidden.
- Roles are exactly `backup_reader` and `restore_runner`.
- Binding is exact product `mysql`, version `8.4.3`, endpoint `127.0.0.1:3306`, and a nonempty `mysql:<native-id>` fingerprint; port 3333 is forbidden.
- Fixed worker only; secret bytes travel through STDIN, never arguments/environment/temp plaintext files.
- Envelope version is 1 and outer metadata is authenticated through DPAPI entropy plus inner/outer comparison.
- Private storage is `storage/private/restore-integration-secrets`; exact role filenames only, ACL hardened, create-only atomic write.
- Stable errors only; no secret/ciphertext/raw worker error/path in logs, exceptions, public output or tests.
- Production MySQL factory/runner/API remain hard-disabled.
- No MySQL connection, credential provisioning, account/grant/database change, backup/restore, protected-schema access, Git commit, push, tag or release.
- Change-based testing only; no unrelated full regression.

## Review Focus

- PHP copy-on-write duplicates a revealed secret: wrapper must prevent serialization/debug leakage and minimize lifetime, while documentation states zeroization is best effort.
- Process timeout occurs after partial stdout/stderr: runner must terminate, close pipes and return only `worker_timeout`.
- Outer metadata is changed to another valid role/binding: entropy and inner metadata comparison must reject it.
- Final envelope appears during atomic create: store must refuse overwrite, remove only its own temporary file and preserve the existing final file.
- Apache restarts under another account/SID: every retrieve must re-read identity and fail before unprotect.

---

### Task 1: Typed Secret Domain and Authenticated Envelope

**Files:**
- Create: `app/Restore/Integration/Secrets/SecretRole.php`
- Create: `app/Restore/Integration/Secrets/SecretServerBinding.php`
- Create: `app/Restore/Integration/Secrets/WindowsIdentity.php`
- Create: `app/Restore/Integration/Secrets/SecretValue.php`
- Create: `app/Restore/Integration/Secrets/SecretProviderException.php`
- Create: `app/Restore/Integration/Secrets/SecretEnvelopeCodec.php`
- Test: `tests/WindowsSecretEnvelopeTest.php`

**Interfaces:**
- Produces: `SecretRole` enum; `SecretServerBinding::metadata(): array`; `WindowsIdentity::metadata(): array`; `SecretValue::reveal(): string`; `SecretEnvelopeCodec::prepare(...)`, `encode(...)`, and `decode(...)` returning strictly validated metadata/ciphertext/inner payload structures.
- Consumes: no new production interfaces.

- [ ] Write failing tests for exact roles/binding, port 3333 rejection, invalid fingerprint/SID, bounded secret, deterministic entropy, version/schema rejection, tampered role/endpoint/product/version/fingerprint/SID/timestamp/ciphertext/inner metadata, and non-serializable redacted `SecretValue`.
- [ ] Run `php tests/run.php WindowsSecretEnvelopeTest`; expect missing-class failures.
- [ ] Implement minimal immutable domain objects and codec. Canonical metadata order is version, role, server, windows identity, created-at; entropy is binary SHA-256 over a fixed context plus canonical JSON.
- [ ] Run the focused test; expect all cases PASS.
- [ ] Record completion in the SDD ledger; do not commit by user rule.

### Task 2: Private Atomic Envelope Storage and ACL Contract

**Files:**
- Create: `app/Restore/Integration/Secrets/WindowsAclHardener.php`
- Create: `app/Restore/Integration/Secrets/SecretEnvelopeStore.php`
- Create: `app/Restore/Integration/Secrets/FileSecretEnvelopeStore.php`
- Test: `tests/WindowsSecretStorageTest.php`

**Interfaces:**
- Consumes: `SecretRole`, `WindowsIdentity`, encoded envelope JSON from Task 1.
- Produces: `WindowsAclHardener::hardenDirectory(string, WindowsIdentity): void`, `hardenFile(...)`; `SecretEnvelopeStore::create/read/delete`; `FileSecretEnvelopeStore` fixed role-path implementation.

- [ ] Write failing tests for exact filenames/private root, directory/file ACL calls, create/read, collision/no-overwrite, malformed size rejection, hardener failure, invalid root, atomic write failure/recovery and exact delete.
- [ ] Run `php tests/run.php WindowsSecretStorageTest`; expect missing-interface/class failures.
- [ ] Implement create-only same-directory exclusive temp write, bounded write/flush/rename, cleanup of owned temp on failure and fixed role paths.
- [ ] Run focused storage tests; expect PASS and no orphan temporary files.
- [ ] Record completion; do not commit.

### Task 3: Fixed PowerShell DPAPI Worker and Bounded Process Runtime

**Files:**
- Create: `app/Restore/Integration/Secrets/RuntimeIdentityProvider.php`
- Create: `app/Restore/Integration/Secrets/SecretProtector.php`
- Create: `app/Restore/Integration/Secrets/WorkerProcessRunner.php`
- Create: `app/Restore/Integration/Secrets/WorkerProcessResult.php`
- Create: `app/Restore/Integration/Secrets/ProcOpenWorkerProcessRunner.php`
- Create: `app/Restore/Integration/Secrets/PowerShellDpapiWorker.php`
- Create: `tools/windows-dpapi-worker.ps1`
- Test: `tests/WindowsDpapiWorkerTest.php`

**Interfaces:**
- Consumes: `WindowsIdentity`, entropy bytes, secret/ciphertext bytes, ACL interface from Task 2.
- Produces: production implementations of `RuntimeIdentityProvider`, `SecretProtector`, `WindowsAclHardener`; bounded `WorkerProcessRunner::run(array $command, string $stdin, int $timeoutMs, int $maxOutputBytes): WorkerProcessResult`.

- [ ] Write failing tests with a recording fake process runner proving fixed command/script/operation, secret only in STDIN, request/output limits, safe code mapping, wrong SID, malformed output, nonzero exit, stderr suppression contract and timeout handling.
- [ ] Add process-runner tests using synthetic child commands for timeout, partial output and output-limit cleanup; no secret arguments.
- [ ] Run `php tests/run.php WindowsDpapiWorkerTest`; expect missing-class failures.
- [ ] Implement bounded nonblocking `proc_open` runner with termination/finally cleanup and fixed PowerShell adapter.
- [ ] Implement fixed `.ps1` allowlist operations with bounded STDIN, `ProtectedData` CurrentUser, same-process SID check, fixed ACL operations, JSON-only stdout and stable errors; no dynamic execution.
- [ ] Run focused tests and PowerShell AST parse; expect PASS/no parser errors.
- [ ] Record completion; do not commit.

### Task 4: Secret Provider Orchestration and Identity Drift Gate

**Files:**
- Create: `app/Restore/Integration/Secrets/WindowsSecretProvider.php`
- Test: `tests/WindowsSecretProviderTest.php`

**Interfaces:**
- Consumes: codec/domain from Task 1, store from Task 2, identity/protector from Task 3.
- Produces: `store(SecretRole, SecretServerBinding, #[SensitiveParameter] string): void`, `retrieve(SecretRole, SecretServerBinding): SecretValue`, `delete(...)` with fail-closed stable codes.

- [ ] Write failing fake-based lifecycle tests for separate roles, missing secret, wrong SID/account, wrong role, wrong endpoint/product/version/fingerprint, protector/decrypt failure, tampered envelope, ACL/storage failure, identity change between operations and sentinel leakage.
- [ ] Run `php tests/run.php WindowsSecretProviderTest`; expect missing-class failure.
- [ ] Implement store/retrieve/delete orchestration; retrieve must check current SID before unprotect and compare protected inner/outer metadata after decrypt.
- [ ] Run focused tests; expect PASS and no sentinel in exceptions/debug/serialized artifacts/test-captured output.
- [ ] Record completion; do not commit.

### Task 5: Deployment Guard, Documentation and Change-Based Verification

**Files:**
- Create: `tools/windows-secret-identity.php`
- Modify: `docs/RESTORE_INTEGRATION_CREDENTIAL_PROVISIONING_PLAN.md`
- Modify: `docs/RESTORE_INTEGRATION_LIVE_RUNBOOK.md`
- Modify: `TEST_BASELINE.md`
- Modify: `.superpowers/sdd/2026-10-18-windows-secret-provider/progress.md`
- Test: `tests/RestoreIntegrationExecutorTest.php`
- Test: `tests/RestoreIntegrationApiAndUiTest.php`

**Interfaces:**
- Consumes: production identity provider and all earlier components.
- Produces: read-only redacted identity command; updated operator steps and test evidence.

- [ ] Write failing CLI contract test: identity command accepts no secret/live arguments and outputs account/SID only; no store/retrieve/delete CLI or HTTP endpoint exists.
- [ ] Implement the command and update runbooks with CLI-vs-Apache identity comparison, DPAPI enrollment boundary, TRIGGER risk, synthetic-only optional smoke and rollback handling.
- [ ] Run all four new focused suites plus directly affected Restore factory/API hard-disable suites.
- [ ] Lint changed PHP, parse PowerShell AST, scan production scope for dynamic PowerShell, secret arguments/environment/temp plaintext, MySQL APIs/SQL/protected schema, and scan captured output/artifacts for sentinels.
- [ ] Update `TEST_BASELINE.md` only with observed results; all live DPAPI/MySQL/credential evidence not exercised stays NOT VERIFIED.
- [ ] Perform final self-review because subagent delegation is not authorized; record any rulings/minors in the ledger and stop before provisioning.
