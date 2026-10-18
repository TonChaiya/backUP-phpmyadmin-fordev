# Windows Secret Provider Design

สถานะ: **Superseded for production worker by Native .NET helper design**  
วันที่: 2026-10-18  
ขอบเขต: DPAPI CurrentUser secret storage สำหรับ Restore Integration เท่านั้น

## 1. Objective and Safety Boundary

เพิ่ม production-grade Windows Secret Provider สำหรับ credential สอง role คือ `backup_reader` และ `restore_runner` โดยผูกความสามารถถอดรหัสกับ Windows security context และ exact Laragon server binding แต่ยังไม่เชื่อม provider เข้ากับ MySQL connection factory หรือ Restore Integration Runner

รอบนี้ไม่มี MySQL connection, account provisioning, SQL, backup/restore, database operation หรือ configuration change ใด ๆ Production mysqli constructor, factory, runner และ HTTP live actions ต้องคง `live_restore_execution_disabled`

## 2. Selected Architecture

ใช้ Windows DPAPI `DataProtectionScope.CurrentUser` ผ่าน fixed PowerShell worker ที่เรียกด้วย `proc_open` command array

- PHP ส่ง operation ที่เป็น allowlist (`identity`, `protect`, `unprotect`, `harden-directory`, `harden-file`) เท่านั้น
- Worker เป็นไฟล์คงที่ใน repository ไม่มี `Invoke-Expression`, script block จาก input, dynamic command, shell interpolation หรือ downloaded code
- Secret ส่งผ่าน anonymous STDIN pipe เป็น base64 ภายใน bounded JSON เท่านั้น
- Secret ไม่อยู่ใน arguments, environment, temporary plaintext file, log หรือ error text
- Worker stdout เป็น bounded JSON protocol; stderr ว่างสำหรับ expected failures และ PHP ไม่บันทึก raw worker output
- Runtime ที่ไม่ใช่ Windows, PowerShell/DPAPI ขาด, identity ไม่ตรง, worker timeout หรือ malformed output ต้อง fail closed

ไม่เลือก PHP FFI เพราะ runtime ปัจจุบันไม่มี FFI และไม่เลือก Windows Credential Manager native API เพราะต้องเพิ่ม extension/native binding โดยไม่จำเป็น

## 3. Components

| Component | Responsibility |
|---|---|
| `SecretRole` | enum exact สองค่า; reject role อื่นทุกกรณี |
| `SecretServerBinding` | immutable endpoint/product/version/native fingerprint; endpoint ต้องเป็น `127.0.0.1:3306` และห้าม 3333 |
| `WindowsIdentity` | immutable account name และ SID ที่ validated |
| `RuntimeIdentityProvider` | คืน identity ปัจจุบันโดยไม่รับ identity จาก caller |
| `SecretProtector` | protect/unprotect opaque bytes ด้วย DPAPI และ additional entropy |
| `SecretEnvelopeStore` | create/read/delete exact role envelope ภายใต้ private directory; ไม่มี arbitrary path |
| `WindowsAclHardener` | ปิด inheritance และให้ current SID เท่านั้นที่อ่าน/เขียน secret directory/file |
| `PowerShellDpapiWorker` | production adapter สำหรับ fixed worker, timeout, byte limits และ stable errors |
| `WindowsSecretProvider` | orchestration, binding validation, envelope integrity comparison และ secret lifecycle |
| `SecretValue` | non-stringable/non-serializable wrapper; redacted debug view และ best-effort memory clearing |

Interfaces แยก OS process, identity, protector, ACL และ filesystem ออกจาก orchestration เพื่อให้ offline tests ใช้ deterministic fakes โดยไม่เรียก DPAPI จริง

## 4. Role and Server Binding

ทุก operation ต้องรับ typed role และ typed binding:

- Roles: `backup_reader`, `restore_runner`
- Endpoint: `127.0.0.1:3306` exact
- Product: `mysql`
- Version: `8.4.3`
- Native server fingerprint: nonempty canonical value เช่น `mysql:<server_uuid>`

Binding ทุก fieldรวม role และ envelope version ถูกใช้สร้าง canonical additional entropy สำหรับ DPAPI หาก endpoint, product, version, fingerprint หรือ role เปลี่ยน ciphertext ต้องถอดไม่ได้ แม้ไฟล์ถูกย้ายหรือ rename

Provider ไม่มี default binding, wildcard, port fallback หรือ plaintext fallback

## 5. Windows Identity Binding

DPAPI ใช้ `CurrentUser` และ envelope บันทึก Windows SID/account name ที่ worker ตรวจพบตอน protect

ก่อน decrypt ทุกครั้ง:

1. PHP อ่าน runtime identity จาก worker
2. validate SID syntax และ exact equality กับ envelope metadata
3. ส่ง expected SID เข้า operation `unprotect`
4. Worker ตรวจ SID ของ process ตนเองซ้ำก่อนเรียก DPAPI
5. หลัง decrypt เปรียบเทียบ inner protected metadata กับ outer envelope อีกครั้ง

หาก CLI กับ Apache รันคนละ SID, account เปลี่ยน, impersonation context ไม่ตรง หรือ identity หาไม่ได้ ให้คืน `windows_identity_mismatch`/`windows_identity_unavailable` โดยไม่ลอง fallback

ผลสำรวจปัจจุบันเป็น read-only evidence ว่า CLI และ Apache ทำงานเป็น `DESKTOP-BVH8F8L\Mainam8888` SID `S-1-5-21-2261628475-1108967253-567926701-1000` แต่หลักฐานนี้ไม่ถูก hard-code และต้องตรวจใหม่ใน runtime จริงทุกครั้ง

## 6. Versioned Envelope and Integrity

Envelope JSON version 1 เก็บเฉพาะ:

- format/version identifier
- exact role
- exact server binding
- Windows SID และ account label
- creation timestamp
- DPAPI ciphertext base64

Plaintext ที่ส่งเข้า DPAPI เป็น inner payload ซึ่งมี metadata canonical ชุดเดียวกันและ secret bytes Additional entropy คำนวณจาก canonical outer metadata ทั้งหมด

Integrity verification มีสามชั้น:

1. strict envelope schema/size/base64 validation
2. DPAPI authenticated protection พร้อม metadata-derived entropy
3. constant-time comparison ระหว่าง inner protected metadata กับ outer metadataหลัง decrypt

การแก้ role, binding, SID, timestamp, version หรือ ciphertext ต้องจบเป็น `secret_envelope_tampered` หรือ `secret_decryption_failed` โดยไม่เปิดเผยรายละเอียด cryptographic error

## 7. Storage, ACL and Atomicity

Default location คือ `storage/private/restore-integration-secrets` ซึ่ง root `.htaccess` ปิดการเข้าถึง `storage/` อยู่แล้ว และอยู่นอก public web root

- ชื่อไฟล์กำหนดตายตัวต่อ role; caller ระบุ path ไม่ได้
- Worker ยอมรับ ACL path เฉพาะ private directory และ exact role/temp filename ใต้ project root พร้อม reject reparse point/junction ใน path chain
- สร้าง directory แล้ว harden NTFS ACL ก่อนเขียน
- ปิด inherited ACL และให้ Full Control เฉพาะ current SID; ไม่เพิ่ม broad Users/Everyone entry
- เขียน envelope ลง same-directory random temporary file ด้วย exclusive create
- harden ACL ของ temporary file, write bounded JSON, `fflush`, close แล้ว rename ไป exact final path
- หาก final fileมีอยู่, ACL hardening ล้มเหลว, write/flush/rename ล้มเหลว ให้ลบเฉพาะ temporary fileที่สร้างเองและ fail closed
- ไม่มี in-place update; initial implementation เป็น create-only ป้องกัน overwrite credential เดิมโดยไม่ตั้งใจ
- delete ใช้ exact role path และต้องตรวจ SID/binding/envelope integrityก่อนลบ; ใช้เฉพาะ future authorized rollback workflow

## 8. Worker Protocol and Limits

- Worker timeout: 5 secondsต่อ operation
- Secret input: 1–4096 bytes; empty secret rejected
- STDIN request: ไม่เกิน 16 KiB
- Ciphertext/envelope/worker output: ไม่เกิน 64 KiB
- JSON depth/field set fixed; unknown fields rejected
- `proc_open` ใช้ command array และ fixed absolute `powershell.exe`/script paths
- process ถูก terminate เมื่อ timeout; pipes ถูกปิดใน `finally`
- worker errors map เป็น allowlisted codesเท่านั้น เช่น `worker_timeout`, `worker_failed`, `secret_input_too_large`, `secret_decryption_failed`, `acl_hardening_failed`
- exception, stderr, PowerShell stack trace, input/output payload หรือ secret length detailห้ามส่งไป logger/API/browser

## 9. Secret Lifecycle

### Store

typed role + full server binding + secret → runtime identity check → canonical metadata/entropy → DPAPI protect → ACL-hardened atomic envelope create → return opaque success statusโดยไม่มี secret/ciphertext

### Retrieve

typed role + full expected binding → exact envelope read → schema/size validation → runtime SID check → DPAPI unprotect with canonical entropy → inner/outer metadata comparison → return `SecretValue` ใน process memoryเท่านั้น

### Use and disposal

Consumer ต้องเรียก reveal ใน narrow scope และห้าม serialize/log/return ค่า `SecretValue` Destructor ใช้ `sodium_memzero` แบบ best effort แต่ PHP copy-on-write ทำให้รับประกัน complete zeroizationไม่ได้ จึงต้องลด lifetime และ references

### Delete

ไม่ expose ผ่าน HTTP/ทั่วไป และไม่ใช้ในรอบนี้ Future rollback ต้องมี separate authorization และ ownership evidenceก่อนเรียก exact-role delete

## 10. Apache/CLI Runtime Handling

- เพิ่ม offline identity-inspection commandที่เปิดเผยเฉพาะ account/SID และไม่อ่าน secret
- Runbook บังคับตรวจ CLI และ Apache identity ก่อนเก็บ secret
- หาก identities ต่างกัน ต้องเลือก security context เดียวที่ Apache ใช้และ provision envelope ภายใต้ context นั้นด้วย approved operator procedure; ห้าม copy DPAPI envelopeจาก SID อื่นหรือใช้ machine-wide scope
- ไม่มี HTTP endpoint สำหรับ store/retrieve/delete secret
- Apache child process identity drift หลัง restartทำให้ retrieve fail closed จน operator ตรวจและ provision ใหม่อย่างมี authorization

## 11. Logging and Public Exposure

- Provider exceptionsมีเฉพาะ stable code ไม่มี secret, ciphertext, entropy, worker output, absolute path หรือ raw OS exception
- Classes ที่ถือ secretห้าม implement string conversion/serialization และ debug outputต้อง redacted
- Job JSON, manifest, ownership journal, TEST_BASELINE, browser/API response และ application logห้ามมี secret/ciphertext
- Tests ใช้ sentinel แล้ว scan output/artifacts ว่าไม่พบ sentinel

## 12. Testing Strategy

Change-based offline tests ใช้ fake protector, fake identity, fake ACL hardener และ temporary directory:

- role separation และ exact filenames
- missing secret
- wrong SID/account
- wrong role
- wrong endpoint/product/version/fingerprint
- tampered outer metadata/ciphertext/inner metadata
- malformed/oversized/truncated envelope
- protector/worker timeout/failure/decrypt failure
- ACL hardening failure
- atomic write/flush/rename/final-collision failure
- no overwrite/no plaintext fallback
- no secret in exception, debug, serialized output, filesอื่น หรือ test output
- CLI/Apache identity comparison logic
- production hard-disable regression for mysqli factory/runner/API

ไม่รัน Full Regression ไม่มี live MySQL test และไม่ใช้จริง credential หากมี optional DPAPI smoke ในอนาคตต้องใช้ synthetic secret, dedicated temporary directory และลบ artifactที่สร้างเองเท่านั้น

## 13. TRIGGER and Dump Compatibility

คง privilege/parser policy เดิมทั้งหมด `TRIGGER` ยังเป็น dual-use metadata risk ที่ต้อง explicit acceptance; `EVENT`, `EXECUTE` และ global `SHOW_ROUTINE` ยังคงไม่อนุญาต Sources ที่มี events/routines หรือมี unsupported dump constructs ต้องหยุดแบบ fail closed

Secret Provider ไม่แก้ grant matrix, BackupService, SQL parser, artifact eligibility หรือ Restore safety gates

## 14. Acceptance Criteria

- Secret plaintextเข้าสู่ child processผ่าน STDIN เท่านั้นและไม่ถูก persist/log/expose
- DPAPI CurrentUser + SID + role + full server binding ถูกตรวจทุก retrieval
- metadata/ciphertext tampering ถูก reject
- envelope อยู่ private path พร้อม hardened ACL และ atomic create
- stable bounded worker protocol fail closedทุก error
- fake-based testsครอบคลุม required negative cases
- production MySQL factory/runner/APIยัง hard-disabled
- ไม่มี database/account/grant/backup/restore operationเกิดขึ้น

## 15. Remaining Live Boundaries

หลัง implementation ยังต้องได้รับ authorization แยกสำหรับ:

1. ตรวจ Apache/CLI identity หลัง deployment/restartจริง
2. ทดสอบ DPAPI smoke ด้วย synthetic secret หากต้องการหลักฐาน OS integration
3. Credential provisioning และ secret enrollmentจริง
4. Read-only MySQL readiness
5. Live integration

งานรอบนี้หยุดก่อนทุกข้อข้างต้น
