# Restore Integration Extension — Design Specification

วันที่: 2026-10-17

สถานะ: **Design only — รออนุมัติก่อน Implementation**
แนวทางที่อนุมัติ: Structural Target Mapping + Safe Restore Integration Executor

## 1. เป้าหมาย ขอบเขต และหลักปกครอง

เอกสารนี้ออกแบบส่วนขยายสำหรับทดสอบวงจร `Backup -> Restore to new disposable database -> Validate -> Safe cleanup` บน MySQL/MariaDB Instance ของ Laragon เครื่องปัจจุบันเท่านั้น ส่วนขยายเป็น subsystem ใหม่ที่แยกจาก Phase 3 preflight และไม่เปลี่ยน Phase 3 ให้กลายเป็น SQL executor โดยอ้อม

หลักปกครองคือ **fail-closed และ evidence-driven**: ขั้นตอนที่แตะฐานข้อมูลจะเริ่มได้ต่อเมื่อ artifact, parser, server identity, credentials, privileges, target absence, ownership journal และ shared lock ได้รับการพิสูจน์ในรอบเดียวกันครบถ้วน การไม่มีหลักฐาน ความกำกวม ความไม่เข้ากันของเวอร์ชัน หรือ syntax ที่ไม่รองรับต้องจบเป็น `blocked` หรือ `failed`; ห้ามเดา ห้ามลด parser policy และห้ามเปิดด้วย configuration flag เพียงอย่างเดียว

ขอบเขตข้อมูลถูกตรึงดังนี้:

- Source แบบ read-only: `autoprintdb`, `drugmap_db`, `drug_stock_check`, `prepack_labels`
- Protected แบบห้ามเข้าถึงทุกกรณี: `jhcisdb`
- System schemas ที่ห้ามใช้เป็น target: `mysql`, `sys`, `information_schema`, `performance_schema`
- ฐานอื่นทั้งหมดอยู่นอกขอบเขต
- Target ต้องเป็นฐานใหม่ที่ผูกกับ test run ปัจจุบันแบบ exact name เท่านั้น

ส่วนขยายนี้เป็น **integration-test executor สำหรับ disposable targets** ไม่ใช่ production restore feature การคงฐาน restored ไว้ใช้งานจริง การ overwrite/merge ฐานเดิม และ automatic restore อยู่นอกขอบเขต

## 2. สถาปัตยกรรมที่เสนอ

ลำดับความเชื่อมั่นมีห้าชั้น และทุกชั้นต้องผ่านตามลำดับ:

1. **Artifact trust** — ใช้ `RestoreArtifactValidator` และ `BackupProvenanceIndex` เดิมตรวจ path containment, manifest, completion marker, bytes, SHA-256 และ Phase 2 succeeded provenance
2. **Structural trust** — compile SQL byte stream เป็น typed restore plan โดย lexer/parser ใหม่ที่เข้มเท่ากับหรือมากกว่า `StructuralSqlParser`; unknown/unsafe constructs ไม่มี executable representation
3. **Environment trust** — ยืนยัน Laragon-local server identityเดียวกันจากทั้ง backup-reader และ restore-runner connections, product/version, dedicated account identity, exact privileges และ target absence โดยไม่ enumerate ฐานอื่น
4. **Execution trust** — ถือ shared database-operation lock, สร้าง target จาก trusted identifier, บันทึก ownership evidence แล้ว execute statements ทีละ statement โดย target connection ถูก pin ไว้กับ target เท่านั้น
5. **Outcome trust** — เปรียบเทียบ schema/data/object evidence กับ artifact plan แล้ว cleanup ด้วย exact-name ownership verification

Phase 3 preflight ยังคงทำงานและคง safety gate ปิดเหมือนเดิม ส่วน subsystem ใหม่ใช้ `LiveRestoreAttestationGate` ของตนเองและรับเฉพาะหลักฐาน runtime ที่ตรวจได้จริง ไม่มี boolean `restore_enabled` และไม่มีเส้นทาง fallback ไปยัง direct import หรือ command-line client

Data flow ที่อนุญาต:

```text
Phase 2 artifact + job provenance
        -> full artifact revalidation
        -> typed restore plan compilation
        -> environment/privilege attestation
        -> acquire per-job + shared DB lock
        -> repeat artifact hash + server/target attestation
        -> create exact target + ownership marker
        -> sequential typed statement execution
        -> post-restore comparison
        -> succeeded/failed
        -> exact-name cleanup with ownership proof
        -> post-cleanup verification
```

ไม่มีขั้นตอนไหนอ่าน `jhcisdb` หรือใช้ unrestricted `SHOW DATABASES` รายชื่อ source/target ถูกส่งเป็นค่าคงที่จาก policy หรือ trusted job state และตรวจด้วย exact-name queries ต่อ `information_schema.SCHEMATA` เฉพาะชื่อเหล่านั้น

## 3. SQL Target Mapping และ Typed Statement Model

### 3.1 Parser contract

สร้าง `RestorePlanCompiler` แยกจาก Phase 3 interface เดิม แต่ reuse lexer rules และ safety rules เดียวกันผ่าน component กลางเพื่อลด parser drift ผลลัพธ์ไม่ใช่ SQL string ใหม่ แต่เป็น immutable `RestorePlan` ประกอบด้วย:

- artifact identity: relative path, bytes, SHA-256, provenance job ID
- source database metadata
- source charset/collation metadataที่ผ่าน allowlist
- ordered typed statements
- object inventory และ expected row counts/digests สำหรับ validation
- parser version/policy version
- unsupported findings ถ้ามี

Typed statement ขั้นต่ำประกอบด้วย `SessionDirective`, `CreateTable`, `DropObject`, `InsertRows`, `AlterTable`, `LockTables`, `UnlockTables`, `CreatePlaceholderView` และ typed objects ที่ได้รับการรองรับภายหลัง เช่น `CreateView`, `CreateTrigger`, `CreateRoutine`, `CreateEvent` ไม่มี `RawSqlStatement` หรือ generic execute escape hatch

Lexer ต้อง stream input และคงข้อจำกัดขนาด statement แบบ bounded. มันต้องเข้าใจ string literals, escaped bytes, quoted identifiers, comments, versioned comments และ `DELIMITER` โดยไม่ตัด statement จาก semicolon ภายใน body ผิดตำแหน่ง ทุก typed validator ต้องตรวจทั้งรูปร่าง statement และ scope ของ identifier

### 3.2 CREATE DATABASE และ USE

`CREATE DATABASE IF NOT EXISTS <source>` และ `USE <source>` จาก dump เดิมถูก parse เป็น `SourceDatabaseDeclaration` และ `SourceDatabaseSelection` metadata เท่านั้น:

- ต้องพบอย่างละหนึ่งครั้งและต้องตรงกับ manifest/provenance source
- ห้ามส่งสอง statement นี้ไป MySQL/MariaDB
- target มาจาก `TrustedTargetName` ที่ผูกกับ job/run เท่านั้น
- `TargetDatabaseProvisioner` สร้าง `CREATE DATABASE <exact-target>` จาก escaped identifier ที่ผ่าน policy และใช้ charset/collation metadata ที่ parser รับรอง
- หลังสร้าง executor เรียก API เลือกฐาน (`select_db`) ด้วย exact target; ไม่ execute `USE` จาก artifact

นี่เป็น semantic mapping ของ typed metadata ไม่ใช่ string replacement

### 3.3 Scope และ statement safety

ทุก executable statement ต้องใช้ unqualified object identifiers ภายใน active target เท่านั้น Schema-qualified reference ทุกชนิดถูก reject รวมถึง reference ที่ซ่อนใน view body, trigger/routine/event body, executable comments หรือ generated expressions

ต้อง reject อย่างน้อย:

- additional/mismatching `CREATE DATABASE` หรือ `USE`
- dynamic SQL: `PREPARE`, `EXECUTE`, constructed SQL, indirect `CALL`
- unknown statements และ unsupported dialect/version syntax
- `DEFINER`, SQL SECURITY ที่ไม่อยู่ใน policy, account/GRANT operations
- filesystem/network/plugin/UDF operations เช่น `INFILE`, `OUTFILE`, `LOAD_FILE`, `SOURCE`, plugin/SONAME และ external engine/connection options
- global/persistent/session changes ที่ไม่อยู่ใน exact session-directive allowlist
- `DROP DATABASE`, cross-schema DDL/DML และ statement ที่เปลี่ยน active database
- executable comments ที่ไม่ตรง exact allowlist ของ dump format

Stored objects ไม่ได้ผ่านเพียงเพราะโปรแกรมเป็นผู้สร้าง dump: view, trigger, procedure, function และ event ต้องมี typed validator เฉพาะ family ซึ่งพิสูจน์ body scope และ forbidden constructs ได้ ถ้ายังไม่มี validator หรือ body ซับซ้อนเกิน policy ให้ทั้ง artifact `unsupported/ineligible`; ห้ามตัด object ออกแล้วรายงาน restore สำเร็จ

### 3.4 Execution representation

Executor serialize SQL จาก typed fields ที่ validate แล้ว หรือส่ง original statement bytes เฉพาะเมื่อ statement type รับรองว่าไม่มี field ที่ต้อง map. Metadata statements ไม่เคยถูก execute. `DELIMITER` เป็น client-side parse metadata และไม่ถูกส่งให้ server

ก่อน statement แรก executor ต้องเทียบ artifact bytes/SHA-256 กับ manifest และ plan อีกครั้งภายใต้ shared lock เพื่อปิด TOCTOU หากไฟล์เปลี่ยน plan ต้องถูกทิ้งและ job เป็น `failed: artifact_changed_before_execution`

## 4. Components ที่ต้องมี

| Component | หน้าที่และ boundary |
|---|---|
| `RestorePlanCompiler` | stream artifact เป็น immutable typed plan; ไม่มี DB access |
| `TypedRestoreStatement` family | เก็บเฉพาะข้อมูลที่ validate แล้ว; ไม่มี raw execute escape hatch |
| `RestoreIntegrationPolicy` | exact source allowlist, protected/system names, run/target naming rules |
| `AuthorizedSourceCatalog` | ตรวจ existence/access เฉพาะ source สี่ชื่อด้วย exact predicates; ไม่ใช้ unrestricted `SHOW DATABASES` |
| `IntegrationBackupOperation` | adapter สำหรับ live integration ที่ใช้ Phase 2 runner และ `BackupService` เดิม แต่รับฐานจาก `AuthorizedSourceCatalog` เท่านั้น |
| `ServerIdentityProbe` | อ่าน identity/version/capability ที่จำเป็นจาก connection ปัจจุบันเท่านั้น |
| `PrivilegeAttestor` | ตรวจ current account และ exact effective privileges แยก backup/runner ตาม product/version |
| `LiveRestoreAttestationGate` | รวม evidence; เปิดได้จาก evidence object ครบชุดเท่านั้น ไม่อ่าน config flag |
| `SharedDatabaseOperationLock` | global host-local lock ที่ Backup และ Restore ใช้ path เดียวกัน |
| `RestoreIntegrationJobStore` | append-only versioned JSONL สำหรับ lifecycle ใหม่ แยกจาก Phase 3 jobs |
| `RestoreIntegrationJobAccess` | Auth/session ownership, active-job และ duplicate-submit protection |
| `OwnershipJournal` | append-only run/target evidence และ cleanup decisions ภายใต้ private storage |
| `TargetDatabaseProvisioner` | exact absence check, create target, ownership marker, select target |
| `TypedRestoreExecutor` | execute typed statements ตามลำดับบน target-pinned connectionเดียว |
| `PostRestoreValidator` | schema/object/data comparison โดยไม่ expose data values |
| `CleanupCoordinator` | verify journal + server + marker + protected-name policy ก่อน exact DROP |
| `RestoreIntegrationRunner` | orchestration, locks, state transitions, interruption/finally cleanup |

แนะนำ namespace `BackupApp\Restore\Integration` และ private directories `storage/restore-integration-jobs/`, `storage/restore-ownership/`, `storage/database-operations/` ซึ่งต้องถูก storage policy และ Apache denial เดิมครอบคลุม

`ServerIdentityProbe` ต้องให้ stable server-native identity ตาม adapter เช่น MySQL server UUID หรือ MariaDB `server_uid` ในเวอร์ชันที่รองรับ พร้อม product/version, connection endpoint, server ID, hostname และ port เพื่อช่วยตรวจ mismatch Endpoint tuple หรือ `server_id` เพียงอย่างเดียวไม่ถือว่า unique หาก server version ไม่มี stable identity ที่ adapter รับรอง ให้ gate เป็น `blocked: stable_server_identity_unavailable`; ห้ามลดเหลือการเชื่อ host/portอย่างเดียว Ownership markerช่วยยืนยัน target/runแต่ไม่ใช้แทน initial server attestation

Phase 2 `DatabaseRepository` ปัจจุบันใช้ unrestricted `SHOW DATABASES` จึงไม่ใช้ใน live integration path นี้ `AuthorizedSourceCatalog` query `information_schema.SCHEMATA` ด้วย exact allowlist predicatesสี่ชื่อและคืนได้เฉพาะชื่อที่ policyกำหนด `IntegrationBackupOperation` ยังคงเรียก `BackupJobRunner` และ `BackupService::backupDatabase()` จริงแบบ sequential ดังนั้นผลยังเป็น artifact/provenanceของแอป ไม่ใช่ dumpจากเครื่องมืออื่น การเพิ่ม adapterนี้เป็นการปิด discovery boundaryที่พิสูจน์แล้ว ไม่ใช่การ rewrite Backup Engine; behaviorทั่วไปของ Phase 2อยู่นอก integration pathและไม่เปลี่ยน

## 5. Credential และ Privilege Design

Credentials ถูก provision นอกแอปโดยผู้ดูแลระบบหลังทราบ product/version จริง แอปไม่สร้าง user, ไม่ GRANT/REVOKE และไม่รับ root เป็น fallback Password อยู่ใน authenticated PHP session/process memory เท่านั้น ไม่ลง job, journal, log หรือ browser response

### 5.1 Backup reader

Account นี้ต้องเห็นและอ่านได้เฉพาะ source allowlist สี่ชื่อ ไม่มี global `SHOW DATABASES`, global object privileges, write DML, DDL, account หรือ file privileges การตรวจ privilege ต้องครอบคลุมความสามารถที่ `BackupService` ใช้จริง เช่น table `SELECT`, metadata/`SHOW CREATE`, views, triggers, routines และ events

ชื่อ privilege แตกต่างระหว่าง MySQL/MariaDB และเวอร์ชัน เช่นความสามารถอ่าน routine definition ดังนั้น design ไม่กำหนด GRANT template แบบเหมารวม `PrivilegeAttestor` ต้อง:

1. อ่าน product/version/capabilities จาก server ปัจจุบัน
2. อ่าน grants ของ `CURRENT_USER()` เท่านั้น
3. คำนวณ effective privileges ต่อ exact source name
4. reject global write/admin/file privileges และ privilege บน protected/unauthorized schemas
5. ทำ capability probes แบบ read-only ต่อ exact authorized sourcesโดยไม่ enumerate schemas

Database-level grantsใช้ pattern semanticsในหลายเวอร์ชัน: `_` และ `%` อาจเป็น wildcard แม้อยู่ใน quoted database token ดังนั้น attestorต้อง parse semanticsของ grantรวมการ escape, MySQL `partial_revokes`, active/default roles และ MariaDB roles/`PUBLIC`; ห้ามเทียบ string grantหรือเห็น prefixแล้วสรุปว่า exact Target/source namesที่มี underscoreต้องมีหลักฐานว่า grant pattern matchชื่อที่อนุญาตเท่านั้นและไม่ matchชื่ออื่น

### 5.2 Restore test runner

Account นี้ต้องไม่มี privilege ใดบน source ทั้งสี่, `jhcisdb`, system schemas หรือฐานอื่น Target grants ต้องผูกกับ exact random database names ของ run ที่ operator provision ไว้ล่วงหน้า แม้ฐานยังไม่ถูกสร้าง และให้เฉพาะ DDL/DML/object privileges ที่ typed plan ต้องใช้จริง รวมสิทธิ์ exact `CREATE`/`DROP` ที่ server version รองรับสำหรับฐานเป้าหมายนั้น

Application prefix ไม่ใช่หลักฐาน privilege isolation. ก่อน create gate ต้องพิสูจน์ทั้ง positive grants บน target exact names และ negative isolation ต่อ source/protected names การ parse grants ที่ไม่เข้าใจ, wildcard ที่ครอบคลุมกว้างเกิน target, global privileges หรือ role activation ที่พิสูจน์ไม่ได้ต้อง `blocked`

เพราะ random targets ทราบหลังสร้าง run plan การเตรียม live test ต้องเป็นสองช่วง: แอปสร้าง **non-executing run proposal** พร้อม exact names ก่อน จากนั้น DBA provision exact grants นอกแอป แล้วผู้ใช้เริ่ม attestation/execution ใหม่ ไม่มี credential ชุดเดียวใช้ทั้ง backup และ restore

ทั้งสอง connectionsต้องให้ server identity fingerprintเดียวกันก่อนสร้าง backup/target หากไม่ตรงให้ block Backup readerถูกใช้สำหรับ source backupและ exact source-existence verificationเท่านั้น Restore runnerถูกใช้สำหรับ target create/execute/validate/dropเท่านั้น ห้ามสลับ credential roles

## 6. Test Run ID, Target Naming และ Collision

Run ID คือ `bin2hex(random_bytes(16))` หรือ lowercase hexadecimal 32 ตัว มี entropy 128 บิตและไม่ใช้ timestamp/prefix เป็น ownership proof Target name เป็น:

```text
restore_test_autoprintdb_<32hex>
restore_test_drugmap_db_<32hex>
restore_test_drug_stock_check_<32hex>
restore_test_prepack_labels_<32hex>
```

ทุกชื่อผ่าน regex `^[a-z0-9_]+$`, ยาวไม่เกิน MySQL/MariaDB database identifier limit 64 ตัว และไม่เท่ากับ source/protected/system names แบบ case-insensitive ภายใต้ server `lower_case_table_names` policy ตัวอย่างทั้งสี่มีความยาว 57, 56, 62 และ 60 ตัวตามลำดับเมื่อใช้ run ID 32 hex ชื่อที่ยาวเกิน limit ห้าม truncate แบบเงียบ; policy ต้อง reject

Collision check ใช้ parameterized exact-name lookup ต่อ `information_schema.SCHEMATA` เฉพาะ target name ห้าม unrestricted `SHOW DATABASES`. หากชื่อมีอยู่ก่อน run ให้บันทึก `collision_before_create`, ห้ามใช้/ลบ และสร้าง run proposal ใหม่ทั้งชื่อ ไม่ reuse ชื่อเดิม

## 7. Ownership Journal

แต่ละ run มี append-only JSONL ที่ filename มาจาก validated run ID ทุก event เป็น full or chained snapshot พร้อม `revision`, previous-record hash, timestamp และ file lock ข้อมูลขั้นต่ำ:

- schema version, run ID, integration job ID, restore job/preflight linkage
- exact source และ target database name
- server identity fingerprint และ connection endpoint identity
- target absence evidence ก่อน create
- creation timestamp, creation result และ exact creation connection identity
- random ownership nonce และ ownership-marker identity
- artifact relative path, SHA-256, provenance job ID
- restore state, validation report digest
- cleanup authorization decision/evidence
- cleanup attempt/result และ post-cleanup absence evidence

หลัง `CREATE DATABASE` สำเร็จ executor ต้องสร้าง ownership marker ภายใน target ด้วยชื่อสุ่มที่ผูกกับ run และค่า nonce 256 บิต Journal บันทึก hash ของ nonce ไม่บันทึกค่าใน log/UI ก่อน restore parser ต้องพิสูจน์ว่า artifact ไม่มี object ชื่อเดียวกับ marker

Journal hash chain ตรวจ corruption/partial write แต่ไม่ถูกถือเป็นหลักฐานเดียวสำหรับ DROP Cleanup ต้องเทียบ journal, server fingerprint, exact target, marker name/nonce, job ownership และ protected-name policy พร้อมกัน ถ้าข้อใดอ่านไม่ได้หรือไม่ตรง ให้ `cleanup_blocked_ownership_unproven`

## 8. Shared Database Operation Lock

สร้าง lock จริงหนึ่งตัวที่ `storage/database-operations/global.lock` ผ่าน `DatabaseOperationLockContract` ทั้ง Backup และ Restore mutation path ต้อง acquire แบบ exclusive ทำให้ concurrency รวมเท่ากับหนึ่ง

ลำดับ lock คงที่เพื่อป้องกัน deadlock:

```text
per-job lock -> shared database-operation lock -> job/journal file lock เฉพาะตอน append
```

Restore ถือ shared lock ตั้งแต่ final attestation/artifact revalidation ก่อน create จน execution, comparison และ cleanup attempt เสร็จ ไม่ปล่อย lockระหว่าง create/drop Backup ถือ lockตลอด ordered database loop เหมือน Phase 2 เดิม

ผลกระทบต่อ Phase 2 ที่ Implementation ต้องทำแบบ migration จำกัดจุด: `BackupExecutionGuard::acquireGlobal()` delegate ไป shared lock path ใหม่ ส่วน per-job lock และ runner behavior คงเดิม การ deploy ต้องตรวจว่าไม่มี active backup owner บน lock path เดิมก่อน switch; ห้ามให้ old backup executor และ new restore executorทำงานพร้อมกันระหว่าง migration Tests ต้องพิสูจน์ backup-vs-backup, restore-vs-restore และ backup-vs-restore serialization

Phase 3 read-only preflight ไม่จำเป็นต้องถือ shared DB lockเพราะไม่มี DB operation แต่ execution ต้อง rerun preflight evidence ภายใต้ shared lock

## 9. Restore Job Lifecycle

ใช้ store ใหม่และ schema version ใหม่ แยกจาก Phase 3 preflight history:

```text
queued -> validating -> running -> succeeded
                    |          -> failed
                    |          -> interrupted
                    -> blocked
                    -> failed
                    -> interrupted
queued              -> interrupted
```

ความหมาย:

- `queued`: job ถูกสร้างและผูก session/context แล้ว ยังไม่มี execution claim
- `validating`: ถือ per-job lock และกำลังตรวจ artifact/parser/environment/privileges/target absence
- `running`: target ถูกสร้างพร้อม ownership evidence และเริ่ม SQL execution แล้ว
- `succeeded`: SQL ทุก statement สำเร็จและ mandatory post-restore validation ผ่านจริง
- `failed`: validation, execution หรือ comparison ล้มเหลวอย่างมีหลักฐาน
- `blocked`: safety evidence ไม่ครบ/unsupported แต่ไม่มี SQL execution และไม่มี target ถูกสร้าง
- `interrupted`: claimed process หายหรือถูกหยุด; ไม่มี auto resume/retry

ก่อนเปลี่ยน `validating -> running` ต้อง append creation evidence ลง journalสำเร็จ หลัง create แต่ก่อน append evidence ล้มเหลวต้องพยายามสร้าง/ตรวจ markerและบันทึก emergency evidence; หาก ownership ยังพิสูจน์ไม่ได้ ห้าม DROP อัตโนมัติและรายงาน target ค้าง

`succeeded` หมายถึง restore+comparison สำเร็จ ไม่ได้หมายถึง cleanup สำเร็จ Cleanup มีสถานะแยกใน journal เพื่อไม่เปลี่ยนผล validationย้อนหลัง Integration run ถือว่าเสร็จสมบูรณ์ต่อผู้ใช้เมื่อ job terminal และ cleanup terminal (`dropped` หรือ `cleanup_blocked/failed` ที่รายงานชัด)

ไม่มี auto retry Browser refresh/status เป็น GET/read-only และไม่สร้าง/execute/cleanup job API mutating ทุกตัวต้องใช้ Auth, CSRF, session ownership, connection-context binding และ idempotency/active-job check

เมื่อ browser disconnect executorใช้ resilience policy แบบ Phase 2 ได้ แต่ไม่ถือว่า durable หาก processตาย status recovery acquire per-job lock เพื่อพิสูจน์ orphanแล้ว mark `interrupted`; recovery ห้าม auto execute และห้าม auto DROP ผู้ใช้ต้องเรียก explicit cleanup actionที่ผ่าน ownership verificationใหม่

## 10. Execution, Failure และ Cleanup Safety

Target database DDL auto-commitและมองเห็นได้ทันที จึงไม่สามารถทำ atomic publish แบบไฟล์ backup ได้ คำว่า “ไม่ publish ฐานไม่สมบูรณ์” ใน design นี้หมายถึง target ใช้ชื่อ disposable ที่ไม่ถูกเสนอเป็น production, ไม่ตั้ง `succeeded`, และเข้าสู่ cleanup path; ห้ามอ้างว่าฐานที่สร้างแล้วไม่เคยมองเห็น

Executor ใช้ connection restore-runner หนึ่งตัว เลือก exact targetครั้งเดียว และก่อนทุก typed statementตรวจว่า current database/server identity ยังตรง evidence หาก connection reconnect หรือ active databaseเปลี่ยนให้หยุดทันที Statements execute sequential เท่านั้น ไม่มี multi-query และไม่มี parallel object execution

Cleanup ทำงานใน `finally` ขณะที่ processยังอยู่ ไม่ว่าผล execution/comparisonสำเร็จหรือล้มเหลว แต่ก่อน exact `DROP DATABASE <target>` ต้องผ่านทุกข้อ:

1. target ไม่อยู่ใน pre-run existence evidence
2. current run สร้าง target สำเร็จ
3. exact name ตรง journal/job/trusted target
4. current server fingerprint ตรง creation evidence
5. marker table/name/nonce ตรง journal
6. job/session authorizationและ cleanup actionถูกต้อง
7. targetไม่ใช่ source/protected/system/unauthorized database
8. shared operation lockยังถูกถือ

ห้ามใช้ `DROP DATABASE IF EXISTS`, wildcard, prefix selection, loop over discovered schemas หรือ force cleanup ถ้า targetหายไปเอง ให้บันทึก mismatchและตรวจ absenceตามจริง ไม่แสร้งว่า executorเป็นผู้ drop

หลัง drop restore runnerต้อง exact-name queryยืนยัน target absence จากนั้นปิด/แยก runner connection และใช้ backup readerทำ exact-name existence checksเฉพาะ source allowlistสี่ชื่อ โดยไม่อ่าน tables/data/schema ของ source ห้ามให้ restore runnerเข้าถึง source และห้ามตรวจ `jhcisdb` แม้เพื่อยืนยันว่าอยู่ หลัง cleanup failureให้บันทึกชื่อ exact targetและเหตุผลที่ปลอดภัยต่อการแสดง ไม่มีการลบฐานอื่นแทน

## 11. Post-Restore Validation

Validation แบ่ง mandatory, conditional และ NOT VERIFIED:

### 11.1 Mandatory structural comparison

- tables และ columns: name/order/type/null/default/generated/extra
- primary/foreign keys และ referential actions
- indexes: name, uniqueness, order, prefix length, visibilityเมื่อรองรับ
- table engine, charset, collation
- AUTO_INCREMENT เทียบตาม manifest/typed plan โดยรับรู้ว่าค่าถัดไปอาจ normalized ตาม server
- exact object inventory เทียบ manifest stats และ typed plan

### 11.2 Data comparison

Expected side มาจาก typed `INSERT` values ใน artifact ไม่ใช่ live source ณ เวลาหลัง restore Actual sideอ่านจาก targetเท่านั้น Canonicalizerใช้ column metadataและ length-prefixed binary-safe representation แยก NULL จาก empty string และไม่ logค่าจริง

สำหรับตารางมี primary/unique keyให้ streamตาม deterministic keyและเทียบ row digests/count สำหรับตารางไม่มี unique keyให้เทียบ multiset digestพร้อม duplicate countsโดย bounded external chunks/temporary filesใต้ private storage ห้ามใช้ aggregate digestเดียวที่สูญเสีย multiplicity Hash algorithmขั้นต่ำ SHA-256

ประเภทข้อมูลที่ canonicalizeอย่างพิสูจน์ไม่ได้ เช่น version-specific spatial/bit/time-zone semantics ต้องรายงาน objectนั้น `NOT VERIFIED` และถ้าเป็น mandatory fidelityของ artifactให้ jobไม่เป็น `succeeded`

### 11.3 Views และ stored objects

Views, triggers, procedures, functions และ eventsเปรียบเทียบจาก typed canonical definitionกับ `SHOW CREATE` ของ target โดย normalizeเฉพาะ server-generated attributesที่ระบุใน version adapter ห้ามลบหรือ ignore bodyเพื่อให้ผ่าน ถ้า parser/executorไม่รองรับ object familyนั้น artifactต้องถูก blockก่อน create ไม่ใช่ restoreบางส่วน

### 11.4 Snapshot consistency

BackupService ปัจจุบันอ่านตารางตามลำดับโดยไม่มี transaction snapshotข้ามทุกตาราง ดังนั้น designนี้พิสูจน์ได้ว่า **targetตรงกับ artifact snapshot** แต่ยังพิสูจน์ไม่ได้ว่า artifactเป็น point-in-time snapshotเดียวของ live source โดยเฉพาะฐานที่เปลี่ยนระหว่าง backupหรือมี non-transactional tables

ห้ามเปรียบเทียบ targetกับ live sourceภายหลังแล้วใช้ความต่างตัดสิน restoreโดยตรง รายงานต้องแยก:

- `artifact_to_target`: PASS/FAIL จาก typed artifact evidence
- `source_to_artifact_snapshot_consistency`: NOT VERIFIED เว้นแต่ future changeมีหลักฐาน consistent snapshotที่รองรับ engineทั้งหมด

การเปลี่ยน Backup Engineเพื่อเพิ่ม consistent snapshotต้องเป็น design/change approval แยก ไม่รวมใน extensionนี้

## 12. Change-Based Test Matrix

| Area | Required tests | Test boundary |
|---|---|---|
| Target mapping | source CREATE/USE become metadata; trusted target generated; originals never executed | pure fixtures/fake executor |
| Lexer/typed parser | quotes/comments/delimiters, executable comments, qualified refs, dynamic/unknown/dialect/DEFINER/file/network rejects | adversarial SQL fixtures |
| Artifact trust | marker/manifest/hash/provenance repeat check and TOCTOU rejection | temporary files/job fixtures |
| Naming | 32-hex run ID, <=64 chars, case policy, collision/new-run behavior, protected names | pure policy tests |
| Source discovery | exact allowlist predicates only; current unrestricted `SHOW DATABASES` path is never called by integration operation | recording fake repository/connection |
| Privilege isolation | exact source read privileges, exact target rights, global/wildcard/role ambiguity rejection, version adapters, both roles pinned to one server identity | captured grant fixtures + fake adapter |
| Server identity | mismatch/reconnect/version/capability rejection | fake connections |
| Ownership journal | append/recovery/hash chain, partial tail, marker nonce, mismatched run/server/name rejection | temporary private files/fake DB |
| Job lifecycle | every allowed transition; illegal jump/duplicate/resume/retry rejection; succeeded only after comparison | store/runner fixtures |
| Shared lock | backup-vs-restore, restore-vs-restore, duplicate job and lock-order behavior | process/lock fixtures on local filesystem |
| Safe execution | sequential only, no multi-query/raw SQL escape, target pinned, stop on first statement failure | recording DB adapter |
| Post-validation | schemas/keys/indexes/counts/content multiset/stored objects, unsupported type NOT VERIFIED | deterministic fixtures/fake metadata |
| Cleanup | exact ownership success; pre-existing/missing marker/server mismatch/protected name/no privilege rejection; verify absence | fake DB first, disposable DB only after approval |
| Interrupted recovery | orphan detection, no auto resume/retry/drop, explicit verified cleanup | process/job fixtures |
| HTTP security | Auth, CSRF, session ownership, safe error/log redaction, refresh GET creates nothing | isolated sessions/HTTP smoke |

Full Phase 1–4 regressionไม่จำเป็น Testsเดิม rerunเฉพาะจุดที่ shared lock migration, artifact validator, routes/sessionหรือ Backup guardได้รับผลกระทบ Live MySQL/MariaDB testsเป็นขั้นสุดท้ายแยกต่างหากและต้องมี approval, dedicated credentials, exact grants และ disposable target evidenceก่อน

## 13. Error, Audit และ Information Disclosure Policy

Job/browser responsesใช้ stable codes เช่น `artifact_changed`, `unsupported_sql`, `privilege_scope_unproven`, `server_identity_mismatch`, `target_exists`, `ownership_unproven`, `statement_failed`, `comparison_failed`, `cleanup_failed` โดยไม่เผย SQL text, values, absolute paths, grants, usernames, passwords, nonce หรือ raw exception

Technical logผ่าน redacting loggerและบันทึกเฉพาะ statement type/index/object identifierที่ปลอดภัย ไม่บันทึก INSERT valuesหรือ stored-program body Ownership journalอยู่ private storageและไม่ถูก downloadผ่าน application UI

หาก logger/journal persistenceล้มเหลวก่อน createให้ block หากล้มเหลวหลัง createให้หยุด executionและเข้าสู่ cleanupเฉพาะเมื่อ ownership marker+journal evidenceที่มีอยู่ยังครบ มิฉะนั้นห้าม dropและรายงาน manual review target exact name

## 14. Implementation Plan Proposal

ลำดับเสนอสำหรับรอบ Implementation หลัง designได้รับอนุมัติ:

1. **Pure structural layer** — แยก lexer safety core, typed statement model และ plan compiler พร้อม adversarial fixtures; ยังไม่มี DB code
2. **Policy and attestation layer** — target/run policy, exact authorized-source catalog, server identity model และ versioned privilege attestorผ่าน fake/captured fixtures
3. **Ownership and cleanup model** — append-only journal, marker protocol, exact-name cleanup decision engineด้วย fake adapter
4. **Shared lock migration** — implement shared lockและย้ายเฉพาะ Phase 2 global lock พร้อม focused backup regressionและdeployment quiescence check
5. **Integration job subsystem** — versioned store/access/state machine, duplicate/interruption behavior; ยังไม่ execute SQL
6. **DB adapter and executor** — target provisionerกับ sequential typed executorหลังทุก fake safety testผ่าน; no live DB
7. **Post-restore validator** — structural/data/object comparisonและ truthful NOT VERIFIED semantics
8. **Authenticated API/minimal UI** — manual proposal/execute/status/cleanup actions, no auto execution, no percentage
9. **Mock/fixture verification** — change-based suite, PHP/JS lint, security/log/source scan และ independent architectural review
10. **Live readiness review** — ตรวจ actual Laragon version, dedicated users/grants, exact target proposalsและtest procedure; หยุดขอ approval ใหม่ก่อน connection/create/restore/drop
11. **Controlled live integration** — ทำภายใต้ separate explicit authorizationเท่านั้น แล้วบันทึกผล/cleanupใน `TEST_BASELINE.md`

แต่ละ milestoneต้องมี RED -> GREEN focused tests และ review gate ห้ามรวม live testกับ implementationโดยอัตโนมัติ ไม่มี commit/push/tag/releaseในขอบเขตคำสั่งปัจจุบัน

## 15. Architectural Risks และ Blockers

1. **Stored-program safety** — arbitrary routine/trigger/event bodiesเป็นภาษาโปรแกรมเต็มรูปแบบ Parserอาจต้อง reject backupจริงบางไฟล์อย่างถูกต้องจนกว่าจะมี grammarที่พิสูจน์ scopeได้
2. **Source snapshot consistency** — engineปัจจุบันไม่รับประกัน point-in-time snapshotข้ามตาราง จึงห้าม claim live source equality; artifact-to-targetเท่านั้นที่ออกแบบให้พิสูจน์ได้
3. **Version-specific privileges** — MySQL/MariaDB privilege names, database-pattern wildcards, roles/PUBLIC, routine visibilityและDDL behaviorต่างกัน ต้องมี actual-version adapterและnegative testsก่อน live readiness
4. **DDL non-atomicity** — partial targetอาจเหลือหลัง process crash Cleanupไม่สามารถ auto-runหลัง OS/Apache termination และต้องใช้ explicit ownership-verified recovery
5. **Shared-lock deployment race** — เปลี่ยน lock pathขณะมี Phase 2 executorเก่าอาจทำให้สอง operationชนกัน ต้อง deployตอน quiescentและตรวจ old/new locks
6. **Filesystem trust** — `flock` และ private journalสมมติ local Windows filesystem; network/shared storageไม่อยู่ในขอบเขต
7. **Credential provisioning** — แอปไม่ควรถือ admin privilege การสร้าง exact per-run grantsต้องทำโดย DBAนอกแอปและอาจเป็น operational bottleneck
8. **Large datasets** — statement limit, long HTTP executionและcomparison temp filesอาจทำให้ run blocked/interrupted ต้องมี bounded resource policyและห้ามเพิ่ม background schedulerโดยปริยาย
9. **Existing database discovery path** — Phase 2 repositoryใช้ unrestricted `SHOW DATABASES`; live integrationต้องใช้ exact `AuthorizedSourceCatalog` adapter มิฉะนั้นไม่ผ่าน safety boundary แม้ accountดูเหมือนจำกัดสิทธิ์

ไม่มี blocker ที่ทำให้สถาปัตยกรรมนี้เป็นไปไม่ได้ แต่ **Implementation Readiness ยังไม่ผ่าน** จนกว่าจะอนุมัติ design, สร้าง detailed implementation plan และกำหนด policyสำหรับ stored objects/version adaptersอย่างทดสอบได้ Live Readiness ยังต้องรอ actual server/versionและ dedicated credentials ซึ่งไม่ได้รับอนุญาตให้ตรวจในรอบนี้

## 16. Implementation Readiness และ Approval Gates

สถานะปัจจุบัน:

| Gate | Status |
|---|---|
| Approved architectural direction | PASS |
| Written design specification | READY FOR REVIEW |
| Detailed implementation plan | NOT CREATED — รอ design approval |
| Parser/typed statement implementation | NOT STARTED |
| Shared lock migration | NOT STARTED |
| Dedicated credentials/grants | NOT VERIFIED |
| Actual Laragon MySQL/MariaDB version | NOT VERIFIED |
| Live backup/restore/comparison/cleanup | NOT AUTHORIZED / NOT VERIFIED |

ขั้นถัดไปหลังผู้ใช้อนุมัติเอกสารนี้คือจัดทำ detailed implementation plan เท่านั้น การอนุมัติ designไม่ถือเป็นการอนุญาตให้เชื่อมต่อ MySQL, สร้าง user/database, execute SQL, restore, compare live dataหรือ cleanup Live operationsต้องมี authorizationใหม่หลัง implementationและmock verificationเสร็จ

## 17. Rollback Boundary

Rollbackในอนาคตต้องลบเฉพาะ namespace/endpoint/UI/storage configurationของ Restore Integration และคืน `BackupExecutionGuard` ไปใช้ behaviorเดิมเมื่อยืนยันว่าไม่มี active operation Phase 1–4 artifacts/jobs/download/preflightและ backup filesต้องไม่ถูกลบหรือ rewrite Database cleanupไม่ใช่ code rollback; targetที่มี ownershipไม่ครบต้องคงไว้และรายงานเพื่อ manual review ห้ามเดาจากชื่อ

## 18. Primary Technical References for Version Adapters

- MySQL GRANT/database wildcard and privilege-level semantics: <https://dev.mysql.com/doc/refman/8.4/en/grant.html>
- MySQL request verification and escaped database patterns: <https://dev.mysql.com/doc/refman/8.4/en/request-access.html>
- MySQL identifier length limits: <https://dev.mysql.com/doc/refman/8.4/en/identifier-length.html>
- MariaDB GRANT and roles/PUBLIC behavior: <https://mariadb.com/docs/server/reference/sql-statements/account-management-sql-statements/grant>
- MariaDB SHOW GRANTS: <https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/show/show-grants>
- MariaDB server variables, including version-dependent `server_uid`: <https://mariadb.com/docs/server/server-management/variables-and-modes/server-system-variables>

Referencesเหล่านี้ใช้กำหนด adapter test cases ไม่ใช้แทนการตรวจ actual product/version/grantsใน live readiness review
