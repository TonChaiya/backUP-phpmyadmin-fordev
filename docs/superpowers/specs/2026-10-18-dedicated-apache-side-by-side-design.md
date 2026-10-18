# Dedicated Apache Side-by-Side Deployment Design

สถานะ: approved architecture, offline preparation only  
วันที่: 2026-10-18

## Intent and boundaries

เตรียม deployment แบบ side-by-side สำหรับแอปนี้เพียงตัวเดียว โดยไม่แก้ shared Laragon Apache เดิม ตัว service ใหม่รับเฉพาะ `127.0.0.1:8788`, ใช้ local non-admin identity เฉพาะ, เสิร์ฟเฉพาะ `Application/public`, อ่าน source/runtime binaries แบบ read-only และเขียนได้เฉพาะ external runtime data ที่กำหนดไว้ล่วงหน้า

รอบนี้สร้างเฉพาะ configuration template, policy validator, read-only preflight, focused tests และ runbook ไม่มีการสร้าง account/service/path, ไม่มี ACL mutation, ไม่มีการ start/restart Apache และไม่มี database operation

## Fixed topology

- Service: `BackupPhpMyAdminApache`
- Runtime identity: `.\BackupPhpRuntime`; SID ถูกสร้างภายหลังและต้อง pin เป็น deployment evidence
- Listener: `127.0.0.1:8788` เท่านั้น
- Application: `C:\Program Files\BackupPhpMyAdmin\Application`
- DocumentRoot: `C:\Program Files\BackupPhpMyAdmin\Application\public`
- Apache/PHP copies: `C:\Program Files\BackupPhpMyAdmin\Runtime\Apache` และ `...\PHP`
- Mutable data: `C:\ProgramData\BackupPhpMyAdmin\RuntimeData`
- Secret helper/store: `C:\ProgramData\BackupPhpMyAdmin\SecretProvider`; ยังคงถูก Authenticode gate และยังไม่ enroll secret

Apache config ไม่มี Include/Alias/VirtualHost จาก Laragon และใช้ minimal module allowlist. PHP config เปิดเฉพาะ `mysqli` และ `sodium`, แยก session/temp/log และคง `proc_open` สำหรับ native helper ในอนาคตโดยปิด shell-oriented process APIs อื่น

## Trust and filesystem model

Administrators/SYSTEM ควบคุม Program Files, runtime binaries, application source และ deployment evidence; runtime SID ได้ read/execute เท่านั้น RuntimeData child directories ให้ Modify เฉพาะ runtime SID ตามงานที่ต้องเขียน ห้าม UNC, non-NTFS, symlink, junction หรือ reparse point ในทุก path segment และห้ามใช้ junction สำหรับ current-release switching

Source package ต้องมี per-file SHA-256 manifest และ manifest hash ที่ approved out-of-band ก่อน copy. Preflight ตรวจ exact path/listener/account/SID, collision, NTFS/reparse, port, manifest, runtime tree, config evidence และ shared-Laragon before/after evidence แบบ fail closed

## Data and lifecycle

Existing development storage คงอยู่ที่เดิม Migration ใช้ copy-only เมื่อ jobs/lock quiescent แล้วตรวจ inventory/hash/manifest ก่อนสลับ production `config/local.php`; ห้าม move/delete/rewrite ต้นฉบับ Rollback หยุดเฉพาะ dedicated service และคง data ทั้งสองฝั่งไว้

Patch Apache/PHP ผ่าน reviewed staging artifact, vendor signature/hash, compatibility/syntax tests และ maintenance window เฉพาะ service ใหม่นี้ Shared Laragon ไม่เป็น dependency และไม่ถูก restart

## Acceptance

- Semantic config validator และ real Apache/PHP syntax probe ผ่าน
- Read-only preflight ให้สถานะ blocked จน exact SID, user right, runtime tree, trusted source manifest และ Laragon before/after evidenceพร้อม
- ACL owner/effective-rights, service execution, loopback HTTP, Apache-context session และ synthetic DPAPI ยัง NOT VERIFIED จนได้รับ live Windows deployment authorization
