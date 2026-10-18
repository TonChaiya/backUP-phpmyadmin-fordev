# Seamless Editorial Application Layout Design

วันที่: 2026-10-17  
สถานะ: Approved direction — Semantic Flattening

## เป้าหมาย

ปรับหน้าหลัก Dashboard, Backup, Restore, Restore Lab, History และ Settings ให้เป็น Modern SaaS application ที่อ่านต่อเนื่องเหมือน landing/editorial page บนพื้นหลังเดียวกัน ลด floating cards, nested panels และเงาที่ไม่จำเป็น โดยรักษา workflow, security และ JavaScript contracts เดิมทั้งหมด

## หลักการออกแบบ

- Shared navigation และ compact page header เป็นกรอบร่วมทุกหน้า
- เนื้อหาใช้ `section` ต่อเนื่อง แบ่งด้วย whitespace, typography และเส้นคั่นบาง
- ใช้พื้นผิวแยกเฉพาะ dialog, login และ workflow control ที่ต้องมีขอบเขตชัดเจน
- Navy/Indigo เป็น accent สำหรับ action, focus, active navigation และข้อมูลสำคัญ ไม่ใช้เป็นพื้นที่สีขนาดใหญ่
- มี `h1` หนึ่งรายการต่อหน้า และลำดับ `h2` ตาม section
- ทุก interactive target สูงอย่างน้อย 44px มี visible focus และ label ที่สัมพันธ์กับ control
- หน้าไม่เกิด horizontal overflow; ตารางอนุญาตให้เลื่อนภายใน region ที่มี label เท่านั้น
- รองรับ reduced motion และ breakpoint ที่ 960px/640px

## Shared Design System

`public/assets/app.css` เป็น source of truth เดียวสำหรับ tokens และ primitives:

- Canvas/surface: unified off-white canvas, white workflow surface, soft status background
- Width: content widthสูงสุด 1180px และ responsive side gutter 40px/24px
- Spacing: page header 24–32px, section 36–52px, internal rhythm 8/12/16/24px
- Divider: `--line` และ `--line-strong` โดยไม่มี shadow รอบ section ปกติ
- Typography: compact eyebrow, responsive `h1`, section `h2`, body/muted hierarchy
- Components: buttons, fields, status pills, table, empty/error/loading states และ navigation active state ใช้ token ร่วมกัน

Shared markup primitives:

- `.page-header` — compact title/description area
- `.editorial-section` — content section บนพื้นเดียว ไม่มี card chrome
- `.section-heading` — kicker/title/action alignment
- `.section-divider` หรือ border-top ของ section — ลำดับเนื้อหา
- `.workflow-surface` — isolation เฉพาะ form/job workflow ที่จำเป็น
- `.status-strip` — safety/connection status แบบเรียบ ไม่ใช่ banner ขนาดใหญ่
- `.metric-strip` — metrics ที่ใช้ divider แทน cards

## Page Designs

### Dashboard

- ยกเลิก hero banner ทั้งหมด
- Compact page header แสดง connection status และปุ่ม “เริ่ม Manual Backup”
- Metrics เป็นแถวเดียวบนพื้นเดียว ใช้ divider ระหว่างค่า
- Recent Jobs เป็นรายการเต็มความกว้างที่แบ่งแถวด้วยเส้นบาง
- Restore Safety เป็น editorial status section ต่อท้าย ไม่เป็น sidebar card

### Backup

- เรียง Connection → Database Selection → Sequential Job Progress ในแนวตั้ง
- Connection form และ selection workflow ใช้โครง section เดียว; input group เป็นพื้นที่ควบคุม ไม่เป็น nested card
- รายการฐานยังคง selectable surface เพื่อให้ click target ชัดเจน
- Job status ใช้ timeline/list rows และ status pill จริง ไม่มีเปอร์เซ็นต์จำลอง

### Restore

- เรียง Safety Status → Artifact Selection/Target → Preflight Findings → Preflight History
- Safety gate เป็น status strip และ definition rows
- Preflight form กับผลตรวจใช้ workflow surface เมื่อจำเป็น แต่ไม่ซ้อน panel header/body หลายชั้น
- ไม่มี execute control และไม่มีข้อความที่ทำให้เข้าใจว่า SQL เริ่มทำงาน

### Restore Lab

- เรียง Live-disabled status → Offline Proposal → Safety Requirements/Isolation Readiness → Validation State
- แสดง exact source scope เป็นรายการต่อเนื่อง
- คง `data-live_execution_enabled="false"` และไม่มี live execution control

### History

- Full-width editorial section
- Search/filter toolbar อยู่ติดกับ table context
- ตารางใช้ thin row dividers และ sticky/soft header โดยไม่มี outer card
- ตารางเลื่อนภายใน labelled region บนหน้าจอแคบ

### Settings

- เรียง Connection → Storage → Security → Environment เป็น section แนวตั้ง
- ใช้ definition rows และ form alignment เดียวกัน
- ไม่มี grid ของ settings cards หรือ nested panels

## Compatibility Contracts

ห้ามเปลี่ยน `data-*` hooks, form methods/actions, CSRF fields, endpoint URLs, job IDs, polling state elements, navigation URLs, session/auth markup และ authorized download URLs การแก้จำกัดที่ views, shared CSS และ UI JavaScript เฉพาะเมื่อ responsive/navigation behavior จำเป็น

## Accessibility และ Responsive

- Skip link, `aria-current`, mobile navigation, Escape-to-close และ focus return คงเดิม
- Status ที่เปลี่ยนแปลงใช้ `aria-live` เดิม
- Empty/error/loading content อ่านได้โดยไม่อาศัยสีเพียงอย่างเดียว
- Tablet ลด multi-column layout เป็นหนึ่งคอลัมน์ตาม content priority
- Mobile ทำ action group เต็มความกว้าง, definition rows stack และไม่มีข้อความ clipped
- `prefers-reduced-motion` ลด transition/animation

## Verification Scope

- PHP render tests ตรวจ semantic sections, heading count, preserved hooks/actions/CSRF และ absence ของ dashboard hero/nested presentation wrappers
- JavaScript responsive test ตรวจ Desktop 1440px, Tablet 820px, Mobile 390px, page overflow, section flow, navigation และ table containment
- Browser inspection ผ่าน Laragon Apache ตรวจทุก route โดยไม่ submit form หรือเริ่ม database operation
- ไม่รัน backend/full regression; rerun เฉพาะ existing routing/UI/API contract tests ที่ markup ได้รับผลกระทบ

## Out of Scope

Backup/Restore engine, SQL parser, job executor, database lock, authentication, CSRF, storage/download security, safety gates, database connection และ feature ใหม่ทั้งหมดอยู่นอกขอบเขต Live Restore ยังคง disabled
