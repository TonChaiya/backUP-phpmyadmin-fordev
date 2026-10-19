# คู่มือใช้งาน Local MySQL Export / Import

สำหรับผู้พัฒนา: [Architecture](ARCHITECTURE.md) และ [AGENTS.md](../AGENTS.md) อธิบายโค้ดและขอบเขตความปลอดภัยปัจจุบัน

เปิด `http://localhost:8081/backUP-phpmyadmin-fordev/` ผ่าน Laragon เดิม ใช้ได้เฉพาะเครื่องนี้ ไม่มีรหัสผ่านเว็บไซต์ รหัส MySQL เป็นอีกเรื่องหนึ่ง ไม่ต้องติดตั้ง Service, DPAPI หรือ Code-signing เพิ่ม

## Export

1. เปิด Export แล้วเชื่อมต่อ `backup_reader` ที่ `127.0.0.1:3306` ด้วยรหัสของบัญชีนี้ หาก Session เดิมยังเชื่อมต่ออยู่ ใช้รายการฐานที่แสดงได้เลย ไม่ต้องเชื่อมต่อใหม่โดยไม่จำเป็น
2. เลือกหนึ่งฐาน หลายฐาน หรือทุกฐานที่อนุญาต: autoprintdb, drugmap_db, drug_stock_check, prepack_labels
3. ยืนยัน Export ระบบทำทีละฐาน และแสดงผลจริงของแต่ละฐาน
4. เปิด History แล้ว Download SQL ระบบตรวจไฟล์ก่อนส่ง ไม่เปิดไฟล์จาก storage ผ่าน URL โดยตรง

ครอบคลุม **InnoDB base-table structure และ rows ในรูปแบบ official mysqldump SQL** ไม่ใช่ Full database backup: ไม่รวม Views, Triggers, Stored procedures/functions, Events หรือ Non-InnoDB engines และไม่รับรองว่าการไม่มีสิทธิ์มองเห็นวัตถุหมายถึงวัตถุนั้นไม่มี หลีกเลี่ยง concurrent DDL และอย่าเปลี่ยนฐานต้นทางระหว่างงาน

ช่อง Password ไม่ใช่รหัสเว็บไซต์ หากไม่มีรหัส backup_reader ให้ติดต่อผู้ดูแล ไม่ใช้ root แทน ระบบไม่ Reset บัญชีให้เอง การเชื่อมต่อใหม่ต้องกรอกรหัสที่ถูกต้อง; คำว่าเว้นว่างหมายถึงไม่ต้องส่งฟอร์มเชื่อมต่อใหม่เมื่อยังใช้ Session เดิม ไม่ใช่การกด Connect ด้วยรหัสว่าง

## Import

1. เลือก Backup ที่ระบบรองรับจากหน้า Import หรือกด Import ใน History ตรวจ Source, ชื่อ SQL, ขนาด และ SHA-256
2. กดตรวจไฟล์และเตรียมงาน ชื่อ Target และบัญชี Import ถูกสร้างโดยระบบ ต้องเป็นฐานใหม่เท่านั้น ไม่สามารถกรอกชื่อฐานเดิมเพื่อเขียนทับ
3. หากยังรอ Provisioning ให้ผู้ดูแลตรวจและเตรียมเฉพาะ Target/บัญชีตามชื่อที่แสดง โดยต้องอนุมัติแยกก่อนสร้างฐาน/บัญชี/GRANT ห้ามสร้างงานซ้ำเพื่อแก้การไม่มี Credential เว็บไม่ Provision ให้อัตโนมัติ
4. เมื่อ Ownership/บัญชีพร้อมและเชื่อม backup_reader แล้ว ให้กรอกรหัส Import เฉพาะงาน พิมพ์ `IMPORT` และยืนยัน ระหว่างรออย่ากดซ้ำ ระบบใช้ mysql.exe และล็อกร่วมกับ Export
5. ดูผลแต่ละด้านด้านล่าง ไม่มี Retry หรือลบฐานทดลองอัตโนมัติ หากงานล้มเหลวให้เก็บไว้ตรวจ ห้าม Import ซ้ำลงฐานที่มีข้อมูลแล้ว

รับเฉพาะไฟล์จาก trusted mysqldump profile ของแอปที่มี Manifest/Job Provenance และผ่านตรวจขนาด/Hash ไม่รับ Upload SQL ทั่วไปหรือไฟล์เก่าที่พิสูจน์รูปแบบไม่ได้ ไม่แก้ชื่อฐานใน SQL ด้วย String Replacement

## อ่านผล Import ให้ถูกต้อง

| ผลบนหน้า | ความหมาย |
|---|---|
| Import execution | ตัว Client ทำงานเสร็จหรือยัง ไม่ได้แปลว่าค่าข้อมูลครบถ้วนแล้ว |
| Schema verified | โครงสร้างที่ตรวจผ่าน อาจมาจากผลตรวจแยกที่ระบุเวลา |
| Data / Live Source | ตรวจค่าทุกค่ากับต้นทาง ณ เวลาที่ระบุ ไม่ใช่ข้อมูลย้อนหลัง ณ เวลา Backup |
| Data / SQL Snapshot | ปัจจุบัน **NOT VERIFIED** เพราะยังไม่มี Supported Independent Snapshot-value Comparison |
| Verification | Pending / Failed / Partial แยกจากสถานะงานที่บันทึกไว้ |

ไม่มีการแสดง Fully verified ด้วย Row Count หรือ Live Source PASS เพียงอย่างเดียว ประวัติงานเดิม failed จะยังเป็น failed แม้มีผลตรวจแยกที่ผ่านบางด้าน วันเวลาที่แสดงคือเวลาตรวจเดิม ไม่ใช่การ Query ฐานซ้ำเมื่อเปิดหน้า History/Import

## Credential และข้อผิดพลาด

- รหัส MySQL ไม่อยู่ใน Job JSON, Manifest หรือข้อความผิดพลาด บัญชี Export และ Import ต้องแยกกัน
- ถ้าขาด Credential ให้ติดต่อผู้ดูแลผ่านช่องทางที่ปลอดภัย ไม่ส่งรหัสในแชต/Logs/Source และไม่อาศัย Clipboard เป็นที่เก็บรหัสถาวร
- ถ้า Session หมดอายุ ให้โหลดหน้าใหม่ งานและ Download ยังจำกัดตามสิทธิ์ Session ห้ามแก้ Session/Job เพื่อยึดงานเก่า
- ถ้าไฟล์/Hash/Provenance ไม่ตรง ให้หยุดและเลือกไฟล์ที่ถูกต้อง อย่าแก้ SQL เพื่อข้ามการตรวจ
- ถ้างานเริ่มแล้วล้มเหลวหรือขัดจังหวะ ให้ผู้ดูแลตรวจ Target เดิมและ Ownership ก่อน ไม่ Reset, Import ซ้ำ หรือ Cleanup เอง

## เส้นทางที่ใช้งานจริง

Navigation: Dashboard / Export / Import / History / Settings; ชื่อ route `backup` คงไว้เพื่อความเข้ากันได้ Settings มี Connection summary, Storage path และข้อมูลป้องกัน/Runtime เท่านั้น

Export ใช้ `job.php` → mysqldump; Import ใช้ `import-job.php` → mysql; Download ใช้ `download.php` ข้อมูล runtime อยู่ใน runtime_data_root เดิม: backups, jobs, restore-jobs/local-import, restore-integration-ownership/local-import และ database-operations/global.lock

Restore/Restore Lab และ API เก่าถูกลบแล้ว ใช้ Import เท่านั้น Custom Restore/DPAPI/.NET/Signing/Dedicated Apache source และ tooling ที่เลิกใช้ถูก Cleanup; หลักฐานเดิมใน TEST_BASELINE และรายงาน Import ยังเก็บไว้ ไม่มีการลบ SQL/Manifest/History หรือสร้างแพ็กเกจใหม่ ดู PROJECT_CLEANUP.md สำหรับรายการไฟล์

สถานะการยืนยันรวมยัง **PARTIAL** จนกว่าจะพิสูจน์ Snapshot-value Fidelity ได้ ดู TEST_BASELINE.md และ LOCAL_IMPORT_ACCEPTANCE_2026-10-19.md สำหรับหลักฐานจริง ขอบเขตนี้ไม่รวมการเข้าถึงฐานอื่นหรือการ Restore ทับฐานเดิม
