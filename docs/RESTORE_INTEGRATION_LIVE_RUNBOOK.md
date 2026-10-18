# Restore Integration Live Runbook

สถานะเอกสาร: **Milestone 10B + Dedicated Credential Provisioning Preparation เท่านั้น**  
วันที่ปรับปรุง: 2026-10-18  
Live database authorization: **ยังไม่ได้รับอนุญาต**

## 1. ขอบเขตและหลักหยุดงาน

Runbook นี้เตรียมขั้นตอนสำหรับการตรวจความพร้อมในอนาคต แต่ไม่อนุญาตให้เชื่อมต่อ MySQL, provision credential, สำรองข้อมูล, restore, compare หรือ cleanup ในรอบ 10B

- Server ที่อนุญาตในอนาคต: Laragon MySQL 8.4.3 บนเครื่องนี้เท่านั้น
- Endpoint ที่ต้องตรงแบบ exact: `127.0.0.1:3306`
- Endpoint ที่ห้ามใช้: `127.0.0.1:3333` และ endpoint อื่นทั้งหมด
- Source allowlist แบบ exact: `autoprintdb`, `drugmap_db`, `drug_stock_check`, `prepack_labels`
- ห้ามใช้บัญชี `root` เป็น runtime credential
- ห้าม query, discover, inspect, backup, restore, modify หรือ drop `jhcisdb`
- ห้ามใช้ `SHOW DATABASES`; discovery ต้องใช้ชื่อ exact จาก allowlist/target proposal เท่านั้น
- Live Restore ยังต้อง hard-disabled จนกว่าจะมี authorization แยกต่างหากหลัง readiness ผ่าน

หากหลักฐานใดขาดหายหรือพิสูจน์ขอบเขตไม่ได้ ให้บันทึก `NOT VERIFIED` และหยุดก่อนการทำ live operation

## 2. หลักฐานจาก Milestone 10A ที่นำมาใช้

| รายการ | สถานะ 10A |
|---|---|
| Laragon binary/version | พบ `mysqld.exe` ของ Laragon เวอร์ชัน 8.4.3 จาก OS/file metadata |
| Listener ที่คาดหมาย | พบ process ฟังพอร์ต 3306 และ Laragon `my.ini` ระบุพอร์ต 3306 |
| Native server identity | **NOT VERIFIED** เพราะไม่ได้เชื่อมต่อฐานข้อมูล |
| Dedicated credentials | **MISSING / NOT VERIFIED**; ไม่พบหลักฐานของ `backup_reader` และ `restore_runner` |
| Actual grants, roles, `partial_revokes` | **NOT VERIFIED** |
| Source capability สี่ฐาน | **NOT VERIFIED** |
| Target absence/isolation | **NOT VERIFIED** |
| Artifact 64 MiB compatibility | metadata ที่พบไม่เกิน limit; directory ของ `prepack_labels` ไม่พบ จึงยังไม่ใช่ live completeness proof |
| Shared-lock atomic quiescence | **NOT VERIFIED**; snapshot ไม่พบ lock file แต่ไม่ได้รัน migration check ที่เขียนไฟล์ |
| Restore safety gate | production factory/runner/API ยังคง hard-disabled |

ตรวจพบ MySQL listener อีกตัวที่พอร์ต 3333 แต่ยังยืนยัน executable/native identity ไม่ได้ จึงถือเป็น **STOP endpoint** โดยไม่มีข้อยกเว้น

## 3. Plan-only Readiness Checker

คำสั่งต่อไปนี้เป็น offline plan-only และไม่รับ credential หรือ connection option:

```powershell
& 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tools\restore-integration-readiness.php --pretty
```

ผลลัพธ์ประกอบด้วย run ID 32 ตัวอักษรฐานสิบหก, source สี่ชื่อ, target สี่ชื่อแบบ exact, expected/forbidden endpoint, check status และ stop conditions เท่านั้น Default status ต้องเป็น `blocked`; หลักฐาน live ที่ยังไม่ตรวจต้องเป็น `not_verified`

Checker ปัจจุบันเป็น pure evidence evaluator และไม่เปิด socket เอง โดยจะยอมให้สถานะ `ready_for_live_authorization_review` เฉพาะเมื่อ typed evidence ทุกกลุ่มผ่านทั้งหมด:

1. reader/runner ระบุ endpoint `127.0.0.1:3306` แบบ exact และไม่ใช่พอร์ต 3333
2. product/version คือ MySQL 8.4.3 และ native stable ID มีรูปแบบที่รองรับ
3. identity ของ credential ทั้งสองตรงกันแบบ cryptographic fingerprint
4. credential มีอยู่จริง แยก role กัน และไม่ใช่ root
5. privilege attestation ผ่าน exact scope โดยไม่มี global, wildcard, inherited/active role ambiguity หรือ `partial_revokes` ambiguity
6. source capabilities ครบเฉพาะ allowlist สี่ชื่อ ไม่มีชื่อเพิ่ม
7. target absence ครบเฉพาะ target proposal สี่ชื่อ และทุกชื่อยังไม่เคยมีอยู่
8. shared/legacy lock migration พิสูจน์ quiescent
9. restore execution ยัง hard-disabled

Checker ไม่เก็บ password, raw grant text หรือ account host details ใน public output

## 4. Dedicated Credential Provisioning Plan

ขั้นนี้เป็นแผนสำหรับ DBA เท่านั้นและ **ห้ามดำเนินการจนกว่าจะได้รับ Credential Provisioning Authorization แยกต่างหาก** Application และ readiness CLI จะไม่สร้าง user และไม่ออก `GRANT`/`REVOKE`

รายละเอียด exact identities, escaped scopes, reviewed SQL procedure, credential storage และ rollback อยู่ใน [RESTORE_INTEGRATION_CREDENTIAL_PROVISIONING_PLAN.md](RESTORE_INTEGRATION_CREDENTIAL_PROVISIONING_PLAN.md) เอกสารดังกล่าวยังเป็น preparation-only และไม่ใช่ live authorization

### 4.1 Identity และ secret handling

- สร้าง principal สองรายแยกกัน: role label `backup_reader` และ `restore_runner`
- จำกัด host ให้ตรงกับ TCP loopback ที่อนุมัติ; ห้ามใช้ `%` หรือ remote host pattern
- ห้าม reuse root/admin account และห้ามให้ account-management privilege
- สร้าง password แบบสุ่มนอก repository; ส่งผ่านช่องทางลับที่ผู้ดูแลกำหนด
- เก็บค่า runtime ภายนอก source control และห้ามบันทึก password/raw grants ลง `TEST_BASELINE.md`, job log หรือ CLI output
- หลัง provisioning ต้องตรวจ `CURRENT_USER()` ของแต่ละ credential เพื่อยืนยัน account ที่ server เลือกจริง

### 4.2 Exact privilege matrix

| Role | Exact positive scope | Privileges ที่ต้องพิสูจน์ | ต้องไม่มี |
|---|---|---|---|
| `backup_reader` | แต่ละ source ทั้งสี่ชื่อแยกกัน | `SELECT`, `SHOW VIEW`, `TRIGGER`, `LOCK TABLES`; `TRIGGER` เป็น dual-use metadata risk ที่ต้องยอมรับโดย explicit authorization | write DML, `EVENT`, `EXECUTE`, global `SHOW_ROUTINE`, DROP, FILE, PROCESS, SHOW DATABASES, account/admin/dynamic global privileges, GRANT OPTION, source/ฐานอื่น |
| `restore_runner` | target proposal สี่ชื่อแยกกันสำหรับ run ID เดียว | `CREATE`, `DROP`, `ALTER`, `INDEX`, `INSERT`, `SELECT`, `REFERENCES`, `LOCK TABLES`, `CREATE VIEW` | source privilege ทุกชนิด, global/admin/file/account/replication/plugin privilege, GRANT OPTION, prefix/wildcard target scope |

ชื่อ schema ที่มี `_` ต้องถูกประเมินตาม MySQL grant-pattern semantics และ escape wildcard ให้เป็น literal อย่างถูกต้อง การ quote identifier อย่างเดียวไม่ถือว่าพิสูจน์ exact scope ต้องตรวจ `@@global.partial_revokes`, active/default roles และ effective grants ทุกครั้ง ถ้า semantics ไม่ชัดเจนให้หยุด ไม่ให้ privilege กว้างขึ้น

Target grants ต้องผูกกับ exact target names จาก run proposal เท่านั้น ห้าม grant ด้วย `restore_test_%`, prefix หรือ wildcard และห้าม reuse target names จาก run ก่อนหน้า

### 4.3 Provisioning acceptance evidence

DBA ต้องส่งเฉพาะหลักฐาน redacted ต่อไปนี้เพื่อขอ read-only readiness authorization:

- run ID และ exact target names
- account role label และหลักฐานว่าไม่ใช่ root (ไม่ส่ง password)
- server endpoint/product/version/native stable fingerprint ที่ได้จากแต่ละ account
- ผล attestation ต่อ exact scope, wildcard semantics, active roles และ `partial_revokes`
- ยืนยันว่าไม่มี global/admin/GRANT OPTION/cross-role/cross-schema privilege

Raw grants ใช้เป็น input ของ attestor ในหน่วยความจำเท่านั้นและไม่คัดลอกลงรายงานสาธารณะ

## 5. ขั้นตอน Read-only Readiness ในอนาคต

ต้องได้รับ Read-only Readiness Authorization ใหม่ก่อนเริ่ม และ authorization นั้นไม่ครอบคลุม backup/restore/create/drop

1. ตรวจ OS process path/config association ซ้ำเพื่อยืนยัน Laragon instance และ endpoint 3306; ถ้าพบ drift ให้หยุด
2. เชื่อมต่อด้วย `backup_reader` ไปที่ `127.0.0.1:3306` โดยบังคับ TCP; เชื่อมต่อด้วย `restore_runner` แยก session ที่ endpoint เดียวกัน
3. เก็บ product/version และ native stable server ID จากทั้งสอง session; fingerprint ต้องตรงกัน
4. เก็บ current account, effective grant/role evidence และ MySQL `partial_revokes`; attestor ต้องยอมรับทั้งสอง role
5. ตรวจ source existence/capability ด้วย exact allowlist predicate เท่านั้น ไม่ใช้ unrestricted schema discovery และไม่ส่งชื่อ protected schema ใน query
6. ตรวจ target absence ด้วย exact target list จาก run proposal เท่านั้น; พบชื่อใดอยู่แล้วคือ target collision และต้องหยุดโดยห้าม drop
7. ตรวจ shared lock migration ด้วย exact legacy/shared paths เมื่อไม่มี database operation กำลังทำงาน; ไม่ถือว่าการไม่มีไฟล์ lock เพียงอย่างเดียวเป็น quiescence proof
8. ส่ง typed evidence เข้า checker และเก็บเฉพาะ redacted result
9. หยุดเพื่อ review; ห้ามต่อเนื่องไป live backup/restore โดยอัตโนมัติ

## 6. Stop Conditions

หยุดทันทีเมื่อเกิดข้อใดข้อหนึ่ง:

- endpoint ไม่ตรง `127.0.0.1:3306`, พบ/เลือกพอร์ต 3333 หรือ native identity พิสูจน์ไม่ได้
- product/version ไม่ตรง MySQL 8.4.3 หรือ reader/runner อยู่คนละ server fingerprint
- dedicated credential ขาดหาย, เป็น root, role ไม่แยก หรือ authentication fallback ไป account อื่น
- grant มี global scope, wildcard ambiguity, prefix scope, role/PUBLIC inheritance ที่แกะไม่ได้, `partial_revokes` ไม่ชัดเจน, excess/missing privilege หรือ cross-schema/cross-role scope
- กระบวนการต้องใช้ `SHOW DATABASES` หรือจำเป็นต้อง inspect ชื่อใดนอก exact allowlist/proposal
- source capability ใดไม่ครบ หรือพบ source key เพิ่มจาก allowlist
- target ใดมีอยู่แล้ว หรือ absence/isolation พิสูจน์ไม่ได้
- shared lock migration/quiescence พิสูจน์ไม่ได้
- restore hard-disable ถูกถอดหรือมี config/request ที่เปิด execution ได้

ห้ามแก้ blocker ด้วยการใช้ root, ขยาย grant, เปลี่ยน endpoint, inspect ฐานอื่น, drop target ที่ชน หรือปิด safety gate

## 7. Requirements Before Live Authorization

ก่อนขอ Milestone 11 authorization ต้องมีทั้งหมด:

- Credential Provisioning Authorization ที่ใช้ไปแล้วกับ DBA และหลักฐาน redacted ของ dedicated accounts
- Read-only Readiness Authorization และผล checker ที่ทุก check เป็น `pass`
- exact run ID, exact four targets, exact Laragon native server fingerprint และ artifact hashes ที่จะใช้
- source capability และ target absence ที่ตรวจจริง ณ endpoint 3306
- shared-lock migration/quiescence evidence
- approved ownership journal/marker/cleanup procedure สำหรับ exact targets
- independent security review finding closure หรือบันทึก risk acceptance ที่ชัดเจนสำหรับรายการที่ยังค้าง
- Live Integration Authorization ใหม่ที่ระบุ scope, expiry/single-use receipt และ stop conditions

จนกว่ารายการข้างต้นจะครบ สถานะ Live Backup, Live Restore, Compare และ Cleanup ต้องคงเป็น **NOT VERIFIED / NOT AUTHORIZED**

## 8. Windows Secret Provider Deployment Gate

Implementation ใช้ DPAPI `CurrentUser` ผ่าน fixed Native .NET Helper ที่ `C:\ProgramData\BackupPhpMyAdmin\SecretProvider\backup-secret-helper.exe` และเก็บ authenticated envelope แยก role ใต้ `SecretProvider\secrets` ไม่มี PowerShell production fallback, ไม่มี plaintext fallback และยังไม่ถูกเชื่อมกับ MySQL factory/runner

ก่อน credential provisioning ต้องผ่าน gate ต่อไปนี้ด้วย authorization แยก:

1. ตรวจ Authenticode, publisher/thumbprint, post-signing SHA-256, fixed executable path และ protected ACL ให้ผ่านครบ
2. รัน identity-only CLI ภายใต้ dedicated Apache service context แล้วพิสูจน์ account/SID ตรงกับ approved runtime SID
3. ใช้ synthetic secret ทดสอบ DPAPI CurrentUser protect/unprotect และ ACL ใน isolated temporary directory แล้วลบ artifact ที่สร้างเอง
4. ตรวจ ACL จริงว่าปิด inheritance และอนุญาต SID ที่คาดหมายเท่านั้น
5. ตรวจซ้ำหลัง Apache restart; identity drift ต้องหยุดและห้าม copy envelope ข้าม SID

Runtime data ต้องใช้ exact production root `C:\ProgramData\BackupPhpMyAdmin\RuntimeData` ซึ่งแยกจาก immutable application source ทุก path ของ backup, job, log, restore job, integration ownership, shared lock, temporary file และ application session ต้อง derive จาก root เดียว ห้ามใช้ junction/symlink หรือเก็บ production data ใต้ source/web root

ProgramData root และ directory ย่อยต้องถูกสร้างพร้อม ACL โดย authorized deployment ล่วงหน้า Application production mode จะตรวจ exact location/existence และ fail closed โดยไม่สร้าง directory เอง ส่วน development ยังคงใช้ `<project>\storage` ตามเดิม การ migration ต้อง copy แบบ non-destructive และห้ามลบหรือเขียนทับ artifact เดิม

สถานะเครื่อง ณ 2026-10-18: Native Helper ยังไม่มี usable Authenticode certificate/pins และ dedicated Apache SID/ProgramData ACL ยัง **NOT VERIFIED / BLOCKED** จึงห้าม credential enrollment หรือ live database operation
