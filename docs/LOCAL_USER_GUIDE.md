# คู่มือใช้งาน Local MySQL Export / Import v1.0

สำหรับผู้พัฒนา: [Architecture](ARCHITECTURE.md) และ [AGENTS.md](../AGENTS.md) อธิบายโค้ดและขอบเขตความปลอดภัยปัจจุบัน

เปิด `http://localhost:8081/backUP-phpmyadmin-fordev/` ผ่าน Laragon เดิม ใช้ได้เฉพาะเครื่องนี้ ไม่มีรหัสผ่านเว็บไซต์ รหัส MySQL เป็นอีกเรื่องหนึ่ง ไม่ต้องติดตั้ง Service, DPAPI หรือ Code-signing เพิ่ม

## Export

1. เปิด Settings เลือกบัญชี MySQL ปกติ ค่าเริ่มต้น `127.0.0.1:3306`, `root`, รหัสว่าง กด Test Connection หรือ Save Settings อย่างชัดเจนก่อน ค่าเริ่มต้นที่ยังไม่ Save ไม่ถูกใช้ auto-connect ครั้งถัดไปเมื่อมีโปรไฟล์แล้วจะตรวจจริงอัตโนมัติแม้ Session ใหม่
2. เลือกหนึ่งฐาน หลายฐาน หรือทั้งหมดจากฐานที่บัญชีมองเห็นและ Export ได้ ไม่จำกัด 4 ฐานที่เคยทดสอบ Select All รวม eligible ทุกฐาน ไม่ใช่เฉพาะผลค้นหา System schemas แสดงแยกและไม่รวม Export/Import; jhcisdb ไม่แสดงและห้ามเข้าถึง ชื่อที่ยังเก็บไฟล์บน Windows ไม่ปลอดภัยแสดงแยก ไม่สร้างงานให้
3. ยืนยัน Export ระบบทำทีละฐาน และแสดงผลจริงของแต่ละฐาน
4. เปิด History แล้ว Download SQL ระบบตรวจไฟล์ก่อนส่ง ไม่เปิดไฟล์จาก storage ผ่าน URL โดยตรง

ครอบคลุม **InnoDB base-table structure และ rows ในรูปแบบ official mysqldump SQL** ไม่ใช่ Full database backup: ไม่รวม Views, Triggers, Stored procedures/functions, Events หรือ Non-InnoDB engines และไม่รับรองว่าการไม่มีสิทธิ์มองเห็นวัตถุหมายถึงวัตถุนั้นไม่มี หลีกเลี่ยง concurrent DDL และอย่าเปลี่ยนฐานต้นทางระหว่างงาน

ช่อง Password ไม่ใช่รหัสเว็บไซต์ ระบบไม่ Reset บัญชีให้เอง ไม่เติมรหัสเดิมลง HTML; โปรไฟล์แรกยอมรับรหัสว่าง เมื่อมีโปรไฟล์แล้วเว้นว่างเพื่อรักษารหัสเดิม เลือก Replace/Clear เพื่อเปลี่ยนเป็นรหัสว่างอย่างชัดเจน Test ไม่บันทึก; Save ตรวจจริงก่อนเขียน หากเปลี่ยน endpoint/UUID/account ต้องติ๊กยืนยัน identity change ใช้ได้เฉพาะ loopback และไม่ใช้ port3333 หาก Disconnected จะซ่อนฐานเก่าและปิด Export/Import

โปรไฟล์เก็บ plaintext ใน private LocalAppData ของ Windows account ที่รัน PHP นอก Web Root ด้วย ACL เฉพาะ SID นั้นและ SYSTEM ไม่ใช่ encrypted vault รหัสไม่อยู่ใน Session/Job/Manifest/Logs เปลี่ยน Windows account หรือย้าย project path จะไม่แชร์ค่าเดิม ห้าม Copy ไป source/config เมื่อเปลี่ยนค่า queued context เก่าใช้ต่อไม่ได้ ส่วนงานกำลังรันห้าม Save จนปล่อย shared lock Export/Import ใช้โปรไฟล์เดียวกัน ไม่มีช่องรหัส Import แยก

## Import

หน้า Import ตรวจการเชื่อมต่อจาก Settings จริงเมื่อเปิดหน้า หาก Disconnected จะปิดปุ่มเตรียมงาน แม้มีไฟล์ SQL อยู่ หาก Connected และมีไฟล์ที่รองรับ ปุ่มจะพร้อมใช้งาน แต่ **ไม่เริ่ม Import อัตโนมัติ** การเตรียมงานเพียงให้ตรวจ Source/Target ก่อนยืนยัน ไม่มีการสร้างฐานในขั้นนี้

1. เลือก Backup ที่ระบบรองรับจากหน้า Import หรือกด Import ใน History ตรวจ Source, ชื่อ SQL, ขนาด และ SHA-256
2. กดตรวจไฟล์และเตรียมงาน จะสร้างเฉพาะชื่อ Target ใหม่ ยังไม่สร้างฐานจริง และไม่สร้างบัญชี
3. ตรวจ Target/Source/Hash พิมพ์ `IMPORT` และยืนยัน ใช้บัญชีจาก Settings ต้องพิสูจน์สิทธิ์ SELECT/INSERT/CREATE/REFERENCES สำหรับ Target ได้ มิฉะนั้นหยุดโดยไม่เพิ่ม GRANT
4. ใต้ shared lock ระบบตรวจ server/account และชื่อฐานยังไม่มี แล้ว CREATE โดยไม่ใช้ IF NOT EXISTS บันทึก Ownership ก่อนส่ง SQL ให้ mysql.exe ระหว่างรออย่ากดซ้ำ งานเก่าเป็นประวัติ ไม่เปลี่ยนโปรไฟล์เพื่อเริ่มซ้ำ
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

- รหัส MySQL ไม่อยู่ใน Job JSON, Manifest หรือข้อความผิดพลาด Export/Import ใช้โปรไฟล์เดียวกัน บัญชีสิทธิ์กว้างไม่ข้าม protected/system/selection gates งานที่ไม่มีหลักฐาน selection เดิมหรือ job file ถูกเปลี่ยนชื่อจะไม่เริ่มต่อ
- ถ้าขาด Credential ให้ติดต่อผู้ดูแลผ่านช่องทางที่ปลอดภัย ไม่ส่งรหัสในแชต/Logs/Source และไม่อาศัย Clipboard เป็นที่เก็บรหัสถาวร
- ถ้า Session หมดอายุ ให้โหลดหน้าใหม่ งานและ Download ยังจำกัดตามสิทธิ์ Session ห้ามแก้ Session/Job เพื่อยึดงานเก่า
- ถ้าไฟล์/Hash/Provenance ไม่ตรง ให้หยุดและเลือกไฟล์ที่ถูกต้อง อย่าแก้ SQL เพื่อข้ามการตรวจ
- ถ้างานเริ่มแล้วล้มเหลวหรือขัดจังหวะ ให้ผู้ดูแลตรวจ Target เดิมและ Ownership ก่อน ไม่ Reset, Import ซ้ำ หรือ Cleanup เอง

## เส้นทางที่ใช้งานจริง

Navigation: Dashboard / Export / Import / History / Settings; ชื่อ route `backup` คงไว้เพื่อความเข้ากันได้ Settings เป็นจุดเดียวสำหรับ connection form พร้อม Storage path และข้อมูลป้องกัน/Runtime เข้าผ่าน localhost, 127.0.0.1 หรือ [::1] เท่านั้น ไม่รับ Host alias อื่น

Export ใช้ `job.php` → mysqldump; Import ใช้ `import-job.php` → mysql; Download ใช้ `download.php` ข้อมูล runtime อยู่ใน runtime_data_root เดิม: backups, jobs, restore-jobs/local-import, restore-integration-ownership/local-import และ database-operations/global.lock

Restore/Restore Lab และ API เก่าถูกลบแล้ว ใช้ Import เท่านั้น Custom Restore/DPAPI/.NET/Signing/Dedicated Apache source และ tooling ที่เลิกใช้ถูก Cleanup; หลักฐานเดิมใน TEST_BASELINE และรายงาน Import ยังเก็บไว้ ไม่มีการลบ SQL/Manifest/History หรือสร้างแพ็กเกจใหม่ ดู PROJECT_CLEANUP.md สำหรับรายการไฟล์

สถานะการยืนยันรวมยัง **PARTIAL** จนกว่าจะพิสูจน์ Snapshot-value Fidelity ได้ ดู TEST_BASELINE.md และ LOCAL_IMPORT_ACCEPTANCE_2026-10-19.md สำหรับหลักฐานจริง ขอบเขตนี้ไม่รวมการเข้าถึงฐานอื่นหรือการ Restore ทับฐานเดิม

v1.0 รับรองขอบเขตเครื่อง Laragon ที่มีหลักฐานเท่านั้น (Apache2.4.66/PHP8.3.30/MySQL8.4.3) ไม่ใช่ Git Release หรือรองรับ MariaDB/ทุกเครื่อง ฐานว่างจาก test incident เก่าที่ระบุตัวไม่ได้ยัง OWNERSHIP UNPROVEN ห้ามเดาจากชื่อ/Prefix แล้วลบหรือ Rename
