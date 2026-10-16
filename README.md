# PHP MySQL Backup Manager

เว็บสำหรับเชื่อมต่อ MySQL/MariaDB จาก host หรือ IP ที่กำหนด เลือกฐานข้อมูลที่ต้องการ และสำรองเป็นไฟล์ `.sql` แยกตามฐานข้อมูล

## โครงสร้าง

- `public/` หน้าเว็บและ assets ที่เปิดผ่าน browser
- `app/` โค้ด PHP หลัก แยก service และ helper
- `config/` ตั้งค่าระบบ เช่น ตำแหน่งจัดเก็บ backup
- `storage/backups/` ไฟล์ฐานข้อมูลที่สำรองแล้ว

## การใช้งาน

เปิดผ่าน Laragon หรือ PHP built-in server โดยชี้ document root ไปที่ `public/`

```powershell
php -S 127.0.0.1:8080 -t public
```

จากนั้นเข้า `http://127.0.0.1:8080`

## ตั้งค่าตำแหน่งเก็บไฟล์สำรอง

ค่าเริ่มต้นแก้ไขได้ที่ไฟล์ `config/app.php`

```php
'backup_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups',
```

ผู้ใช้สามารถเปลี่ยนตำแหน่งจัดเก็บจากหน้าเว็บได้ในแถบ “ตำแหน่งจัดเก็บไฟล์สำรอง”

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

## ความครบถ้วนของไฟล์สำรอง

ระบบเขียนไฟล์แบบปลอดภัยขึ้นโดยสร้างเป็นไฟล์ `.tmp` ก่อน แล้วค่อยเปลี่ยนชื่อเป็น `.sql` เมื่อ dump สำเร็จครบเท่านั้น ถ้างานถูกตัดกลางทาง ไฟล์ที่ไม่สมบูรณ์จะไม่ถูกนำไปใช้เป็น backup หลัก

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

จากนั้น restore ได้ด้วยคำสั่งมาตรฐานของ MySQL/MariaDB:

```powershell
mysql -u root -p < .\backup.sql
```
