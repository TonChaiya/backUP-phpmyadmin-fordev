# Phase 1 Audit and Security Record

วันที่: 2026-10-16

## ขอบเขตที่ตรวจ

ตรวจ execution flow, database connection, SQL dump service, storage/download, session/authentication, CSRF, error handling, UI organization, Git tracking และ PHP/Laragon compatibility โดยไม่เปิดอ่านเนื้อหา SQL backup และไม่เชื่อมต่อฐานข้อมูลจริง

## Architecture เดิม

- `public/index.php` เป็น monolithic request handler และ template
- `ConnectionFactory` สร้าง `mysqli` connection ตาม host/port/user/password ที่ผู้ใช้กรอก
- `DatabaseRepository` ใช้ `SHOW DATABASES` และตัด system schemas
- `BackupService` dump schema/data/view/trigger/routine/event ลง temporary file ก่อน rename พร้อม completion marker และ manifest
- การเลือกหลายฐานทำงานด้วย synchronous `foreach` ทีละฐานอยู่แล้ว
- Storage อยู่ใต้ `storage/backups` โดยค่าเริ่มต้น และเปลี่ยน path ผ่าน session ได้
- Download เดิมรับ relative path จาก query string โดยไม่มี authentication

## Confirmed Issues

| Severity | Issue | Exposure before Phase 1 | Phase 1 disposition |
|---|---|---|---|
| Critical | ไม่มี authentication/access gate | LAN user ที่เข้าถึงเว็บสามารถสั่ง connect, backup, เปลี่ยน path และ download | เพิ่ม configured authentication และ unconfigured direct-loopback-only policy |
| Critical | Backup root จาก session ไม่ถูกจำกัด | ผู้ใช้ที่เข้าถึงเว็บสามารถชี้ root ไป directory อื่น และ download endpoint รับไฟล์ใดก็ได้ใต้ root | เพิ่ม absolute canonical allowlist, containment และ inventory-based SQL authorization |
| High | DB password ถูก render กลับใน password/hidden inputs | รหัสผ่านปรากฏใน HTML/DOM | เก็บเฉพาะ server-side session และไม่ render value/hidden field |
| High | Download ไม่มี authorization | URL โดยตรงใช้ได้โดยไม่ผ่าน access policy | บังคับ auth, HMAC token, inventory membership, completion/checksum validation |
| High | Session ใช้ค่า default | fixation/cookie policy ไม่ชัดเจน | strict mode, cookies-only, HttpOnly, SameSite=Strict, conditional Secure, regenerate login, invalidate logout |
| Medium | Raw exception message แสดงต่อ browser | driver/path/server details รั่ว | generic user messages และ private redacted log |
| Medium | Request/controller/view ผูกกันในไฟล์เดียว | เปลี่ยน UI/security เสี่ยงกระทบ backup flow | แยก front controller, controllers, router, views และ shared layout |
| Medium | Internal folders พึ่ง Apache rule บางส่วน | document root ผิดอาจเปิด internal files | ขยาย root `.htaccess`; ยังคงแนะนำ document root เป็น `public/` |
| High | SQL backup ถูก track ใน Git 8 ไฟล์ | เนื้อหาฐานข้อมูลอยู่ใน current history/remote history | ไม่อ่าน/ลบ/แก้ history; ignore backup ใหม่และส่งต่อ remediation แบบควบคุม |

## Existing Backup Engine

Phase 1 ไม่ rewrite `app/Backup/BackupService.php`. SQL strings ที่มี `DROP`, `CREATE`, `ALTER` และ `INSERT` เป็นข้อความที่เขียนลง dump file ไม่ได้ execute ต่อฐานข้อมูลต้นทางจาก Phase 1 code path. ยังไม่ได้พิสูจน์ compatibility กับ MySQL/MariaDB ทุกเวอร์ชันเพราะห้ามใช้ฐานจริงในการทดสอบ

## Git Exposure and Remediation Proposal

ตรวจด้วย `git ls-files '*.sql'` พบ tracked SQL 8 ไฟล์ เนื้อหาไม่ได้ถูกอ่านหรือพิมพ์ ไฟล์เดิมยังอยู่และยัง tracked ตามข้อห้ามของงานนี้

`.gitignore` ป้องกัน `storage/backups/`, `storage/logs/`, `config/local.php` และ execution ledger ใหม่ แต่ ignore rule ไม่ลบไฟล์ที่ track อยู่แล้ว

แนวทาง remediation ภายหลังต้องได้รับอนุมัติแยกต่างหาก:

1. ประเมินว่า remote repository หรือ clone ใดเคยรับ SQL เหล่านี้
2. หมุนเวียน credential/token ที่อาจอยู่ในข้อมูล backup ตาม incident procedure
3. สำรองไฟล์ต้นฉบับไป encrypted/offline storage ที่ตรวจสอบได้
4. เลือกว่าจะหยุด track เฉพาะ commit ใหม่ หรือ rewrite history ด้วยการประสานผู้ใช้ทุก clone
5. ห้าม force-push/history rewrite โดยไม่มีแผน downtime และ approval ชัดเจน

## Remaining Risks

- Server-side PHP session ยังเก็บ DB password ชั่วคราวเพื่อรักษา workflow เดิม; ต้องป้องกัน session directory และเครื่อง host
- ไม่มี rate limiting ส่วนกลางสำหรับ login; ควรจำกัดผ่าน local firewall/web server และพิจารณาในเฟสถัดไป
- Conditional Secure cookie อาศัย direct HTTPS signal; proxy deployment ต้องกำหนด HTTPS/access rules เอง
- Symlink/junction escape test อาจรันไม่ได้บน Windows ที่ไม่มีสิทธิ์สร้าง symlink
- Apache rules ไม่ครอบคลุม Nginx/IIS; ต้องชี้ document root ไป `public/`
- Live backup compatibility, large datasets และ restore safety ยังไม่ verified ใน Phase 1

## Deferred to Phase 2

- Restore staging/validation/recovery workflow
- Backup job model, queue/progress/cancellation และ richer history metadata
- Disposable database compatibility fixtures หากผู้ใช้อนุมัติ environment แยก
- Credential/session-at-rest hardening เพิ่มเติมและ login rate limiting ตาม deployment model
- Controlled remediation ของ SQL ที่อยู่ใน Git history

