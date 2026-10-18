# Native Helper Signed Deployment Preparation

สถานะ: Offline preparation only — production deployment blocked  
วันที่: 2026-10-18

## Certificate strategy

| Strategy | Security and operations | Decision |
|---|---|---|
| Organization-managed signing service | strongest custody/audit, centralized renewal/revocation and hardware-backed key; depends on existing enterprise service | preferred when available |
| Internal/private CA | appropriate for local/internal Windows deployment; permits controlled publisher trust and rotation, but CA root/issuing trust distribution needs separate authorization | recommended fallback for this project |
| Self-signed development certificate | easy for isolated testing, but trust is manual, revocation/audit weak and encourages local trust exceptions | development only; never accepted for production credentials |

Selected operational target is an organization-managed code-signing identity. If unavailable, use a dedicated internal-CA code-signing certificate; do not use a self-signed certificate for production.

Certificate requirements:

- Enhanced Key Usage must contain Code Signing only/relevantly: OID `1.3.6.1.5.5.7.3.3`
- Key Usage must permit Digital Signature
- SHA-256 Authenticode digest and RFC 3161 timestamp using SHA-256
- RSA 3072+ is the compatibility baseline; private key must be non-exportable and hardware-backed or service-held when possible
- no private key, PFX password or secret in source, environment, command line, log or report
- pin exact normalized publisher subject and uppercase certificate thumbprint; thumbprint is primary signer identity, publisher is a second consistency check
- hash pin is computed after signing and timestamping; signing changes the executable bytes

Renewal/rotation uses an explicit overlap procedure: issue and validate the new certificate, review new publisher/thumbprint, sign a new build, calculate its post-signing hash, update reviewed pins, then deploy. Old pins are removed only after rollback window closes. Revoked/expired/untrusted signers fail closed.

## Reproducible workflow

1. **Build** — fixed .NET 10 SDK from `global.json`; locked NuGet restore; Release `win-x64`, self-contained, single-file, deterministic/CI build, no trim/AOT. Staging is fixed outside the web root under LocalAppData.
2. **Sign** — authorized signing operator/service signs the staged executable. This round only emits the exact signing requirements; it never calls SignTool.
3. **Verify** — read-only verifier requires Authenticode `Valid`, exact Code Signing EKU, exact publisher/thumbprint, RFC 3161 timestamper and optional expected post-signing SHA-256.
4. **Preflight** — checks fixed production paths, absence of reparse points, artifact signature/hash, exact runtime SID syntax, production collision and dry-run mode.
5. **Deploy** — current script produces an immutable JSON deployment plan only. A later separately authorized implementation may create ProgramData directories, copy the verified artifact atomically and apply ACLs.
6. **Verify deployed state** — future read-only validation rechecks path, signature/hash, no reparse point and ACL: Administrators/SYSTEM control executable; runtime SID read/execute only; secret directory is runtime-SID exclusive.
7. **Identity validation** — future tool invokes fixed deployed helper with `identity` and `{}` through STDIN, then compares exact SID with approved Apache/CLI runtime SID before enrollment.

## Fail-closed controls

- no execution-policy bypass, signing bypass, unsigned mode or development-certificate production mode
- no user-controlled production destination; fixed `C:\ProgramData\BackupPhpMyAdmin\SecretProvider`
- dry-run tool contains no copy, directory creation, ACL mutation, certificate import or trust-store mutation
- signature must be valid before hash is accepted; publisher/thumbprint/EKU/timestamp mismatch is a blocker
- post-signing hash, not unsigned build hash, is the production pin
- existing production executable/directory collision, reparse point, ACL ambiguity or SID mismatch is a STOP condition
- mutable application data ต้องอยู่ใต้ exact `C:\ProgramData\BackupPhpMyAdmin\RuntimeData` ที่ deployment สร้างและกำหนด ACL ล่วงหน้า; ห้ามเก็บ production runtime data ใต้ source/web root หรือผ่าน junction/symlink
- no MySQL/Restore factory/runner wiring is part of this workflow

## Authorization gates

Separate authorization is required for certificate issuance/import, trust distribution, signing, pin updates, ProgramData deployment/ACL mutation, Apache/CLI identity validation and credential enrollment. Until then production pins remain empty and `native_helper_deployment_unverified` remains mandatory.
