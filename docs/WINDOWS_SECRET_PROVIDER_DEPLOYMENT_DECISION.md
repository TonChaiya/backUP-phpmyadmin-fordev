# Windows Secret Provider Deployment Decision

สถานะ: **Architecture B approved; offline implementation completed; signed deployment BLOCKED**  
วันที่: 2026-10-18

## Decision Context

Architecture B (Native .NET DPAPI Helper) ได้รับอนุมัติเมื่อ 2026-10-18 และแทนที่ PowerShell production adapter แล้ว PowerShell sourceเดิมคงไว้เป็น historical protocol evidence เท่านั้น Production identity CLI ใช้ native adapter และไม่มี PowerShell fallback

PHP provider, authenticated envelope, private store และ fake-based tests มีอยู่แล้ว แต่ production adapter เรียก Windows PowerShell 5.1 ซึ่ง effective policy เป็น `Restricted` บนเครื่องนี้ Worker ไม่มี Authenticode signature และ repository ACL อนุญาต broad modification ขณะเดียวกัน PowerShell 7 ที่พบเป็น dependency ภายใน Codex runtime cache ไม่ใช่ stable production installation

Protocol defect ของ `identity` ถูกแก้แยกจาก deployment decision แล้ว โดยกรองเฉพาะ `$null` ที่ PowerShell สร้างจาก property enumeration ของ empty JSON object `{}`; unknown empty-string property name ยังคงถูก reject นอกจากนี้ PHP adapter ตรวจ exact JSON field set แบบไม่ขึ้นกับ property order เพราะ PowerShell hashtable serialization ไม่รับประกันลำดับ key

## Alternatives

| Criterion | A. Signed PowerShell Worker | B. Native .NET DPAPI Helper |
|---|---|---|
| Security | ต้องมี trusted code-signing certificate, Trusted Publisher strategy, protected script path/ACL และ process-scoped policy ที่อนุญาต signed scripts; policy/host drift ยังเป็น failure mode | Signed fixed executable, protected ACL และ optional hash/signer pin; ไม่ขึ้นกับ PowerShell execution policy; attack surface จาก script parsing/profile ถูกตัดออก |
| Deployment complexity | Source เล็ก แต่ต้องจัดการ signing ทุกครั้ง, trust chain/publisher และ policy compatibility ของ PowerShell host | Build/publish/sign เพิ่มขึ้นครั้งแรก แต่ target deployment เป็น executable artifact เดียวและมี deterministic version/hash |
| Dependencies | Windows PowerShell 5.1 มีใน Windows แต่เครื่องนี้เป็น `Restricted`; PowerShell 7 production installation ยังไม่มี | แนะนำ .NET 10 LTS self-contained single-file `win-x64`; target ไม่ต้องติดตั้ง runtime เพิ่ม แต่ build machine ต้องมี .NET 10 SDK |
| Windows compatibility | PowerShell version, execution policy, JSON behavior และ trust prompt แตกต่างตามเครื่อง | Windows 10 x64+ ตาม RID; DPAPI และ Windows ACL APIs ใช้โดยตรง; ต้อง publish แยกหากต้องรองรับ ARM64/x86 |
| CLI/Apache identity | DPAPI CurrentUser ถูกต้อง แต่ต้องระวัง host/profile/policy context | Child process ใช้ token ของ PHP/Apache โดยตรง; helper ตรวจ current SID ซ้ำก่อน DPAPI เช่นเดิม |
| Performance | Startup ของ PowerShell process สูงกว่า แต่ credential operation ไม่ถี่ | Self-contained helper startup ต่ำกว่า PowerShell และ protocol เหมือนเดิม |
| Long-term maintenance | ต้องดูแล script signing, certificate expiry, trusted publisher และ PowerShell compatibility | ต้อง patch/rebuild bundled .NET runtime และ sign release; typed compiler/System.Text.Json ลด scripting edge cases |
| Laragon portability | ผูกกับ policy และ PowerShell installation ของแต่ละ Windows host | แยกจาก Laragon/PHP version; copy signed `win-x64` artifact และ deployment manifest/ACL ไปยัง fixed private location |
| Testability | ต้องมี PowerShell host ที่ policy ยอมให้รัน script; unit testsถูกกระทบโดย host semantics | Parser/protocol/validation unit-test ได้ใน .NET; PHP ใช้ fake runner เดิม; Windows integration testsใช้ synthetic secret |
| Credential compatibility | ใช้ DPAPI CurrentUser/envelope เดิม | ใช้ `ProtectedData` CurrentUser และ entropy เดิม จึงรักษา ciphertext semantics; ไม่มี live credential/envelope ที่ต้อง migrate |

## Recommendation

เลือก **B. Native .NET DPAPI Helper** สำหรับ production deployment ของ native PHP บน Laragon

เหตุผลหลักคือสามารถตัด execution-policy, PowerShell version และ script/trust-prompt variance ออกจาก runtime boundary ได้ โดยยังรักษา Windows DPAPI CurrentUser และ PHP-side authenticated envelope เดิม Initial implementation cost สูงกว่า แต่ deployment และ testing มี deterministic artifact มากกว่า เหมาะกับงาน credential ระยะยาวมากกว่า

PowerShell worker คงเป็น diagnostic/reference implementation จนกว่า .NET design/plan จะได้รับอนุมัติ ห้ามเปลี่ยน production adapter ในงานนี้

## Proposed .NET Boundary

- Target framework: `.NET 10 LTS`
- Publish: Release, self-contained, single-file, RID `win-x64`; ไม่ใช้ trimming หรือ NativeAOT ในรอบแรกเพื่อลด compatibility risk
- Build dependency: .NET 10 SDK บน isolated build environment
- Target dependency: Windows 10 x64 หรือใหม่กว่า; ไม่ต้องติดตั้ง .NET runtime เมื่อใช้ self-contained artifact
- Proposed fixed deployment path: `C:\ProgramData\BackupPhpMyAdmin\SecretProvider\backup-secret-helper.exe`
- PHP invocation ใช้ command array โดยไม่มี shell: `[fixedExecutable, operation]`
- Argument มีเพียง allowlisted operation: `identity`, `protect`, `unprotect`, `harden-directory`, `harden-file`
- Secret, protected inner payload, entropy, expected SID และ ACL path เดินทางใน bounded JSON ผ่าน anonymous STDIN เท่านั้น
- STDOUT เป็น bounded stable JSON; expected failuresไม่เขียน raw exception/secret ลง STDERR
- คง timeout 5 วินาที, request 16 KiB, output 64 KiB และ stable error mapping
- Helper อ่าน Windows token/SID ของ process จริงและตรวจ expected SID ก่อน DPAPI/ACL ทุกครั้ง
- DPAPI ใช้ `ProtectedData` กับ `DataProtectionScope.CurrentUser` เท่านั้น

## Preserved Application Contracts

- `SecretRole`, exact endpoint `127.0.0.1:3306`, MySQL 8.4.3 และ server fingerprint ยังคงอยู่ใน PHP typed domain
- Versioned authenticated envelope, metadata-derived entropy และ inner/outer comparison ไม่เปลี่ยน
- Separate role envelopes และ create-only private atomic store ไม่เปลี่ยน
- Secret directory ACL ยังคง current SID only; executable directory แยก ACL เป็น Administrators/SYSTEM modify และ Apache/CLI identity read/execute
- ไม่มี raw-SQL, database connection, credential migration หรือ plaintext fallback
- เนื่องจากยังไม่เคย enroll live credential จึงไม่สร้าง migration workflow ที่ไม่จำเป็น

## Deployment Controls Requiring Future Approval

1. Written .NET helper design and implementation plan
2. Build/release provenance, dependency lock และ reproducible SHA-256 manifest
3. Authenticode signing certificate and trusted-publisher strategyสำหรับ executable
4. Protected ProgramData deployment directory และ exact NTFS ACL
5. PHP adapter changeจาก fixed `.ps1` เป็น fixed `.exe` พร้อม signer/hash verification
6. Synthetic DPAPI/ACL tests ภายใต้ CLI และ Apache SID จริง
7. Rollback ที่ลบเฉพาะ helper artifact/version ที่ deployment journal เป็นเจ้าของ

จนกว่าจะอนุมัติรายการข้างต้น PowerShell production adapter ยังคง fail closed และ live credential provisioning/restore execution ต้องคง disabled
