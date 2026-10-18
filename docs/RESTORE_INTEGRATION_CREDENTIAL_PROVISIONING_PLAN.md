# Restore Integration — Dedicated Credential Provisioning Plan

สถานะ: **Preparation only / NOT AUTHORIZED / NOT VERIFIED**  
วันที่: 2026-10-18  
Run ID ที่ผูกกับแผน: `b0108a9219ca1f50f8b5490f73d28f4c`

เอกสารนี้เป็น procedure สำหรับ DBA ในอนาคต ไม่ใช่ authorization และไม่ใช่ SQL executor ของแอป การดำเนินการทุกคำสั่งในหัวข้อ Provisioning/Rollback ต้องได้รับอนุญาตแยกต่างหากก่อนเสมอ

## 1. Fixed Boundary

- Server: Laragon local MySQL 8.4.3 เท่านั้น
- TCP endpoint: `127.0.0.1:3306` เท่านั้น
- ห้ามใช้ `127.0.0.1:3333`, remote endpoint หรือ socket fallback
- Account identities ที่ตั้งใจสร้าง:
  - `'backup_reader'@'localhost'`
  - `'restore_runner'@'localhost'`
- Client ต้องบังคับ TCP ไป `127.0.0.1:3306` และ `CURRENT_USER()` ต้องคืน `backup_reader@localhost` หรือ `restore_runner@localhost` ตาม role
- หาก `skip_name_resolve` หรือ host matching ทำให้ identity ไม่ตรง ให้หยุด ห้ามแก้ด้วย `%`, host wildcard หรือสร้าง account สำรองเพิ่ม
- Source scope มีเพียง `autoprintdb`, `drugmap_db`, `drug_stock_check`, `prepack_labels`
- ฐานที่ได้รับการคุ้มครองและฐานอื่นนอก allowlist ห้ามถูก query, enumerate, grant, backup, restore หรือ cleanup

MySQL แยก user/host เป็น account identity และการละ host มีความหมายเท่ากับ `%`; จึงต้องระบุ host อย่างชัดเจน ([MySQL 8.4 account names](https://dev.mysql.com/doc/refman/8.4/en/account-names.html)).

## 2. Prerequisites Before Any Provisioning

ต้องได้รับ **Credential Provisioning Authorization** ที่ระบุ server fingerprint, endpoint, run ID, account identities และ exact scopes ด้านล่างก่อน จากนั้น DBA ต้องตรวจตามลำดับ:

1. ยืนยันจาก OS/process/config association ว่า endpoint คือ Laragon MySQL 8.4.3 ที่ `127.0.0.1:3306` และไม่ใช่ listener 3333
2. ตรวจ native `@@server_uuid`, `@@port`, version/product และเก็บ fingerprint แบบ redacted
3. ตรวจว่า account exact ทั้งสองยังไม่มีอยู่; หากชื่อใดมีอยู่แล้วให้ STOP ห้าม `ALTER USER`, reuse หรือ drop
4. ตรวจ `@@global.partial_revokes`, `@@skip_name_resolve`, mandatory/default/active roles และ account host matching
5. ยืนยัน target ทั้งสี่เป็นชื่อใหม่และยังไม่เคยมีอยู่; ห้าม drop เมื่อ collision
6. สร้าง provisioning journal นอก repository โดยมี run ID, server fingerprint, endpoint, exact account identity, เวลา, ผู้ดำเนินการ และสถานะ created/not-created; ห้ามเก็บ credential หรือ raw grants

## 3. Backup Reader Privilege Matrix

| Scope | Exact privileges | เหตุผล | ห้ามมี |
|---|---|---|---|
| แต่ละ source สี่ชื่อแยกกัน | `SELECT` | อ่าน rows และ metadata ที่มองเห็นได้ | write DML, global `SELECT` |
| แต่ละ source สี่ชื่อแยกกัน | `SHOW VIEW` | `BackupService` ใช้ `SHOW CREATE VIEW` | `CREATE VIEW` |
| แต่ละ source สี่ชื่อแยกกัน | `TRIGGER` | `BackupService` ใช้ `SHOW TRIGGERS`/`SHOW CREATE TRIGGER`; เป็น dual-use privilege จึงต้องมี explicit live risk acceptance | global scope หรือ scope อื่น |
| แต่ละ source สี่ชื่อแยกกัน | `LOCK TABLES` | รองรับ consistent table read/MyISAM path เดิม | DML/DDL อื่น |

Exact database grant scopes:

- `` `autoprintdb`.* ``
- `` `drugmap\_db`.* ``
- `` `drug\_stock\_check`.* ``
- `` `prepack\_labels`.* ``

`_` และ `%` มี wildcard semantics สำหรับ database-level grants และ backticks อย่างเดียวไม่ทำให้เป็น literal จึงต้อง escape แม้ `partial_revokes` จะเปิดหรือปิด ([MySQL 8.4 GRANT](https://dev.mysql.com/doc/refman/8.4/en/grant.html), [partial revokes](https://dev.mysql.com/doc/refman/8.4/en/partial-revokes.html)).

### Metadata limitation / blocker

- ไม่ grant `EVENT`: privilege นี้อนุญาตสร้าง/แก้/ลบ event ไม่ใช่ read-only metadata privilege
- ไม่ grant `EXECUTE`: routine อาจมี side effect และ `SHOW CREATE PROCEDURE/FUNCTION` อาจยังคืน body เป็น `NULL`
- ไม่ grant global `SHOW_ROUTINE` หรือ global `SELECT`: จะเปิด metadata นอก allowlist
- ดังนั้น events/routines ต้องไม่มีใน source ที่จะใช้ทดสอบ และ absence ต้องมี exact-source DBA attestation แยกต่างหาก; ถ้าพบหรือพิสูจน์ไม่ได้ให้ STOP
- `TRIGGER` เป็นความเสี่ยง dual-use ที่ MySQL ไม่มี read-only equivalent สำหรับ metadata path ปัจจุบัน หากไม่ยอมรับความเสี่ยงนี้ ให้หยุด provisioning แทนการลด safety gate

MySQL ระบุว่า `SHOW CREATE PROCEDURE/FUNCTION` ต้องอาศัย definer, global `SHOW_ROUTINE`/`SELECT` หรือ routine privilege และ privilege แบบ `EXECUTE` อาจไม่เปิดเผย body ครบถ้วน ([SHOW CREATE PROCEDURE](https://dev.mysql.com/doc/refman/8.4/en/show-create-procedure.html)).

## 4. Restore Runner Privilege Matrix

Restore Runner ไม่มีสิทธิ์บน source ใด ๆ และไม่มี global privilege สิทธิ์ทุกชุดผูกกับ target exact ของ run นี้เท่านั้น:

| Exact target | Grant scope |
|---|---|
| `restore_test_autoprintdb_b0108a9219ca1f50f8b5490f73d28f4c` | `` `restore\_test\_autoprintdb\_b0108a9219ca1f50f8b5490f73d28f4c`.* `` |
| `restore_test_drugmap_db_b0108a9219ca1f50f8b5490f73d28f4c` | `` `restore\_test\_drugmap\_db\_b0108a9219ca1f50f8b5490f73d28f4c`.* `` |
| `restore_test_drug_stock_check_b0108a9219ca1f50f8b5490f73d28f4c` | `` `restore\_test\_drug\_stock\_check\_b0108a9219ca1f50f8b5490f73d28f4c`.* `` |
| `restore_test_prepack_labels_b0108a9219ca1f50f8b5490f73d28f4c` | `` `restore\_test\_prepack\_labels\_b0108a9219ca1f50f8b5490f73d28f4c`.* `` |

Privileges ต่อ exact target: `CREATE`, `DROP`, `ALTER`, `INDEX`, `INSERT`, `SELECT`, `REFERENCES`, `LOCK TABLES`, `CREATE VIEW`

- `CREATE`/`DROP` จำเป็นสำหรับ exact new target และ object ภายใน target
- `ALTER`, `INDEX`, `REFERENCES` ใช้กับ typed table/constraint plan
- `INSERT`, `SELECT` ใช้ restore และ deterministic validation/ownership marker
- `LOCK TABLES` ใช้เฉพาะ typed lock statements
- `CREATE VIEW` ใช้เฉพาะ typed placeholder-view statement ที่ structural parser รับรอง
- ไม่ให้ `UPDATE`, `DELETE`, `EVENT`, `EXECUTE`, routine privileges, account/admin/file/replication/plugin privileges, role หรือ `GRANT OPTION`

MySQL อนุญาต database-level grants บนฐานที่ยังไม่มีอยู่เพื่อเตรียม account ล่วงหน้า แต่การ drop database ไม่ลบ grants เหล่านั้นอัตโนมัติ จึงต้อง rollback account exact หลัง cleanup ([MySQL 8.4 GRANT](https://dev.mysql.com/doc/refman/8.4/en/grant.html), [DROP DATABASE](https://dev.mysql.com/doc/refman/8.4/en/drop-database.html)).

## 5. Reviewed Account Creation Procedure (Future Authorization Only)

คำสั่งตัวอย่างต่อไปนี้ **ห้ามรันในรอบนี้** และต้องรันโดย DBA ใน protected local console เท่านั้น:

```sql
CREATE USER 'backup_reader'@'localhost'
  IDENTIFIED BY RANDOM PASSWORD ACCOUNT LOCK;

CREATE USER 'restore_runner'@'localhost'
  IDENTIFIED BY RANDOM PASSWORD ACCOUNT LOCK;
```

ห้ามใช้ `IF NOT EXISTS`: account collision ต้อง fail และหยุด ห้ามแก้ account เดิม MySQL 8.4 รองรับ server-generated random password และคืนค่าเพียงครั้งเดียวแก่ผู้ดำเนินการ ([CREATE USER](https://dev.mysql.com/doc/refman/8.4/en/create-user.html)).

หลังบันทึก generated credential เข้าระบบเก็บความลับที่อนุมัติแล้ว DBA จึง apply exact grants ทีละ scope:

```sql
GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON `autoprintdb`.* TO 'backup_reader'@'localhost';
GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON `drugmap\_db`.* TO 'backup_reader'@'localhost';
GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON `drug\_stock\_check`.* TO 'backup_reader'@'localhost';
GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON `prepack\_labels`.* TO 'backup_reader'@'localhost';

GRANT CREATE, DROP, ALTER, INDEX, INSERT, SELECT, REFERENCES, LOCK TABLES, CREATE VIEW ON `restore\_test\_autoprintdb\_b0108a9219ca1f50f8b5490f73d28f4c`.* TO 'restore_runner'@'localhost';
GRANT CREATE, DROP, ALTER, INDEX, INSERT, SELECT, REFERENCES, LOCK TABLES, CREATE VIEW ON `restore\_test\_drugmap\_db\_b0108a9219ca1f50f8b5490f73d28f4c`.* TO 'restore_runner'@'localhost';
GRANT CREATE, DROP, ALTER, INDEX, INSERT, SELECT, REFERENCES, LOCK TABLES, CREATE VIEW ON `restore\_test\_drug\_stock\_check\_b0108a9219ca1f50f8b5490f73d28f4c`.* TO 'restore_runner'@'localhost';
GRANT CREATE, DROP, ALTER, INDEX, INSERT, SELECT, REFERENCES, LOCK TABLES, CREATE VIEW ON `restore\_test\_prepack\_labels\_b0108a9219ca1f50f8b5490f73d28f4c`.* TO 'restore_runner'@'localhost';
```

ห้ามใช้ `ALL`, `ON *.*`, `restore_test_%`, unescaped `_`, role หรือ `WITH GRANT OPTION` หลัง grant verification ผ่านและ credential storage พร้อม จึง unlock exact accounts:

```sql
ALTER USER 'backup_reader'@'localhost' ACCOUNT UNLOCK;
ALTER USER 'restore_runner'@'localhost' ACCOUNT UNLOCK;
```

Application จะไม่รับหรือ execute statements เหล่านี้ และจะไม่มี config flag/HTTP request สำหรับ provisioning

## 6. Grant Verification Procedure

### DBA-side exact review

1. ตรวจ `SHOW GRANTS FOR 'backup_reader'@'localhost'` และ exact runner identity; raw outputอยู่ใน protected console เท่านั้น
2. ยอมรับ global `USAGE ON *.*` ได้เพราะหมายถึงไม่มี privilege; reject global privilege อื่นทั้งหมด
3. parse ทุก database scope ด้วย wildcard semantics ไม่ใช้ string prefix comparison
4. ตรวจไม่มี role assignment/default role/mandatory role ที่เพิ่ม effective privileges
5. ตรวจ `partial_revokes`; implementation ปัจจุบัน fail-closed หากไม่ใช่ `OFF`
6. ตรวจไม่มี `GRANT OPTION`, dynamic/global privilege, source scope บน runner หรือ target scope บน reader

### Dedicated-session readiness (ต้องขอ authorization ใหม่)

แต่ละ credential เชื่อมต่อแบบ TCP ไป `127.0.0.1:3306` แล้วตรวจเฉพาะ:

- `VERSION()`, `@@version_comment`, `@@server_uuid`, `@@port`
- `CURRENT_USER()`, `CURRENT_ROLE()`, `@@partial_revokes`
- `SHOW GRANTS FOR CURRENT_USER()` ซึ่ง MySQL อนุญาตให้ current account ดูของตนเองโดยไม่ต้อง `SELECT mysql.*` ([SHOW GRANTS](https://dev.mysql.com/doc/refman/8.4/en/show-grants.html))
- source existence/capability ด้วย exact predicates สี่ชื่อเท่านั้น
- target absence ด้วย exact predicates สี่ชื่อเท่านั้น
- shared lock quiescence และ restore safety gates

การพิสูจน์ negative scope ใช้ grants/roles/global-scope evidence; ห้ามทดลอง query ฐานคุ้มครองหรือฐานอื่นเพื่อดูว่าโดน deny หรือไม่ หลักฐาน live ทั้งหมดคงเป็น **NOT VERIFIED** จนกว่าจะได้รับ authorization และตรวจจริง

## 7. Credential Storage Policy

- เก็บ credential แยก role ใน Windows OS-protected secret store เช่น Credential Manager/DPAPI ภายใต้ principal ของ service/operator ที่จำเป็นเท่านั้น
- Suggested logical keys: `backup-app/mysql/127.0.0.1-3306/backup-reader` และ `backup-app/mysql/127.0.0.1-3306/restore-runner`
- ห้ามใช้ command-line argument, environment dump, source/config file, Git, test fixture, log, baseline, browser response, job JSON หรือ ownership journal
- ห้ามใช้ `cmdkey /pass:<value>` หรือคำสั่งใดที่วาง credential ใน command line/process list
- credential เข้าสู่ PHP process memory เฉพาะเมื่อ `WindowsSecretProvider` ที่ผ่าน offline review เรียกใช้ และต้องไม่ถูก serialize
- provider implementation มีแล้วแต่ยังไม่เชื่อม MySQL factory และ local PowerShell execution policy ยังบล็อก fixed worker; actual DPAPI/ACL จึง NOT VERIFIED และห้าม fallback ไป plaintext local config

## 8. Rollback for Newly Created Accounts

Rollback ต้องได้รับ authorization และทำได้เฉพาะเมื่อ provisioning journal พิสูจน์ว่า account exact ถูกสร้างโดย run นี้บน server fingerprint เดียวกัน:

1. lock exact accounts ก่อน
2. ยืนยันไม่มี session/job/operation lock ที่กำลังใช้ credentials
3. ยืนยัน identity, endpoint และ journal ownership ตรงทุกค่า
4. drop เฉพาะ `'backup_reader'@'localhost'` และ `'restore_runner'@'localhost'`; ห้าม wildcard/prefix
5. verify exact accounts หายไปและลบ secret entries จาก OS store
6. บันทึกผลแบบ redacted ใน journal

หาก account ใดมีอยู่ก่อน run, journal ขาด, identity/server mismatch หรือพิสูจน์ ownership ไม่ได้ ให้ **ห้าม DROP** และส่งต่อ DBA review

## 9. Authorization Boundaries Remaining

ต้องขออนุญาตแยกตามลำดับ:

1. Credential Provisioning Authorization — create/lock/grant/unlock exact accounts และ exact scopes เท่านั้น
2. Read-only Readiness Authorization — เชื่อมต่อด้วย dedicated accounts เพื่อตรวจ identity/grants/capabilities/target absence เท่านั้น
3. Secret-provider Deployment Authorization — แก้ code-signing/execution-policy blocker ที่ตรวจพบ, ยืนยัน DPAPI/ACL และ CLI/Apache SID parity โดยไม่เปิด live execution
4. Live Integration Authorization — ผูก exact run/server/artifacts/targets/expiry หลัง readiness ผ่านทั้งหมด
5. Credential Rollback Authorization — drop exact accounts เฉพาะเมื่อ journal ownership ผ่าน

Provisioning authorization ไม่อนุญาต backup, restore, create/drop target database หรือ cleanup และ authorization ใด ๆ ห้ามขยายไป schema นอก exact scope
