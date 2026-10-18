# Native .NET DPAPI Helper Implementation Plan

**Goal:** Replace the PowerShell production boundary with a fail-closed native .NET 10 DPAPI helper while preserving the existing secret protocol and envelope contracts.

**Architecture:** A self-contained single-file `win-x64` console helper owns Windows identity, DPAPI CurrentUser, and approved ACL operations. PHP calls only the fixed production executable through the existing bounded process runner after a non-bypassable deployment trust gate. Production remains blocked until a signed artifact is pinned.

**Tech Stack:** .NET 10/C#, Windows DPAPI/ACL APIs, PHP 8.3, existing custom PHP test harness.

---

### Task 1: Protocol and typed operations

**Files:** create `native/WindowsSecretHelper/*`, `native/WindowsSecretHelper.Tests/*`.

Write failing parser tests for empty identity input, missing/unexpected fields, malformed JSON, invalid operations and bounds. Implement strict JSON parsing, exact field schemas, bounded base64 and stable responses.

Acceptance: all protocol tests pass; no request content appears in errors.

### Task 2: Windows identity, DPAPI and ACL policy

**Files:** modify native helper/tests.

Write failing tests for wrong SID, malformed SID, wrong entropy length, ACL path escape/reparse policy and safe error mapping. Implement CurrentUser protect/unprotect and exact SID precheck. Implement ACL operations only for approved fixed storage paths.

Acceptance: unit tests pass; a synthetic secret round-trip through the built helper succeeds on Windows without persisting plaintext; negative responses contain stable codes only.

### Task 3: Deterministic publish profile

**Files:** create `native/WindowsSecretHelper/WindowsSecretHelper.csproj`, publish documentation/scripts only as needed.

Configure `net10.0-windows`, `win-x64`, Release, self-contained, single-file, no trimming and no AOT. Publish an unsigned offline test artifact outside production deployment path.

Acceptance: artifact starts and protocol smoke passes; artifact is explicitly not production-authorized.

### Task 4: PHP native adapter and production trust gate

**Files:** create `NativeDpapiHelper.php`, `NativeHelperDeploymentVerifier.php`, tests; modify PowerShell production tests/documentation.

Write failing tests proving fixed executable/operation, no args, exact stdin, deployment gate blocks invocation, safe error mapping and no PowerShell fallback. Implement adapter over the existing bounded runner and a production verifier that remains blocked until signed hash/publisher/thumbprint pins are compiled into reviewed source.

Acceptance: focused PHP tests pass; production path is fixed; unsigned/missing/unverified helper never executes; old PowerShell class is not a production fallback.

### Task 5: Integration evidence and handoff

**Files:** update `TEST_BASELINE.md`, design/plan/deployment docs.

Run only native unit/integration tests and directly affected PHP secret-worker/provider tests, PHP/C# static checks and forbidden-wiring scans. Record signing/ACL/SID requirements and unresolved production blockers.

Acceptance: evidence is reproducible; no MySQL or real credential activity; no success claim for signed deployment.
