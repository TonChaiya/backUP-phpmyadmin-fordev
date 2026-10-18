# Native .NET DPAPI Helper Design

สถานะ: Approved architecture; offline implementation in progress  
วันที่: 2026-10-18

## Boundary

แทนที่ PowerShell production worker ด้วย fixed native helper สำหรับ Windows `win-x64` ที่ publish ด้วย .NET 10 LTS แบบ Release, self-contained, single-file, ไม่ trim และไม่ NativeAOT โดยคง DPAPI `CurrentUser`, JSON protocol, versioned authenticated envelope และ exact SID/role/server binding เดิมทั้งหมด

รอบนี้ไม่มี MySQL connection, credential enrollment, account/grant/database operation, backup/restore หรือ trust-store/policy change ใด ๆ

## Helper Contract

- executable production path ตายตัว: `C:\ProgramData\BackupPhpMyAdmin\SecretProvider\backup-secret-helper.exe`
- invocation ใช้ command array `[fixed-executable, allowlisted-operation]`; ไม่ใช้ shell และไม่มี argument อื่น
- operations: `identity`, `protect`, `unprotect`, `harden-directory`, `harden-file`
- request เป็น strict JSON object ทาง STDIN เท่านั้น; maximum 16 KiB
- response เป็น strict JSON objectทาง STDOUT เท่านั้น; maximum 64 KiB; expected failures ไม่เขียน STDERR
- timeout 5 seconds; malformed/oversized/timeout/non-zero/STDERR จบด้วย stable safe code
- protect/unprotect ตรวจ current process SID เทียบ `expected_sid` ก่อน DPAPI ทุกครั้ง
- DPAPI ใช้ `DataProtectionScope.CurrentUser` และ caller-provided 32-byte metadata entropy
- ACL operations ยอมรับเฉพาะ fixed private storage directory และ exact role/temp filenames; reject reparse point

Protocol success/error shape คง compatible กับ worker เดิม ไม่มี raw-SQL, arbitrary executable/path/operation หรือ plaintext fallback

## Production Trust Gate

PHP adapter มี executable path และ operation allowlist แบบ hard-coded ก่อน invoke ต้องผ่าน deployment attestation ครบชุด:

1. file เป็น regular fileที่ fixed pathและไม่ใช่ link
2. SHA-256 ตรงค่าที่ review/pin ไว้
3. Authenticode status valid
4. signer publisher และ certificate thumbprint ตรงค่าที่อนุมัติ
5. executable directory/file ACL ผ่าน deployment procedure

เครื่องปัจจุบันไม่มี code-signing certificate จึงไม่กำหนด production hash/publisher/thumbprint และ production adapter ต้อง fail ด้วย `native_helper_deployment_unverified` ก่อน process invocation ไม่มี configuration flag หรือ fallback ไป PowerShell

## Build and Deployment Separation

source และ test artifact อยู่ใน repository ได้ แต่ production executable ต้องถูก publish/sign/verify แล้ววางใน protected ProgramData path โดย authorized deployment ภายหลัง Build output ไม่ถือเป็น production deployment และห้ามใช้กับ real credential จน trust gate ได้รับค่าจาก signed artifact review

PowerShell sourceคงไว้เป็น historical compatibility/test evidence เท่านั้นและไม่ถูก production adapter เรียก

## Verification

- .NET unit tests: strict parser, exact fields, invalid operation, SID mismatch, malformed/base64/size cases และ ACL path policy
- synthetic DPAPI smoke: identity + protect/unprotect ด้วย test-only sentinelผ่าน native helper แล้วไม่ persist secret
- PHP tests: fixed command, allowlisted operation, trust-gate block, timeout/output/error sanitization และ real helper protocol integration
- existing envelope/provider negative casesยังเป็น evidence ของ role/endpoint/version/fingerprint/tamper/atomic storage; rerunเฉพาะ directly affected subset

Production deployment remains **BLOCKED** until signing certificate, trusted publisher/thumbprint, signed artifact hash, protected ACL และ CLI/Apache SID parity ได้รับอนุมัติและตรวจจริง
