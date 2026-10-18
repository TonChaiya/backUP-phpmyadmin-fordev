# Windows Secret Provider Implementation Plan

สถานะ: Superseded by approved Native .NET helper plan; PowerShell is historical only  
วันที่: 2026-10-18

## Sequence and Acceptance

1. **Typed domain and authenticated envelope** — exact roles, endpoint/product/version/fingerprint, Windows SID/account, canonical metadata-derived entropy, inner/outer integrity comparison และ bounded JSON
2. **Private create-only storage** — fixed per-role filenames under `storage/private/restore-integration-secrets`, ACL hook before write, exclusive same-directory temporary file, flush/rename และ owned-temp recovery
3. **Fixed worker boundary** — fixed `powershell.exe` and repository script, allowlisted operations, secret through bounded STDIN only, bounded JSON stdout, no raw stderr propagation, 5-second timeout
4. **Provider orchestration** — identity checked before unprotect, role/server/SID exact comparison, no plaintext fallback, verified exact-role deletion only
5. **Operator/readiness surface** — identity-only CLI, runbook, change-based tests และ hard-disable regression

Acceptance requires all fake-based unit tests, PHP lint, PowerShell AST parse, leakage scan and existing live safety seals to pass. Actual DPAPI/ACL evidence remains NOT VERIFIED while the machine blocks `.ps1`; this must not be bypassed through dynamic command or an unreviewed execution-policy override.

## Dependency Boundary

The provider is deliberately not referenced by `RestoreConnectionFactory`, `MysqliRestoreConnection`, `RestoreIntegrationRunner`, HTTP controllers, job JSON or manifests. Credential provisioning and live database activity require separate authorization after the worker deployment blocker is resolved and CLI/Apache SID parity is rechecked.
