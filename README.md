# PHP MySQL Backup Manager

เว็บสำหรับเชื่อมต่อ MySQL/MariaDB จาก host หรือ IP ที่กำหนด เลือกฐานข้อมูลที่ต้องการ และสำรองเป็นไฟล์ `.sql` แยกตามฐานข้อมูล

## โครงสร้าง

- `public/` หน้าเว็บและ assets ที่เปิดผ่าน browser
- `app/` โค้ด PHP หลัก แยก service และ helper
- `config/` ตั้งค่าระบบ เช่น ตำแหน่งจัดเก็บ backup
- `storage/backups/` ไฟล์ฐานข้อมูลที่สำรองแล้ว
- `storage/jobs/` สถานะงานสำรองและ lock ภายในระบบ (private, Git ignored)

## การใช้งาน

เปิดผ่าน Laragon หรือ PHP built-in server โดยชี้ document root ไปที่ `public/`

```powershell
php -S 127.0.0.1:8080 -t public
```

จากนั้นเข้า `http://127.0.0.1:8080`

## Phase 1: การเข้าถึงอย่างปลอดภัย

หากยังไม่มี `config/local.php` ระบบจะอนุญาตเฉพาะการเชื่อมต่อโดยตรงจาก `127.0.0.1` หรือ `::1` เท่านั้น การส่ง `X-Forwarded-For` ไม่ทำให้ request จาก LAN หรือ Remote กลายเป็น localhost

เมื่อต้องการเปิดใช้งาน authentication:

1. สร้างรหัสผ่านและ hash แบบสุ่มด้วย Laragon PHP

   ```powershell
   C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tools\generate-admin-credentials.php
   ```

2. คัดลอก `config/local.php.example` เป็น `config/local.php`
3. ใส่ hash ที่สร้างได้ใน `auth.password_hash` และเก็บรหัสผ่านที่แสดงครั้งเดียวไว้ใน password manager
4. ทดสอบ login ผ่าน loopback ก่อนเปิด firewall หรือ virtual host ให้เครื่องอื่นเข้าถึง

`config/local.php` ถูก Git ignore และต้องไม่ commit. ทุก route รวมถึง download จะต้องผ่าน access policy เดียวกัน เมื่อกำหนด password hash แล้ว แม้แต่ loopback ก็ต้อง login

Reverse proxy มีข้อจำกัดโดยตั้งใจ: ระบบไม่เชื่อถือ `X-Forwarded-For` หรือ `X-Forwarded-Proto` เพื่อยืนยัน localhost/HTTPS ผู้ดูแลต้อง terminate HTTPS และกำหนด access control ที่ web server อย่างชัดเจน ห้ามเปิดแอปนี้สู่ Internet โดยตรง

## Navigation

- Dashboard — ภาพรวม
- Backup — เชื่อมต่อและสำรองแบบ Manual ทีละฐาน
- Restore — ตรวจ provenance, integrity, SQL structure และ safety gates แบบ preflight เท่านั้น
- History — รายการ, สถานะ origin แบบเบา และ secure download
- Settings — เลือก backup directory ภายใต้ allowlist

ทุกหน้าผ่าน `public/index.php?page=<route>` และใช้ shared layout เดียวกัน

## Phase 4: Modern UX/UI

Shared layout รองรับ desktop, tablet และ mobile พร้อม skip link, active navigation, เมนูมือถือที่เปิด/ปิดด้วย keyboard, visible focus และ reduced-motion preference โดยใช้ CSS/JavaScript ภายในโปรเจกต์และไม่เพิ่ม framework

- Dashboard แสดงเฉพาะ connection status, จำนวนฐานที่ session มองเห็น, จำนวน/เวลาของไฟล์สำรอง, งานสำรองล่าสุดใน session และ Restore safety gate จริง
- Backup เพิ่มค้นหา, เลือกรายการที่มองเห็น, ล้างรายการ, จำนวนที่เลือก และ confirmation dialog ก่อนส่งคำขอสร้าง job; executor และ concurrency ยังคงเป็น Phase 2 เดิม
- Restore นำสถานะ fail-closed ขึ้นก่อนฟอร์ม แสดง safety gates และ preflight history แต่ยังไม่มีระบบประมวลผล SQL หรือปุ่ม restore
- History เพิ่มค้นหา/filter และแยก integrity, provenance, eligibility ชัดเจน โดยยังไม่ hash ไฟล์ SQL ตอนเปิดหน้า
- Settings แสดง connection summary โดยไม่แสดง password, storage allowlist, security/access และ runtime environment

สถานะงานทั้งหมดมาจาก persisted job state ไม่มีเปอร์เซ็นต์, success rate หรือเวลาเสร็จโดยประมาณที่ระบบพิสูจน์ไม่ได้ การ refresh จะอ่าน active job เดิมและไม่สร้าง job ใหม่
ขณะ Restore preflight อยู่ใน `queued` หรือ `running` หน้าเว็บจะ poll เฉพาะ status จนถึง `blocked`, `failed` หรือ `interrupted`; การ poll ไม่เรียก create/preflight และไม่เริ่ม SQL execution

## Phase 3: Restore Preflight แบบ Fail-Closed

Phase 3 ไม่มี SQL executor และไม่เชื่อมต่อฐานข้อมูลเพื่อ Restore หน้า Restore ทำได้เฉพาะสร้างงานตรวจสอบไฟล์ที่มี Phase 2 job provenance, ตรวจ SHA-256/ขนาด/completion marker/manifest จากไฟล์จริง, ตรวจ SQL structure แบบ conservative และตรวจชื่อฐานทดลองปลายทาง งานที่ผ่านการตรวจไฟล์จะจบเป็น `blocked` เพราะยังไม่มีหลักฐาน isolated disposable instance, dedicated credentials, target-scoped privileges และ live integration test ไม่มี configuration flag ที่เปิด execution ได้

Backup รุ่นเก่าหรือไฟล์ที่ไม่มี Phase 2 provenance จะแสดง `unverified_origin` และไม่ eligible ระบบไม่สร้าง provenance ย้อนหลัง หน้า History อ่านเฉพาะ metadata/manifest/provenance เพื่อไม่ hash ไฟล์ขนาดใหญ่ทุกครั้ง และระบุชัดว่ายังไม่ได้ตรวจ SHA-256; full preflight เท่านั้นที่ตรวจเนื้อหาไฟล์จริง

## Phase 2: Manual Backup Jobs

หน้า Backup รองรับการเลือกหนึ่งฐาน หลายฐาน หรือทั้งหมดจากรายการที่ connection ปัจจุบันอนุญาต งานจะถูกเก็บเป็นสถานะ file-backed ภายใต้ `storage/jobs/` และประมวลผลทีละฐานเท่านั้น โดย per-job lock ป้องกันการเริ่ม job เดิมซ้ำ และ global lock ป้องกันงานสำรองหลาย job ทำงานพร้อมกัน

สถานะที่แสดงคือ `queued`, `running`, `succeeded`, `failed` และ `interrupted` จากข้อมูลที่บันทึกจริง ไม่มีเปอร์เซ็นต์หรือเวลาเสร็จโดยประมาณ หากฐานหนึ่งล้มเหลว ระบบบันทึก error แบบปลอดภัยแล้วทำฐานถัดไปต่อ

การ refresh หรือปิด browser จะไม่สร้าง job ใหม่ หน้าเดิมสามารถกลับมาอ่านสถานะผ่าน session ได้ หาก HTTP process ยังทำงาน งานอาจดำเนินต่อ แต่การตั้งค่า `ignore_user_abort` และ time limit ไม่รับประกันว่าจะรอดจาก Apache/FastCGI/Windows restart เมื่อพบ claimed job ที่ไม่มี process ถือ lock แล้ว ระบบจะแสดง `interrupted` และไม่ลองใหม่อัตโนมัติ

ไฟล์ job ไม่มี database password, session token หรือ authentication credential ข้อมูลเชื่อมต่อถูกใช้จาก server session ใน memory ของ request เท่านั้น Job ผูกกับ connection context ที่เก็บเฉพาะใน session; หาก reconnect หลังสร้าง queued job งานเดิมจะถูก interrupted และต้องสร้างงานใหม่ เพื่อไม่ให้รายชื่อฐานเดิมไปสำรองจาก server อื่นโดยไม่ตั้งใจ Job API ทุก action ต้องผ่าน authentication; create/execute และ status ต้องผ่าน CSRF/ownership ตามนโยบาย Phase 1

## ตั้งค่าตำแหน่งเก็บไฟล์สำรอง

ค่าเริ่มต้นแก้ไขได้ที่ไฟล์ `config/app.php`

```php
'backup_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups',
```

ผู้ใช้สามารถเปลี่ยนตำแหน่งจัดเก็บจากหน้าเว็บได้ในแถบ “ตำแหน่งจัดเก็บไฟล์สำรอง”

ตั้งแต่ Phase 1 ตำแหน่งดังกล่าวต้องอยู่ภายใต้ `allowed_backup_roots` เท่านั้น หากต้องการใช้ drive หรือ directory ภายนอก ให้เพิ่ม absolute path ใน `config/local.php` ก่อน แล้วจึงเลือก path ย่อยผ่านหน้า Settings ตัวอย่างอยู่ใน `config/local.php.example`

ไฟล์สำรองจะแยกโฟลเดอร์ตามชื่อฐานข้อมูล เช่น

```text
storage/backups/
  database_a/
    2026-07-17_17-30-00_database_a.sql
    2026-07-17_17-30-00_database_a.sql.json
  database_b/
    2026-07-17_17-31-05_database_b.sql
    2026-07-17_17-31-05_database_b.sql.json
```

แนะนำให้เก็บนอก public web root และจำกัดสิทธิ์เข้าถึงเฉพาะผู้ดูแลระบบ

ควรกำหนด document root ของ Laragon เป็น `public/`. ไฟล์ `.htaccess` ที่ project root และ `storage/.htaccess` เป็น defense-in-depth สำหรับ Apache แต่ไม่ทดแทน document-root configuration โดยเฉพาะเมื่อใช้ Nginx หรือ reverse proxy

## Log และข้อความผิดพลาด

ผู้ใช้จะเห็นเฉพาะข้อความทั่วไป รายละเอียดทางเทคนิคบันทึกที่ `storage/logs/app.log` และรหัสผ่านฐานข้อมูลที่อยู่ใน session จะถูก redact ก่อนเขียน log ห้ามนำ log ไปไว้ใต้ public web root หรือเผยแพร่ผ่าน Git

## ความครบถ้วนของไฟล์สำรอง

ระบบเขียนไฟล์แบบปลอดภัยขึ้นโดยสร้าง SQL และ manifest เป็นไฟล์ `.tmp` ตรวจ completion marker, SHA-256, ขนาด และ manifest ก่อน publish จากนั้นวาง manifest ก่อนและวาง `.sql` เป็นขั้นสุดท้าย หาก publish ล้มเหลวจะ rollback เฉพาะ artifact ใหม่ของ attempt นั้น ไม่เขียนทับหรือลบ backup เดิม ถ้างานถูกตัดกลางทาง ไฟล์ SQL ที่ไม่สมบูรณ์จะไม่ถูกนำไปใช้เป็น backup หลัก

ลำดับข้อมูลในไฟล์ `.sql` ถูกจัดให้เหมาะกับการ restore และรองรับการรันแยกเป็นช่วง ๆ มากขึ้น:

1. ตั้งค่า session และเลือกฐานข้อมูล
2. ลบ view เดิมก่อน เพื่อไม่ชนกับชื่อ table
3. สร้าง table โดยตัด foreign key ออกจาก `CREATE TABLE` ก่อน
4. ใส่ข้อมูลตามลำดับความสัมพันธ์ของ foreign key เท่าที่ตรวจได้จาก `information_schema`
5. สำหรับ table ที่เป็น MyISAM จะมี `LOCK TABLES`, `ALTER TABLE ... DISABLE KEYS`, `ENABLE KEYS` และ `UNLOCK TABLES` ครอบช่วง insert แบบเดียวกับแนวทางของ `mysqldump`
6. คืนค่า `AUTO_INCREMENT` ด้วย `ALTER TABLE ... AUTO_INCREMENT = ...` หลังใส่ข้อมูลครบ เพื่อรักษาเลขถัดไปแม้ table ว่าง
7. เพิ่ม foreign key กลับด้วย `ALTER TABLE ... ADD CONSTRAINT` หลัง table ครบแล้ว
8. สร้าง trigger
9. สร้าง view placeholder สำหรับรองรับ view ที่อ้างถึงกัน
10. เขียน view จริงทับ placeholder
11. สร้าง procedure และ function
12. สร้าง event
13. คืนค่า session และเขียน marker ว่า backup เสร็จสมบูรณ์

ไฟล์ที่สำเร็จจะมีบรรทัดท้าย:

```sql
-- Backup completed successfully
```

และมีไฟล์ manifest คู่กันเป็น `.sql.json` ภายในมีขนาดไฟล์, SHA-256, จำนวน table/view/trigger/routine/event/foreign key, จำนวน table ที่คืนค่า `AUTO_INCREMENT`, จำนวน MyISAM lock section และจำนวน row ที่ dump ได้

กรณีฐานข้อมูลมี foreign key วนกันเป็นวงจร ระบบจะยังสร้าง table ได้เพราะ foreign key ถูกแยกออกจาก `CREATE TABLE` แล้ว ส่วนการเติม constraint กลับจะอยู่ท้ายช่วง table/data และยังใช้ session restore ที่ปิด `FOREIGN_KEY_CHECKS` ระหว่าง import เพื่อช่วยกับข้อมูลเดิมที่มี dependency ซับซ้อน

ระบบจะตัด `DEFINER` ออกจาก view, trigger, procedure, function และ event เพื่อลดปัญหา restore ข้ามเครื่องที่ไม่มี user/host เดิม แต่ยังควรทดสอบ restore บน MySQL/MariaDB เวอร์ชันเป้าหมายเสมอ โดยเฉพาะ routine/event ที่ใช้ syntax เฉพาะเวอร์ชัน

## การตรวจสอบก่อน restore

ก่อนนำไฟล์ไป restore ควรตรวจสองจุด:

1. ไฟล์ `.sql` ต้องมีบรรทัด `-- Backup completed successfully` ที่ท้ายไฟล์
2. ค่า SHA-256 ของไฟล์ `.sql` ต้องตรงกับค่า `sha256` ในไฟล์ `.sql.json`

บน Windows PowerShell ตรวจ checksum ได้ด้วย:

```powershell
Get-FileHash .\backup.sql -Algorithm SHA256
```

Phase 3 ไม่ให้คำสั่งหรือช่องทาง execute restore หลังตรวจเสร็จ การทดสอบ restore จริงต้องรอ instance ทดลองที่แยกขาด, dedicated restore user ที่จำกัดสิทธิ์เฉพาะฐานทดลอง และ integration test ที่ได้รับอนุมัติก่อน
