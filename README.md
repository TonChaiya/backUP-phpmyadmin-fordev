# Local MySQL Export / Import Manager v1.0

เว็บ PHP สำหรับ Export/Import MySQL ผ่าน **Laragon บนเครื่องเดียว** ใช้ official `mysqldump.exe` และ `mysql.exe` ไม่ใช่ Custom SQL Engine ไม่มี Application Login หรือ Windows service เพิ่ม

[คู่มือใช้งาน](docs/LOCAL_USER_GUIDE.md) · [Architecture](docs/ARCHITECTURE.md) · [แนวทางสำหรับ AI/ผู้พัฒนา](AGENTS.md) · [ผลทดสอบ](TEST_BASELINE.md) · [ข้อจำกัด Import](docs/LOCAL_IMPORT_ACCEPTANCE_2026-10-19.md)

## v1.0 readiness — 2026-10-19

Version label นี้เป็นขอบเขต Source/เอกสาร ไม่ใช่ Git tag หรือ Release: ใช้บนเครื่อง Laragon ที่ตรวจแล้ว (Apache 2.4.66, PHP 8.3.30, MySQL/official clients 8.4.3) ไม่รับรอง MySQL/MariaDB รุ่นอื่นหรือเครื่องอื่นโดยอัตโนมัติ

Export และการเชื่อมต่อ/discovery มีหลักฐาน Live เดิม; Import มี client exit0, effective schema9/9 และ live-source values9/9 แต่ **overall historical Import acceptance ยังคง PARTIAL: backup snapshot-value fidelity NOT VERIFIED** ไม่ใช่ Full Backup/Restore verification ประวัติ failed เดิมคงเดิม

หน้า Import ใช้สถานะการเชื่อมต่อจริงจาก Settings เพื่อเปิด/ปิดการเตรียมงาน; การเตรียมงานไม่สร้างฐาน ส่วน execution ต้องยืนยัน IMPORT และผ่าน new-target/identity/privilege gates เดิม ไม่เริ่มงานเพียงเปิดหน้า

ข้อจำกัดคงค้าง: บัญชี Windows/runtime หรือ admin ที่ถูกยึดอาจอ่าน plaintext credentials ได้; concurrent DDL ไม่ถูก freeze; ฐานว่างจาก test incident เดิมยัง **OWNERSHIP UNPROVEN** จึงไม่ระบุชื่อจากการเดาหรือลบฐาน ข้อมูลเก่าใน Git history ไม่ถูก rewrite ดู TEST_BASELINE สำหรับหลักฐานและขอบเขตจริง

## ฟังก์ชันปัจจุบัน

- Dashboard / Export / Import / History / Settings บน UI เดิม
- Export หนึ่งฐาน หลายฐาน หรือทุกฐานที่อนุญาต ทำตามลำดับทีละฐาน พร้อม Job History และ shared operation lock
- Export เฉพาะ InnoDB base-table structure + rows ไม่ใช่ Full Database Backup; ไม่รวม Views, Triggers, Procedures/Functions, Events, Non-InnoDB และไม่รองรับ cross-schema FK
- Import เฉพาะ SQL จาก trusted application profile ที่มี Manifest/Job Provenance ผ่าน SHA-256 ใช้บัญชีจาก Settings และสร้างเฉพาะฐานใหม่ที่บันทึก Ownership
- History พร้อม Download ที่ตรวจ Session token, containment และ integrity ตามสัญญาไฟล์ปัจจุบัน; ไฟล์เก่าที่ไม่มี Manifest มี compatibility แบบ completion-marker-only จึงไม่เทียบเท่า Import eligibility
- แสดงผล Import แยก Execution / Schema / Live-source Data / SQL-snapshot Data / Verification ไม่รวมเป็น Success เดียว

## Requirements

- Windows x64 + Laragon Apache/PHP 8.3; mysqli/mysqlnd, JSON, Session, Hash และ `proc_open`
- Existing Laragon MySQL **8.4.3** ที่ `127.0.0.1:3306` และ official clients ใน `C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/`
- Code ตรวจ exact client/server version และ executable SHA-256; server UUID/CURRENT_USER ผูกกับโปรไฟล์ที่บันทึก ไม่ใช่รองรับ MySQL ทุก binary/server โดยอัตโนมัติ
- Local NTFS writable runtime/temp, temp อยู่นอก Web Root; fixed Windows PowerShell command สำหรับ private temporary ACL และ hard-link publication ที่ volume รองรับ
- ไม่มี Composer/npm runtime install, DPAPI/.NET helper, Signing หรือ Dedicated Apache dependency; Node.js ใช้เฉพาะ JS fixture test ที่เลือก

## ติดตั้ง/เปิดผ่าน Laragon ในเครื่องเดิม

1. วาง Source ที่ `C:/laragon/www/backUP-phpmyadmin-fordev` โดยเก็บ Source/Views/Assets/Config Templates และ `storage/.htaccess` ครบ ไม่ Copy ข้อมูลฐานหรือ Credential เข้า Git
2. ใช้ Laragon Apache/PHP เดิมที่เปิดใช้อยู่ พอร์ต8081, mod_rewrite และ AllowOverride ต้องรองรับ `.htaccess` ของโปรเจกต์ ไม่แก้ VirtualHosts/Service/MySQL อัตโนมัติ
3. เปิด [Dashboard](http://localhost:8081/backUP-phpmyadmin-fordev/) บนเครื่องนี้เท่านั้น; หรือใช้ `public/` เป็น Web Root ตามการตั้งค่าเดิม
4. Development bootstrap ใช้ `storage/` และสร้าง directories ที่ขาดเมื่อเปิดเว็บ Source ใหม่ไม่มี SQL/Jobs/Credentials จาก Git ให้เอง ห้ามย้าย/ลบ storage เดิมในการอัปเดต
5. ตรวจ prerequisites/client pins และโปรไฟล์ server ให้ตรงกับ Laragon ในเครื่อง ไม่แก้ binary hashes เพื่อข้าม safety gate

ไม่มีขั้นตอนสร้างฐาน/บัญชี/GRANT หรือ Restart Service ในการติดตั้ง Source นี้ การเตรียม MySQL role ต้องอนุมัติแยก

## Configuration

`config/app.php` เป็น default; optional ignored `config/local.php` merge ทับค่าที่อนุญาต ใช้ `config/local.php.example` เป็นตัวอย่างและ **ห้ามเขียนทับ local.php เดิม**

- `app_name`: ชื่อแอป
- `runtime_data_mode=development`, `runtime_data_root=<project>/storage`
- `max_backup_files=200`, `connect_timeout_seconds=5`
- Backup/Jobs/Logs/Sessions/Ownership/Shared Lock paths derive จาก root เดียว ไม่ตั้ง writable roots แยกกระจัดกระจาย
- ไม่ใส่ MySQL password ลง PHP config; old website auth hashes ไม่เปิด LAN/login
- production-mode path validation เก็บไว้เพื่อ compatibility เท่านั้น ไม่สลับไป ProgramData และไม่ Deploy ให้อัตโนมัติ

ตำแหน่ง Import เดิม `restore-jobs/local-import`, ownership `restore-integration-ownership/local-import` และ `database-operations/global.lock` ยังคงเดิม ไม่ต้อง migration ดู [Configuration/Storage reference](docs/ARCHITECTURE.md)

## Export

1. เปิด Settings เลือกบัญชี MySQL ปกติ ค่าเริ่มต้น `127.0.0.1:3306`, `root`, รหัสว่าง ระบบไม่ใช้ค่าเริ่มต้นจนกด Test/Save Dashboard/Export/Import/Settings ตรวจจริงเมื่อมีโปรไฟล์ที่บันทึก รวมถึง Session ใหม่
2. เลือกหนึ่งฐาน หลายฐาน หรือทั้งหมดจากฐานที่บัญชีใน Settings มองเห็นและ Export ได้ ไม่ได้จำกัด 4 ฐานที่เคยใช้ทดสอบ
3. ยืนยัน Export รอ persisted status ของแต่ละฐาน ระบบทำทีละฐาน
4. Download จาก History เท่านั้น ไม่เปิด SQL ผ่าน storage URL โดยตรง

บัญชีต้องอ่านฐานที่เลือกได้ ระบบ discovery เฉพาะชื่อ schema ตาม visibility ของบัญชี โดย SQL ไม่คืน `jhcisdb` และไม่ inspect ตารางของฐานนี้ System schemas (`information_schema`, `performance_schema`, `mysql`, `sys`) แสดงแยก ไม่เปิดให้ Export/Import และไม่รวม Select All ชื่อที่ไม่ปลอดภัยสำหรับไฟล์ Windows แสดงแยกเป็นยังไม่รองรับ

Select All หมายถึง eligible databases ทั้งหมดใน catalog ปัจจุบัน ไม่ใช่เฉพาะแถวที่ค้นหามองเห็น รายการที่ยืนยันผูกกับ Session/job; POST หรือ job file ที่เปลี่ยนชื่อไม่ขยาย scope ได้ ตรวจ account visibility อีกครั้งก่อนสำรอง และตรวจ selected scope ก่อนส่ง positional argument ให้ mysqldump งานเก่าที่ไม่มีหลักฐาน selection ไม่ resume อัตโนมัติ
หากไม่มีรหัสให้ติดต่อผู้ดูแล เว็บไม่ Reset/Provision/GRANT อัตโนมัติ เมื่อ Disconnected จะไม่แสดงรายการฐานเก่าหรืออนุญาต Export ให้ไป Settings แก้ไข

Settings มี Test Connection (ไม่บันทึก) และ Save Settings (ทดสอบก่อนบันทึก); ไม่มีโปรไฟล์ + Password ว่างหมายถึงตั้งใจใช้รหัสว่าง หากมีโปรไฟล์แล้วเว้นว่างจะรักษารหัสเดิม เลือก Replace/Clear เพื่อแทนด้วยรหัสว่างได้อย่างชัดเจน ไม่เปลี่ยนรหัสบน MySQL ยืนยัน checkbox ก่อนเปลี่ยน endpoint/server UUID/account รองรับเฉพาะ loopback ไม่รองรับ remote หรือ port3333; active roles ยังไม่รองรับ

รหัสเก็บเป็น **plaintext ที่ป้องกันด้วย NTFS ACL ไม่ใช่ encryption** ใน `%LOCALAPPDATA%/BackupPhpMyAdmin/ConnectionSettings/<project-hash>/settings.json` นอก source/web root เฉพาะ Windows runtime SID และ SYSTEM ตรวจ owner/ACL/reparse และเขียน atomic ไม่แก้ config/local.php ไม่ใส่รหัสใน Session/HTML/API/jobs/manifests CLI กับ Apache คนละ Windows identity จะไม่แชร์รหัสโดยอัตโนมัติ

การ Save ถูกปฏิเสธขณะ shared operation lock ถูกใช้งาน; เปลี่ยนค่าทำให้ queued context เก่าใช้ต่อไม่ได้ และ executor ตรวจ revision ใต้ lock ก่อนเชื่อมจริง Status polling/History/static assets ไม่เชื่อม MySQL ผู้ใช้ HTTP ต้องมาจาก loopback และ Host เป็น localhost/127.0.0.1/[::1] เท่านั้น

## Import

1. เลือก trusted Backup จากหน้า Import หรือ History ตรวจชื่อ Source/SQL/ขนาด/SHA-256 แล้วเตรียมงาน
2. ระบบสร้างชื่อ Target ใหม่ให้ Browser เปลี่ยนเป็นฐานเดิมไม่ได้ ใช้โปรไฟล์ Settings เดียวกับ Export
3. ตรวจ Target/Source/Hash แล้วพิมพ์ `IMPORT` ก่อนยืนยัน จะตรวจ server/account, สิทธิ์ SELECT/INSERT/CREATE/REFERENCES และชื่อฐานต้องยังไม่มีอยู่
4. สร้างเฉพาะฐานใหม่แบบไม่มี IF NOT EXISTS บันทึก Ownership แล้วใช้ mysql.exe หนึ่งครั้ง ไม่สร้างบัญชี/GRANT งานเก่าเก็บเป็นประวัติ ไม่เริ่มด้วยโปรไฟล์ใหม่
5. ตรวจผลแต่ละด้าน หากขัดจังหวะ/failed ให้เก็บฐานเดิมไว้ตรวจ ไม่ Retry/Overwrite/Drop อัตโนมัติ

ไม่รับ arbitrary SQL upload, old custom dumps หรือ SQL ที่แก้ชื่อฐานด้วย string replacement
CLI provisioning เดิมเก็บไว้สำหรับงานประวัติเท่านั้น ไม่ใช่ prerequisite ของเว็บปัจจุบัน และห้ามรันโดยไม่มี authorization เฉพาะ

## Security และข้อจำกัด

Direct loopback peer เท่านั้น (`127.0.0.1`/`::1`); LAN ถูกปฏิเสธแม้ปลอม forwarding headers รักษา Session/CSRF/job ownership/Download token
**ห้ามเข้าถึง jhcisdb หรือต้นทางนอก scope ที่ยืนยัน** ห้ามแก้ต้นทาง; บัญชีสิทธิ์กว้างไม่ข้าม discovery/selection/target/provenance/identity/confirmation gates การใช้บัญชีจำกัดสิทธิ์ยังลดผลกระทบจาก runtime compromise ได้ดีกว่า root

Session files และ private temporary option files ไม่ใช่ encrypted vault ต้องป้องกันด้วย Windows permissions; ผู้ยึด runtime account/admin สามารถข้าม local trust assumptions ได้ Crash อาจทิ้ง private remnants; อย่า Cleanup ด้วย Prefix/Wildcard
Single-transaction/pre-post inventory ไม่ป้องกัน concurrent DDL/writes ทั้งหมด และ SELECT-only ไม่พิสูจน์ว่าไม่มี objects ที่บัญชีมองไม่เห็น

**Overall Import Acceptance: PARTIAL — SQL Snapshot-value Fidelity NOT VERIFIED.**
mysql.exe exit0 ไม่เท่ากับ full verification; normal runner ตรวจ effective schema/row counts ส่วนหลักฐานแยกเคยเทียบทุกค่ากับ Live Source ณ เวลาตรวจแล้ว ไม่ใช่ค่าจาก historical SQL Snapshot ประวัติ failed เดิมไม่ถูกแก้เป็น succeeded
ดู [Acceptance evidence](docs/LOCAL_IMPORT_ACCEPTANCE_2026-10-19.md)

## Development / change-based verification

อ่าน AGENTS.md, ARCHITECTURE.md และ TEST_BASELINE ก่อนแก้ ใช้ PHP/Node ของเครื่อง ไม่ติดตั้ง Framework เพิ่ม เลือกเฉพาะชุดที่กระทบ เช่น:

```powershell
php -l public/import-job.php
php tests/run.php ImportSchemaComparisonTest
php tests/run.php LocalManagerPresentationTest
node tests/js/local-import-presentation.test.js
git diff --check
```

ตัวอย่างไม่ใช่คำสั่งให้รันทุกชุดทุกครั้ง; bare `php tests/run.php` จะเลือกทั้งหมด ไม่ใช้เป็นค่าเริ่มต้น
`CleanupDependencyTest` ตรวจ autoload/isolated route smoke โดยไม่ใช้ MySQL/storage จริง; ตรวจ side effects ของ test ก่อนเลือกชุด process/NTFS

หลักฐานเดิมที่ไม่ต้องรันซ้ำหาก unchanged: Export4/4, focused39/39, mysql.exe exit0, effective schema9/9, live-source values9/9, finalization12/12, cleanup50PASS/1SKIP ไม่มี Live ใหม่ในงานเอกสาร/Git นี้

## Repository hygiene

Current tree ไม่เก็บ SQL/Manifest Backup, Jobs/History/Sessions/Logs, local config/secrets, private keys หรือ binaries/build artifacts; `storage/.htaccess` เป็นไฟล์ป้องกันที่เก็บไว้
Tracked runtime เดิมนำออกจาก index เท่านั้น ไฟล์ในเครื่องคงเดิม **Git history เก่ายังอาจมี SQL/artifacts** การ untrack ไม่ใช่การล้างประวัติ; ต้องประเมิน exposure ของข้อมูลเก่าแยกต่างหาก ไม่ rewrite/force push อัตโนมัติ

[Cleanup inventory](docs/PROJECT_CLEANUP.md) อธิบายสิ่งที่ลบ/เก็บ รวม History/provenance utilities สองตัวที่ยังใช้จริง ห้ามนำ Custom Restore/DPAPI/Dedicated Deployment กลับมาเป็น prerequisite
