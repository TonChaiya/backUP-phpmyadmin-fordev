# Seamless Editorial Application Layout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** เปลี่ยนหกหน้าหลักให้เป็น seamless editorial layout โดยรักษา functional/security contracts เดิมทั้งหมด

**Architecture:** ใช้ semantic flattening ที่ view layer และรวม visual rules ไว้ใน `public/assets/app.css` เพียงแห่งเดียว Existing `data-*` hooks, form/API contracts และ page JavaScript จะไม่เปลี่ยน เว้นแต่จำเป็นต่อ responsive navigation

**Tech Stack:** PHP 8.3 views, semantic HTML, shared CSS, vanilla JavaScript, PHP fixture tests, Node/Chrome responsive checks

**Spec:** `docs/superpowers/specs/2026-10-17-seamless-editorial-layout-design.md`

## Global Constraints

- แก้เฉพาะ views, CSS, UI JavaScript และ UI tests/baseline
- ห้ามเชื่อมต่อ MySQL หรือเข้าถึงฐานข้อมูลใด รวมถึง `jhcisdb`
- ห้ามแก้ Backup/Restore engine, SQL parser, executor, locks, auth/CSRF, storage/download security หรือ safety gates
- รักษา `data-*`, form actions, CSRF fields, API URLs และ polling contracts
- Change-Based Testing เท่านั้น; ไม่มี commit/push/tag/release

## Review Focus

1. หน้า 390px ต้องไม่มี page-level horizontal overflow และ action/text ต้องไม่ clipped
2. Navigation ต้องรักษา active state, mobile toggle, Escape close และ keyboard focus
3. Restore/Restore Lab ต้องไม่มี live execute control และยังแสดง disabled state
4. Backup/Restore polling selectors และ form endpoints ต้องคงเดิมหลัง flatten markup
5. History table ต้อง scroll ภายใน labelled region โดยไม่ขยาย document width

---

### Task 1: Shared semantic shell and design tokens

**Files:**
- Modify: `tests/Phase4UiTest.php`
- Modify: `views/layout.php`
- Modify: `public/assets/app.css`

**Interfaces:**
- Produces shared `.editorial-section`, `.workflow-surface`, `.status-strip`, `.metric-strip` and compact `.page-header` contracts used by every page

- [ ] Add failing render assertions for compact shared header, one `h1`, editorial section primitives and retained navigation/accessibility hooks.
- [ ] Run `php tests/run.php Phase4UiTest`; verify RED on missing editorial contracts.
- [ ] Implement shared layout markup/tokens and remove general section shadows/card chrome.
- [ ] Rerun the focused test; expect PASS.

### Task 2: Dashboard semantic flattening

**Files:**
- Modify: `tests/Phase4UiTest.php`
- Modify: `views/pages/dashboard.php`
- Modify: `public/assets/app.css`
- Modify: `tests/dashboard-responsive.test.js`

**Interfaces:**
- Consumes Task 1 primitives
- Preserves dashboard facts, connection status and Manual Backup URL

- [ ] Add failing assertions that dashboard has no hero, uses header action/status, metric strip, full-width recent activity and editorial safety section.
- [ ] Run filtered PHP test and existing responsive test; verify intended RED failures.
- [ ] Flatten dashboard markup/CSS while preserving one heading and factual status content.
- [ ] Update responsive assertions to new section selectors and rerun GREEN.

### Task 3: Backup, Restore and Restore Lab workflows

**Files:**
- Modify: `tests/Phase4UiTest.php`
- Modify: `tests/RestoreIntegrationApiAndUiTest.php`
- Modify: `views/pages/backup.php`
- Modify: `views/pages/restore.php`
- Modify: `views/pages/restore-integration.php`
- Modify: `public/assets/app.css`

**Interfaces:**
- Consumes Task 1 primitives
- Preserves all existing form/API/data attributes and restore hard-disable signals

- [ ] Add failing assertions for ordered editorial sections, limited workflow surfaces, preserved CSRF/actions/data hooks and absence of live restore controls.
- [ ] Run `Phase4UiTest` and `RestoreIntegrationApiAndUiTest`; verify RED only on new layout expectations.
- [ ] Flatten all three views and use dividers/status strips instead of panel grids; keep dialog and necessary workflow isolation.
- [ ] Rerun focused PHP and existing backup/restore UI JavaScript tests; expect PASS.

### Task 4: History and Settings continuous layouts

**Files:**
- Modify: `tests/Phase4UiTest.php`
- Modify: `views/pages/history.php`
- Modify: `views/pages/settings.php`
- Modify: `public/assets/app.css`

**Interfaces:**
- Consumes Task 1 primitives
- Preserves history filter hooks/download URLs and settings form actions/CSRF

- [ ] Add failing assertions for full-width history/table region, integrated toolbar, vertical settings sections and absence of settings card grid.
- [ ] Run `php tests/run.php Phase4UiTest`; verify RED.
- [ ] Implement continuous history/settings markup and responsive table/definition/form styling.
- [ ] Rerun focused test; expect PASS.

### Task 5: Responsive, accessibility and browser verification

**Files:**
- Modify: `tests/dashboard-responsive.test.js`
- Modify only if required: `public/assets/app.js`
- Modify: `TEST_BASELINE.md`

**Interfaces:**
- Verifies Tasks 1–4 without changing application behavior

- [ ] Extend browser assertions across Dashboard, Backup, Restore, Restore Lab, History and Settings at 1440x1000, 820x900 and 390x844; assert no document overflow, one `h1`, visible focus/navigation contract and contained table overflow.
- [ ] Run the responsive script through Laragon Apache and verify RED before final responsive fixes.
- [ ] Apply only CSS/UI-JS corrections required by observed failures.
- [ ] Run targeted PHP UI/routing/API suites, UI JavaScript syntax/behavior tests and Apache browser checks; require zero failures.
- [ ] Inspect desktop/mobile pages visually through the browser without submitting forms.
- [ ] Append only new observed UI evidence and remaining visual limits to `TEST_BASELINE.md`.

## Self-review Result

- Spec coverage: all six pages, shared layout, responsive/accessibility and safety constraints mapped to Tasks 1–5
- Contract consistency: preserved selectors/actions/endpoints are tested where each affected view is changed
- Review focus coverage: all five risks have explicit PHP or browser assertions
- Scope: no backend/database component is included
- Commit steps intentionally omitted because the user explicitly prohibited commit/push/tag/release
