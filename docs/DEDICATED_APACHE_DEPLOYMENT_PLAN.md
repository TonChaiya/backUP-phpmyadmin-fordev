# Dedicated Apache/PHP Side-by-Side Deployment Plan

สถานะ: **OFFLINE PREPARATION — NOT DEPLOYED**  
วันที่: 2026-10-18

## 1. Deployment architecture

Service ใหม่ `BackupPhpMyAdminApache` ทำงานด้วย local non-admin account `.\BackupPhpRuntime` และรับ request เฉพาะ `127.0.0.1:8788` เท่านั้น Shared Laragon Apache ที่ `8081/443`, VirtualHosts, aliases, PHP และ project อื่นไม่ถูก include, proxy, stop หรือแก้ไข

| Purpose | Exact path |
|---|---|
| Immutable application | `C:\Program Files\BackupPhpMyAdmin\Application` |
| Web document root | `C:\Program Files\BackupPhpMyAdmin\Application\public` |
| Dedicated Apache | `C:\Program Files\BackupPhpMyAdmin\Runtime\Apache` |
| Dedicated PHP | `C:\Program Files\BackupPhpMyAdmin\Runtime\PHP` |
| Mutable runtime data | `C:\ProgramData\BackupPhpMyAdmin\RuntimeData` |
| Signed native helper | `C:\ProgramData\BackupPhpMyAdmin\SecretProvider\backup-secret-helper.exe` |

Deployment contract อยู่ที่ `deployment/dedicated-apache/deployment-contract.json`; template Apache/PHP อยู่ใต้ directory เดียวกัน Application production config ต้องระบุ `runtime_data_mode=production` และ exact `runtime_data_root`; ไม่มี automatic switch หรือ directory creation จากแอป

## 2. Apache/PHP configuration

Apache template:

- exact `Listen` และ `ServerName` เป็น `127.0.0.1:8788`
- default filesystem scope `Require all denied`; เปิดเฉพาะ exact `Application/public` ด้วย `Require local`
- `AllowOverride None`, `Options None`; ไม่พึ่ง `.htaccess`
- ไม่มี `Include`, `IncludeOptional`, `Alias`, `ScriptAlias`, `<VirtualHost>`, SSL, proxy, CGI, autoindex หรือ reference ไป `C:\laragon`
- modules เฉพาะ authz core/host, filter, request timeout, MIME, log config, directory index, headers และ mod_php
- PID/mutex/logs อยู่ใต้ RuntimeData; ไม่ใช้ Laragon logs/temp

PHP template:

- thread-safe x64 build ที่เข้ากันกับ Apache VS runtime; initial validated pair คือ Apache 2.4.66 VS18 + PHP 8.3.30 TS x64
- extensions เฉพาะ `mysqli` และ `sodium`; JSON/hash/session/mysqlnd เป็น core/bundled capabilities ของ build
- `session.save_path`, `sys_temp_dir`, `upload_tmp_dir`, `error_log` แยกอยู่ใต้ RuntimeData
- `open_basedir` เปิดเฉพาะ Application, dedicated PHP, RuntimeData และ SecretProvider
- display/startup errors ปิด, log errors เปิด, URL include/fopen และ file upload ปิด
- ปิด shell-oriented functions แต่คง `proc_open` สำหรับ fixed native-helper adapter; live restore ยัง hard-disabled

## 3. Runtime account and service lifecycle

ขั้นตอนต่อไปนี้ต้องได้รับ authorization และทำโดย deployment administrator เท่านั้น:

1. สร้าง `.\BackupPhpRuntime` ด้วย random high-entropy password ผ่าน SecureString/credential UI; ไม่ส่ง password ใน command argument, environment, file หรือ log
2. ยืนยัน account ไม่อยู่ Administrators/Users group ที่ให้ write กว้าง, ไม่มี interactive/RDP/network logon และได้รับเฉพาะ `Log on as a service`; ตรวจว่าไม่มี `Deny log on as a service`
3. สร้าง directory tree จาก trusted empty parents, apply ACL และ verify owner/effective rights ก่อน copy artifact
4. ติดตั้ง Apache service โดยใช้ fixed binary/config และกำหนด service Log On identity ก่อน start ครั้งแรก ห้ามให้ service เริ่มชั่วคราวเป็น LocalSystem
5. ตั้ง Automatic (Delayed Start); restart-on-failure ได้หลังยืนยันว่า interrupted job handling ปลอดภัย แต่ห้าม automatic backup/restore retry
6. ก่อนทุก start/patch ตรวจ config syntax, exact service ImagePath/account, listener collision, ACL และ source hashes
7. การ stop/restart กระทบเฉพาะ `BackupPhpMyAdminApache`; shared Laragon process/service ไม่อยู่ใน dependency chain

การ reset password โดย administrator อาจทำให้ DPAPI CurrentUser material ใช้งานไม่ได้ หลังมี credential จริงต้องใช้ controlled rotation/re-enrollment procedure ไม่ reset แบบฉุกเฉินโดยไม่มี recovery plan รอบนี้ยังไม่มี secret enrollment

## 4. Exact ACL and owner matrix

ใช้ explicit SID ไม่ใช้ชื่อที่ resolve ไม่แน่นอน ปิด inheritance หลังสร้าง path จาก trusted parent และตรวจ effective access; `CREATOR OWNER`, `Users`, `Authenticated Users` และ `Everyone` ต้องไม่มี write ACE

| Path | Owner/control | Runtime SID |
|---|---|---|
| `C:\Program Files\BackupPhpMyAdmin` | Administrators + SYSTEM: Full Control | traverse/read only as inherited by exact children |
| `...\Application` including `config` and `public` | Administrators + SYSTEM: Full Control | Read & Execute, List, Read; no create/write/delete/ACL/owner |
| `...\Runtime\Apache` and `...\Runtime\PHP` | Administrators + SYSTEM: Full Control | Read & Execute only |
| `C:\ProgramData\BackupPhpMyAdmin` | Administrators + SYSTEM: Full Control | Traverse/List only |
| `...\RuntimeData` root | Administrators + SYSTEM: Full Control | Traverse/List/Read; no arbitrary child creation |
| each of `backups`, `jobs`, `logs`, `restore-jobs`, `restore-integration-jobs`, `restore-integration-ownership`, `database-operations`, `temporary`, `sessions` | Administrators + SYSTEM: Full Control | Modify + Synchronize on exact subtree |
| `...\SecretProvider` executable/config | Administrators + SYSTEM: Full Control | Read & Execute only |
| `...\SecretProvider\secrets` | owner recorded in deployment evidence; inheritance disabled | exact runtime SID Full Control; no broad ACE |
| admin-only deployment manifest/evidence | Administrators + SYSTEM: Full Control | Read only only if runtime startup verification requires it; otherwise none |

ทุก segment ต้องอยู่บน local NTFS volumeและไม่เป็น symlink/junction/mount point/reparse point Deployment implementation ต้องเปิด path ด้วย no-follow semantics, ตรวจ final path/volume/owner หลัง creation และก่อน copy เพื่อป้องกัน path squatting ห้ามใช้ junction/symlink เป็น release switch

## 5. Safe preflight

`tools/dedicated-apache/deployment-preflight.ps1` เป็น read-only collector และตั้งใจคืน exit `9` ในรอบ preparation โดยตรวจ:

- destination collision, NTFS และ reparse point ของ existing ancestors
- port `127.0.0.1:8788`
- exact account และ future pinned SID; non-admin membership
- trusted manifest hash และ per-file SHA-256 ของ staging source
- complete RuntimeData directory set

หลักฐานต่อไปนี้ต้อง supply จาก authorized deployment workflow และยัง fail closed ใน collector ปัจจุบัน: user-right assignment, Apache/PHP final syntax evidence, shared Laragon before/after hash evidence `validate-preflight-evidence.ps1` รับเฉพาะ bounded JSON shape และคืน exit `0` เมื่อทุก gate เป็นจริง

Source manifest ต้องมี relative paths แบบ allowlistedและ SHA-256 ต่อไฟล์ Manifest hash ต้องได้รับอนุมัติผ่านช่องทางแยกจาก staging tree; ห้ามเชื่อ manifest ที่แก้ได้โดย runtime account

## 6. Non-destructive migration

หลัง authorization เท่านั้น:

1. หยุดการสร้างงานใหม่และยืนยันว่า backup/restore jobs ไม่มี `queued/running`; shared database-operation lock และ legacy lock ต้อง quiescent
2. บันทึก source inventory ของ existing `storage` แยกตาม directory พร้อม file size, mtime และ SHA-256; backup SQL ต้องผ่าน completion marker/manifest/SHA เดิม
3. สร้าง RuntimeData tree และ ACL ก่อน copy โดยไม่ให้ runtime SID สร้าง root เอง
4. Copy แต่ละ directoryด้วย no-follow/`/XJ` semantics; ห้าม move, rename, delete หรือ overwrite destination collision
5. สร้าง destination inventory ใหม่และ compare exact relative name, size และ SHA-256; verify job JSON/history/ownership journals parse ได้โดยไม่ rewrite
6. วาง production `config/local.php` แบบ admin-owned read-only โดยระบุ production root จากนั้นรัน bootstrap/path policy ภายใต้ runtime SID
7. เก็บ existing development storage ไว้ครบถ้วนและ mark migration evidence ด้วย run ID, source/destination identity และ hashes

หาก quiescence, ownership, no-follow path หรือ integrity พิสูจน์ไม่ได้ ให้หยุดก่อน config switch

## 7. Rollback

1. หยุดเฉพาะ dedicated serviceและยืนยัน process/port 8788 ปิด
2. คืน production application/runtime release ก่อนหน้าหรือ service ImagePath/config ที่บันทึกไว้; ห้ามเปลี่ยน shared Laragon
3. หากยังไม่เคย enroll live credential ให้กลับไปใช้ development URL/storage ได้โดยไม่แตะ copied RuntimeData
4. ถ้ามี data ใหม่ใน RuntimeData ให้เก็บทั้งสองชุด read-onlyเพื่อ reconcile; ห้าม copy-back แบบ overwriteและห้ามลบ backup/job/history
5. Native-helper/DPAPI rollback เป็น authorization แยกและต้องรักษา signed pin/SID binding; ห้าม copy envelope ข้าม SID

## 8. Apache/PHP maintenance

1. ติดตาม Apache Lounge/PHP supported releasesและ security advisories; ใช้ x64 Thread Safe PHP ที่เข้ากันกับ Apache ABI/VC runtime
2. ดาวน์โหลดบน controlled admin context, ตรวจ vendor hash/signature และบันทึก artifact inventory
3. แตกไป admin-only staging ที่ไม่ใช่ Program Files/Data production; ไม่ reuse Laragon binariesเป็น live dependency
4. Render template, `httpd -t`, PHP extension/path probe และ loopback smoke บน isolated test port/contextก่อน maintenance window
5. หยุดเฉพาะ dedicated service, retain previous runtime release, copy reviewed releaseไป fixed runtime roots, reapply/verify ACL และ hashes แล้ว start
6. ตรวจ exact listener, service SID, page/API auth/session, no direct sensitive files และ logs; database/backup/restore testsต้องมี authorization แยก
7. Rollback runtime releaseเดิมเมื่อ check ใดล้มเหลว; เก็บ failed artifact/evidenceสำหรับ diagnosis และไม่ลบ backups

## 9. Current readiness and stop conditions

Current read-only inspection: destination rootsไม่ชน, existing ancestorsเป็น NTFS/no reparse และ port 8788 ว่าง Templates ผ่าน real parser probeแล้ว Candidate source package `wt-b5246f66b9fd94a3` ถูกสร้างแบบ content-addressed จาก current working tree พร้อม deterministic manifest digest `79fbd7a1fadc10c2737b55bf1f6b0bcba20f1ed5c96855a2a484204d80793f96` และ manifest-file SHA-256 `9d9051d5b81cd9ec03703a89cbb15698bc99a67793f43323af24b39e1500f6d3`; สถานะยัง **GENERATED / NOT APPROVED** จนกว่าจะผ่าน separate review. Deployment ยัง **BLOCKED** เพราะ account/SID, service right, Program Files/ProgramData trees/ACL, source approval, source copy, service registration, final listener, Apache-context session และ shared-Laragon before/after evidenceยังไม่มี

Stop ทันทีเมื่อพบ path collision, non-NTFS/reparse, port occupied, source hash mismatch, account/SID mismatch, admin membership, broad write ACE, config syntax failure, shared Laragon drift หรือ identity drift No database connectionหรือ live restore เป็นส่วนของ deployment นี้
