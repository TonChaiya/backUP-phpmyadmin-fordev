# Dedicated Apache Side-by-Side Deployment Preparation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Produce exact, fail-closed deployment configuration and validation evidence without changing Windows, Laragon, Program Files or ProgramData.

**Architecture:** Fixed Apache/PHP templates and a machine-readable contract define the side-by-side topology. A PHP semantic validator and PowerShell read-only evidence tools verify policy; a temporary materialization invokes the installed Apache/PHP parsers without deploying anything.

**Tech Stack:** Apache 2.4 Win64/WinNT, PHP 8.3 TS/mod_php, PowerShell 7 read-only inspection, PHP 8.3 tests, NTFS/Windows service planning.

**Spec:** `docs/superpowers/specs/2026-10-18-dedicated-apache-side-by-side-design.md`

## Global Constraints

- No Windows account/service/ACL or Program Files/ProgramData mutation.
- No shared Laragon configuration or restart.
- No MySQL connection, backup/restore execution or access to `jhcisdb`.
- No commit, push, tag or release.
- All production paths/listener/service identity are fixed and fail closed.

## Review Focus

- A wildcard listener or permissive public directory must be rejected.
- A Laragon Include/Alias or shared temp/session path must be rejected.
- Existing destination, non-NTFS or reparse-backed path must block deployment.
- Missing/mismatched service SID or privileged group membership must block deployment.
- Untrusted manifest, config evidence or shared-Laragon baseline must remain NOT VERIFIED.

---

### Task 1: Fixed configuration and semantic policy

**Files:**
- Create: `deployment/dedicated-apache/deployment-contract.json`
- Create: `deployment/dedicated-apache/conf/httpd.conf`
- Create: `deployment/dedicated-apache/php/php.ini`
- Create: `app/Deployment/DedicatedApacheConfigValidator.php`
- Test: `tests/DedicatedApacheDeploymentPreparationTest.php`

**Interfaces:**
- Consumes: existing `RuntimeDataPaths::APPROVED_PRODUCTION_ROOT` contract.
- Produces: `DedicatedApacheConfigValidator::validate(string, string, array): array` and immutable deployment templates.

- [x] Write and run RED tests for listener/document-root/module/path isolation and insecure mutations.
- [x] Implement exact contract, minimal Apache configuration and isolated PHP configuration.
- [x] Implement semantic validator and rerun focused tests GREEN.

### Task 2: Read-only deployment preflight

**Files:**
- Create: `tools/dedicated-apache/validate-preflight-evidence.ps1`
- Create: `tools/dedicated-apache/deployment-preflight.ps1`
- Test: `tests/DedicatedApacheDeploymentPreparationTest.php`

**Interfaces:**
- Consumes: exact topology and future reviewed source manifest/SID evidence.
- Produces: bounded JSON readiness evidence; exit `0` only when all gates pass and `9` otherwise.

- [x] Write and run RED tests for each blocker and forbidden mutation commands.
- [x] Implement strict evidence shape/path/SID validation and read-only local inspection.
- [x] Confirm current machine reports blockers without mutation.

### Task 3: Real parser checks and operations runbook

**Files:**
- Create: `tests/dedicated-apache-syntax.ps1`
- Create: `docs/DEDICATED_APACHE_DEPLOYMENT_PLAN.md`
- Modify: `TEST_BASELINE.md`

**Interfaces:**
- Consumes: templates, installed Apache 2.4.66 and PHP 8.3.30 only for offline parsing.
- Produces: syntax/extension/path isolation evidence and exact future authorization procedure.

- [x] Materialize paths only under a random OS temporary directory and invoke real Apache/PHP parsers.
- [x] Fix Windows/module and PHP extension-load issues found by RED probes.
- [x] Run final focused tests, lints, mutation scan and append fresh evidence to `TEST_BASELINE.md`.

No commit step is included because the task explicitly prohibits commits.
