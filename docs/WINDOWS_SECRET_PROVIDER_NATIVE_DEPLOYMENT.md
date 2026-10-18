# Native Helper Signed Deployment Requirements

สถานะ: **BLOCKED — signing certificate unavailable**  
วันที่: 2026-10-18

Certificate strategy, reproducible workflow และ dry-run toolsฉบับล่าสุดอยู่ใน `WINDOWS_SECRET_PROVIDER_SIGNED_DEPLOYMENT_PREPARATION.md` Production mutation ยังคงไม่ได้รับอนุญาต

เอกสารนี้เป็น procedure สำหรับ authorization รอบถัดไปเท่านั้น ห้ามใช้ build artifact ปัจจุบันกับ credential จริง

## Required artifact

- .NET 10 LTS, `win-x64`, Release, self-contained, single-file
- `PublishTrimmed=false`, `PublishAot=false`
- fixed name `backup-secret-helper.exe`
- production path `C:\ProgramData\BackupPhpMyAdmin\SecretProvider\backup-secret-helper.exe`
- private store `C:\ProgramData\BackupPhpMyAdmin\SecretProvider\secrets`

## Signing and review sequence

1. Build from reviewed source on a controlled build host.
2. Sign the exact executable with an organization-controlled Authenticode code-signing certificate using SHA-256 digest and an approved RFC 3161 timestamp service.
3. Verify `Get-AuthenticodeSignature` returns `Valid`; record exact subject/publisher, certificate thumbprint, chain status and timestamp.
4. Compute SHA-256 after signing and independently compare the deployed file byte-for-byte/hash with the reviewed signed artifact.
5. Insert the reviewed SHA-256, publisher and thumbprint pins into `ProductionNativeHelperDeploymentVerifier`, rerun focused tests and obtain code review. Empty pins intentionally keep deployment blocked.
6. Create the fixed ProgramData directories without junction/reparse points. Executable directory/file must be writable only by authorized administrators/deployment identity; Apache/CLI runtime identity receives read/execute only.
7. Secret directory inheritance must be disabled and grant Full Control only to the exact runtime SID used for DPAPI CurrentUser. Verify owner and effective ACL after creation.
8. Re-run Authenticode, publisher/thumbprint and SHA-256 checks against the deployed fixed path. Any mismatch requires removal of only the newly deployed artifact and STOP.
9. Confirm CLI and Apache exact SID parity before any enrollment. Identity drift requires STOP; do not copy envelopes between identities.

## Acceptance evidence

- certificate chain and timestamp valid; expected publisher/thumbprint explicitly approved
- deployed hash equals the reviewed post-signing artifact hash
- no writable ACE for Users, Everyone, authenticated web users or the runtime SID on executable directory/file
- no reparse point in either executable or secret storage path chain
- runtime SID has only read/execute on helper and exclusive access to secret directory
- PHP adapter invokes only the fixed path with one allowlisted operation argument
- production verifier pins are nonempty and exact; no configuration/request override exists

## Runtime data separation gate

Application source และ mutable runtime data ต้องแยกกันก่อน production secret enrollment:

- development default: `<project>\storage`
- production root แบบ exact: `C:\ProgramData\BackupPhpMyAdmin\RuntimeData`
- production configuration ต้องระบุทั้ง `runtime_data_mode=production` และ exact `runtime_data_root`; application จะไม่สลับไป ProgramData อัตโนมัติ
- production root และ directory ย่อย `backups`, `jobs`, `logs`, `restore-jobs`, `restore-integration-jobs`, `restore-integration-ownership`, `database-operations`, `temporary`, `sessions` ต้องถูกสร้างโดย authorized deployment ก่อนเริ่ม Apache; application จะ fail closed และไม่สร้าง ProgramData เอง
- ปิด inheritance ที่ production root; `SYSTEM`/`Administrators` เป็น owner/control principal และ exact non-admin Apache runtime SID ได้เฉพาะสิทธิ์ที่จำเป็น: read/execute ต่อ source/runtime และ Modify ต่อ runtime-data directories
- ห้าม UNC/network path, path traversal, source/web-root overlap, junction, symlink หรือ reparse-backed directory ทุกระดับ
- Backup และ Restore ต้องใช้ lock เดียวกันที่ `RuntimeData\database-operations\global.lock`

การย้ายข้อมูลต้องทำเมื่อไม่มี active job โดย copy แบบ non-destructive ไป exact directory ใหม่ ตรวจ inventory/integrity แล้วจึงเปลี่ยน configuration ห้าม move/delete/rewrite backup, manifest, job history, log หรือ ownership journal เดิม การ rollback ก่อน credential enrollment ให้กลับ configuration เท่านั้นและเก็บทั้งสองชุดไว้ตรวจสอบ

## Current blocker

CurrentUser and LocalMachine certificate stores contain no usable code-signing certificate. The offline artifact is `NotSigned`; therefore publisher/thumbprint/hash pins remain empty, production invocation fails with `native_helper_deployment_unverified`, and credential provisioning remains prohibited.

## Prepared tools

- `build-release.ps1` — locked deterministic build ไป fixed LocalAppData staging เท่านั้น
- `show-signing-plan.ps1` — แสดง requirements; ไม่เรียก signing tool
- `verify-signed-artifact.ps1` — read-only Authenticode/EKU/publisher/thumbprint/timestamp/hash verification
- `deployment-preflight.ps1` — read-only artifact/SID/path/collision preflight
- `new-deployment-plan.ps1` — JSON dry-run plan; ไม่มี production mutation
- `verify-deployed-helper.ps1` — read-only signature/hash/ACL verification
- `verify-runtime-identity.ps1` — เรียกเฉพาะ `identity` หลัง signature/hash/ACL ผ่านครบ
