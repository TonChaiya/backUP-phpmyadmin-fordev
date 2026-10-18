# Native Helper Signed Deployment Preparation Plan

**Goal:** Provide a deterministic Build → Sign → Verify → Deploy workflow that is safe to review offline and cannot mutate production state in this round.

**Architecture:** Build to a fixed staging directory outside the web root. Validate signed artifacts through a strict read-only PowerShell verifier and typed evidence. Produce deployment plans only; production paths, trust stores and credentials remain untouched.

### Task 1: Deterministic build contract

Create `global.json`, locked dependency files and `tools/native-helper/build-release.ps1`. Test fixed SDK/RID/configuration/output and single-file/no-trim/no-AOT settings.

### Task 2: Signature and certificate validation

Create a fixed deployment module plus `verify-signed-artifact.ps1`. Test unsigned, wrong EKU, publisher, thumbprint, timestamp and hash evidence fail closed; valid deterministic fixture passes.

### Task 3: Preflight and deployment plan

Create `deployment-preflight.ps1` and `new-deployment-plan.ps1`. Test fixed ProgramData targets, runtime SID, collision/reparse blockers and prove scripts contain no mutating deployment/trust/certificate commands.

### Task 4: Post-deployment verification preparation

Create read-only `verify-deployed-helper.ps1` and `verify-runtime-identity.ps1`. Test missing deployment and identity mismatch fail closed without starting secret operations.

### Task 5: Verification and evidence

Run focused PowerShell/PHP tests, syntax analysis and forbidden-operation scans. Update `TEST_BASELINE.md`; keep production deployment BLOCKED.
