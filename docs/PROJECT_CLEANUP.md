# Project Cleanup Record — 2026-10-19

## Plan and safety boundary

- [x] Trace current working-tree entrypoints and retain the referenced graph.
- [x] Enumerate exact DELETE candidates and verify no protected data/reparse paths.
- [x] Delete obsolete source/tests/docs/generated artifacts; remove dead registrations/config.
- [x] Verify autoload, metadata/provenance integration, isolated routes and affected tests.
- [x] Reconcile protected-file hashes, inventory and final results.

This is the authorized one-run cleanup, not a new architecture phase. No commits, database/network operations, real Export/Import, Windows/Laragon mutations or storage migration. User's instruction to finish inline overrides skill approval/commit/worktree handoffs: the dirty current working tree is the required authority. This record serves as plan, ledger and exact deletion inventory; no extra phase runbooks.

## Active graph / KEEP

`index.php` / root rewrite -> `public/index.php` -> bootstrap/config/runtime paths/session/loopback/CSRF -> Router -> BackupController + SettingsController -> Dashboard, Export, Import, History, Settings / shared layout/navigation/assets.

`public/job.php` -> BackupJobAccess/Store/Runner/ExecutionGuard -> MysqliBackupOperation -> BackupService -> MysqldumpEngine -> pinned MysqldumpCommand/Process + TemporaryMysqlCredentials -> BackupArtifactPublisher. FileDatabaseOperationLock is shared with Import. DumpEngine/BackupOperation interfaces are used by production and focused fakes; not duplicate engines.

`public/import-job.php` -> ImportAccess/JobStore/ArtifactVerifier/Policy/Runner -> MysqlImportClient + MysqliImportDatabase -> EffectiveSchemaComparison. ImportResultPresentation reads bound acceptance evidence; never changes historical state or claims snapshot equality.

`public/download.php` -> loopback/session token -> BackupPathPolicy / BackupService + DownloadAuthorization (real artifact hash/marker).

KEEP `app/Restore/BackupProvenanceIndex.php`: actual ImportArtifactVerifier and History job origin lookup. KEEP `app/Restore/RestoreArtifactValidator.php`: legacy History metadata inspection only; remove unused custom preflight method, not old artifacts. Their legacy names do not imply SQL execution. KEEP `tools/provision-local-import.php`: active explicit plan/authorized provisioning tool; never executed by this cleanup. KEEP read-only acceptance inspector/helper and comparison tests to explain historical PARTIAL evidence. One-off password reset wrapper/receipt tool is retired, not needed for daily workflow.

KEEP existing runtime path keys used by Import (`restore-jobs/local-import`, `restore-integration-ownership/local-import`) and compatibility path contracts without renaming/migration. All protected storage paths and config/local.php stay untouched; local credentials are not read into reports. No new dependencies.

## DELETE evidence / categories

All custom SQL lexer/plan/executor, offline Restore orchestration/privilege/authorization/secret framework and deployment classes are unreachable from the above graph. The two retired HTTP endpoints have an unconditional exit before dead prototype bodies; Router already excludes their pages. Remove their files and rewrite registrations. Removed tests/tooling specifically depend on removed prototypes, native projects, signing or dedicated deployment. Build staging and native bin/obj/artifacts are copies/generated outputs, not application runtime; do not install them. Remove abandoned phase/design/runbooks; TEST_BASELINE and current acceptance report retain historical evidence. Empty directories may be removed only with nonrecursive rmdir after exact file deletion. `.git`, `.agents`, `.superpowers` are not cleanup targets.

## REVIEW resolved

- Legacy artifact validator/provenance: KEEP shared metadata utility, simplify only unreachable preflight.
- Historical SQL and persisted jobs/ownership/private files: KEEP protected; no wildcard cleanup.
- Production runtime-path validation: KEEP for existing configuration compatibility; dedicated deployment tooling is not required.
- Existing unrelated dirty files: retained unless explicitly listed below as proven obsolete in this authorized cleanup.

## Exact deleted-file inventory (960 actual deletions, reconciled)

- `app/Restore/DatabaseOperationLockContract.php` (418 bytes)
- `app/Restore/RestoreExecutionGuard.php` (1701 bytes)
- `app/Restore/RestoreJobAccess.php` (3163 bytes)
- `app/Restore/RestoreJobRequestException.php` (362 bytes)
- `app/Restore/RestoreJobStore.php` (7490 bytes)
- `app/Restore/RestorePreflightRunner.php` (6696 bytes)
- `app/Restore/RestoreSafetyGate.php` (602 bytes)
- `app/Restore/RestoreTargetValidator.php` (842 bytes)
- `app/Restore/SqlStructureValidator.php` (220 bytes)
- `app/Restore/StructuralSqlParser.php` (15434 bytes)
- `app/Restore/Sql/LexedStatement.php` (573 bytes)
- `app/Restore/Sql/SqlFileByteReader.php` (1622 bytes)
- `app/Restore/Sql/SqlStatementLexer.php` (8888 bytes)
- `app/Restore/Integration/Validation/PostRestoreValidator.php` (7056 bytes)
- `app/Restore/Integration/Validation/RowCanonicalizer.php` (1835 bytes)
- `app/Restore/Integration/Validation/RowEvidenceAccumulator.php` (5505 bytes)
- `app/Restore/Integration/Validation/RowMultisetComparator.php` (1296 bytes)
- `app/Restore/Integration/Validation/SchemaComparator.php` (1195 bytes)
- `app/Restore/Integration/Validation/StoredObjectComparator.php` (548 bytes)
- `app/Restore/Integration/Validation/TableDataEvidence.php` (813 bytes)
- `app/Restore/Integration/Validation/TypedSqlValue.php` (1007 bytes)
- `app/Restore/Integration/Validation/ValidationReport.php` (799 bytes)
- `app/Restore/Integration/Sql/AbstractExecutableStatement.php` (541 bytes)
- `app/Restore/Integration/Sql/CreateTableEvidenceParser.php` (5885 bytes)
- `app/Restore/Integration/Sql/ExecutableRestoreStatement.php` (217 bytes)
- `app/Restore/Integration/Sql/ExecutableStatementPolicy.php` (2978 bytes)
- `app/Restore/Integration/Sql/FileStatementPayload.php` (466 bytes)
- `app/Restore/Integration/Sql/InlineStatementPayload.php` (269 bytes)
- `app/Restore/Integration/Sql/InsertRowsParser.php` (4456 bytes)
- `app/Restore/Integration/Sql/ParsedInsertRows.php` (665 bytes)
- `app/Restore/Integration/Sql/RestorePlanCompiler.php` (9781 bytes)
- `app/Restore/Integration/Sql/RestorePlanItem.php` (149 bytes)
- `app/Restore/Integration/Sql/RestorePlan.php` (1620 bytes)
- `app/Restore/Integration/Sql/RestoreStatementKind.php` (463 bytes)
- `app/Restore/Integration/Sql/StatementPayload.php` (146 bytes)
- `app/Restore/Integration/Sql/StatementSpoolLease.php` (1480 bytes)
- `app/Restore/Integration/Sql/StatementSpoolWriter.php` (1877 bytes)
- `app/Restore/Integration/Sql/ValidatedExecutableStatement.php` (808 bytes)
- `app/Restore/Integration/Sql/Statement/AlterTable.php` (375 bytes)
- `app/Restore/Integration/Sql/Statement/CreatePlaceholderView.php` (398 bytes)
- `app/Restore/Integration/Sql/Statement/CreateTable.php` (619 bytes)
- `app/Restore/Integration/Sql/Statement/DropObject.php` (375 bytes)
- `app/Restore/Integration/Sql/Statement/InsertRows.php` (895 bytes)
- `app/Restore/Integration/Sql/Statement/LockTables.php` (375 bytes)
- `app/Restore/Integration/Sql/Statement/SessionDirective.php` (387 bytes)
- `app/Restore/Integration/Sql/Statement/SourceDatabaseDeclaration.php` (432 bytes)
- `app/Restore/Integration/Sql/Statement/SourceDatabaseSelection.php` (428 bytes)
- `app/Restore/Integration/Sql/Statement/UnlockTables.php` (379 bytes)
- `app/Restore/Integration/Secrets/AtomicFileSecretEnvelopeStore.php` (4081 bytes)
- `app/Restore/Integration/Secrets/FileSecretEnvelopeStore.php` (629 bytes)
- `app/Restore/Integration/Secrets/NativeDpapiHelper.php` (5278 bytes)
- `app/Restore/Integration/Secrets/NativeHelperDeploymentVerifier.php` (176 bytes)
- `app/Restore/Integration/Secrets/NativeSecretEnvelopeStore.php` (377 bytes)
- `app/Restore/Integration/Secrets/ProcOpenWorkerProcessRunner.php` (2827 bytes)
- `app/Restore/Integration/Secrets/ProductionNativeHelperDeploymentVerifier.php` (1495 bytes)
- `app/Restore/Integration/Secrets/RuntimeIdentityProvider.php` (170 bytes)
- `app/Restore/Integration/Secrets/SecretEnvelopeCodec.php` (6050 bytes)
- `app/Restore/Integration/Secrets/SecretEnvelopeStore.php` (316 bytes)
- `app/Restore/Integration/Secrets/SecretProtector.php` (336 bytes)
- `app/Restore/Integration/Secrets/SecretProviderException.php` (169 bytes)
- `app/Restore/Integration/Secrets/SecretRole.php` (195 bytes)
- `app/Restore/Integration/Secrets/SecretServerBinding.php` (973 bytes)
- `app/Restore/Integration/Secrets/SecretValue.php` (1380 bytes)
- `app/Restore/Integration/Secrets/WindowsAclHardener.php` (277 bytes)
- `app/Restore/Integration/Secrets/WindowsIdentity.php` (944 bytes)
- `app/Restore/Integration/Secrets/WindowsSecretProvider.php` (2742 bytes)
- `app/Restore/Integration/Secrets/WorkerProcessResult.php` (229 bytes)
- `app/Restore/Integration/Secrets/WorkerProcessRunner.php` (268 bytes)
- `app/Restore/Integration/Readiness/ReadinessAssessment.php` (2167 bytes)
- `app/Restore/Integration/Readiness/ReadinessEvidence.php` (4029 bytes)
- `app/Restore/Integration/Readiness/RestoreIntegrationReadinessChecker.php` (11911 bytes)
- `app/Restore/Integration/Provisioning/CredentialProvisioningPlan.php` (3941 bytes)
- `app/Restore/Integration/Policy/RestoreIntegrationPolicy.php` (1012 bytes)
- `app/Restore/Integration/Policy/TrustedRunId.php` (398 bytes)
- `app/Restore/Integration/Policy/TrustedTargetName.php` (614 bytes)
- `app/Restore/Integration/Ownership/CleanupDecisionEngine.php` (1647 bytes)
- `app/Restore/Integration/Ownership/CleanupDecision.php` (506 bytes)
- `app/Restore/Integration/Ownership/OwnershipEvent.php` (821 bytes)
- `app/Restore/Integration/Ownership/OwnershipJournal.php` (5000 bytes)
- `app/Restore/Integration/Ownership/OwnershipMarker.php` (1458 bytes)
- `app/Restore/Integration/Ownership/OwnershipSnapshot.php` (1057 bytes)
- `app/Restore/Integration/Job/RestoreIntegrationJobAccess.php` (1398 bytes)
- `app/Restore/Integration/Job/RestoreIntegrationJobException.php` (174 bytes)
- `app/Restore/Integration/Job/RestoreIntegrationJobGuard.php` (1224 bytes)
- `app/Restore/Integration/Job/RestoreIntegrationJobStore.php` (7134 bytes)
- `app/Restore/Integration/Job/RestoreIntegrationState.php` (874 bytes)
- `app/Restore/Integration/Http/RestoreIntegrationApiAuthorization.php` (934 bytes)
- `app/Restore/Integration/Execution/CleanupCoordinator.php` (2265 bytes)
- `app/Restore/Integration/Execution/ExecutionResult.php` (439 bytes)
- `app/Restore/Integration/Execution/RestoreIntegrationRunner.php` (9378 bytes)
- `app/Restore/Integration/Execution/TargetDatabaseProvisioner.php` (2817 bytes)
- `app/Restore/Integration/Execution/TargetProvisioningException.php` (639 bytes)
- `app/Restore/Integration/Execution/TypedRestoreExecutor.php` (1096 bytes)
- `app/Restore/Integration/Database/AuthorizedSourceCatalog.php` (794 bytes)
- `app/Restore/Integration/Database/IntegrationBackupOperation.php` (1056 bytes)
- `app/Restore/Integration/Database/MetadataConnection.php` (642 bytes)
- `app/Restore/Integration/Database/MysqliRestoreConnection.php` (11932 bytes)
- `app/Restore/Integration/Database/OfflineRestoreConnection.php` (234 bytes)
- `app/Restore/Integration/Database/RestoreConnectionFactory.php` (1487 bytes)
- `app/Restore/Integration/Database/RestoreConnection.php` (1233 bytes)
- `app/Restore/Integration/Authorization/ExecutionAuthorization.php` (541 bytes)
- `app/Restore/Integration/Authorization/LiveAuthorizationReceipt.php` (2863 bytes)
- `app/Restore/Integration/Authorization/LiveAuthorizationReceiptStore.php` (3909 bytes)
- `app/Restore/Integration/Attestation/LiveRestoreAttestationGate.php` (2674 bytes)
- `app/Restore/Integration/Attestation/MariaDbPrivilegeDialect.php` (157 bytes)
- `app/Restore/Integration/Attestation/MySqlPrivilegeDialect.php` (1265 bytes)
- `app/Restore/Integration/Attestation/PrivilegeAttestationService.php` (1879 bytes)
- `app/Restore/Integration/Attestation/PrivilegeAttestor.php` (4928 bytes)
- `app/Restore/Integration/Attestation/PrivilegeDialect.php` (251 bytes)
- `app/Restore/Integration/Attestation/PrivilegeEvidence.php` (827 bytes)
- `app/Restore/Integration/Attestation/ServerIdentity.php` (1303 bytes)
- `app/Restore/Integration/Attestation/ServerIdentityProbe.php` (943 bytes)
- `app/Http/Controller/AuthController.php` (421 bytes)
- `app/Deployment/DedicatedApacheConfigValidator.php` (7135 bytes)
- `app/Deployment/SourcePackageBuilder.php` (14619 bytes)
- `public/restore-integration.php` (3205 bytes)
- `public/restore-job.php` (6180 bytes)
- `public/assets/restore-integration.js` (562 bytes)
- `public/assets/restore-jobs.js` (3876 bytes)
- `views/pages/login.php` (1196 bytes)
- `views/pages/restore-integration.php` (2363 bytes)
- `views/pages/restore.php` (6519 bytes)
- `tools/check-database-operation-lock-migration.php` (1117 bytes)
- `tools/generate-admin-credentials.php` (523 bytes)
- `tools/interactive-existing-import-acceptance.ps1` (2455 bytes)
- `tools/reset-existing-import-password.php` (5438 bytes)
- `tools/restore-integration-credential-plan.php` (1633 bytes)
- `tools/restore-integration-readiness.php` (1253 bytes)
- `tools/verify-restore-integration-offline.ps1` (2104 bytes)
- `tools/windows-dpapi-worker.ps1` (5370 bytes)
- `tools/windows-secret-identity.php` (1312 bytes)
- `tools/native-helper/build-release.ps1` (1741 bytes)
- `tools/native-helper/deployment-preflight.ps1` (1920 bytes)
- `tools/native-helper/NativeHelperDeployment.psm1` (9580 bytes)
- `tools/native-helper/new-deployment-plan.ps1` (2031 bytes)
- `tools/native-helper/show-signing-plan.ps1` (392 bytes)
- `tools/native-helper/validate-deployment-evidence.ps1` (918 bytes)
- `tools/native-helper/verify-deployed-helper.ps1` (1455 bytes)
- `tools/native-helper/verify-runtime-identity.ps1` (3032 bytes)
- `tools/native-helper/verify-signed-artifact.ps1` (835 bytes)
- `tools/dedicated-apache/build-source-package.php` (1560 bytes)
- `tools/dedicated-apache/deployment-preflight.ps1` (5210 bytes)
- `tools/dedicated-apache/validate-preflight-evidence.ps1` (3849 bytes)
- `tests/apache-deployment-smoke.ps1` (4669 bytes)
- `tests/dashboard-responsive.test.js` (9157 bytes)
- `tests/DedicatedApacheDeploymentPreparationTest.php` (6659 bytes)
- `tests/dedicated-apache-syntax.ps1` (4062 bytes)
- `tests/DedicatedSourcePackageTest.php` (6908 bytes)
- `tests/NativeHelperDeploymentPreparationTest.php` (9019 bytes)
- `tests/Phase3ApiAndUiTest.php` (2700 bytes)
- `tests/Phase3ArtifactValidationTest.php` (6725 bytes)
- `tests/Phase3RestoreJobTest.php` (7075 bytes)
- `tests/Phase3SqlSafetyTest.php` (6331 bytes)
- `tests/phase4-restore-polling.test.js` (2469 bytes)
- `tests/phase-restore-integration-ui.test.js` (500 bytes)
- `tests/RestoreIntegrationApiAndUiTest.php` (5634 bytes)
- `tests/RestoreIntegrationCredentialProvisioningTest.php` (6338 bytes)
- `tests/RestoreIntegrationExecutorTest.php` (19926 bytes)
- `tests/RestoreIntegrationJobTest.php` (3943 bytes)
- `tests/RestoreIntegrationOwnershipAndCleanupTest.php` (3899 bytes)
- `tests/RestoreIntegrationPolicyAndAttestationTest.php` (11315 bytes)
- `tests/RestoreIntegrationReadinessTest.php` (9923 bytes)
- `tests/RestoreIntegrationSqlPlanTest.php` (6197 bytes)
- `tests/RestoreIntegrationValidationTest.php` (7450 bytes)
- `tests/WindowsNativeSecretHelperProtocolTest.php` (3773 bytes)
- `tests/WindowsNativeSecretHelperTest.php` (4203 bytes)
- `tests/WindowsSecretEnvelopeTest.php` (5499 bytes)
- `tests/WindowsSecretProviderTest.php` (6241 bytes)
- `tests/WindowsSecretStorageTest.php` (5659 bytes)
- `tests/WindowsSecretWorkerProtocolTest.php` (2871 bytes)
- `tests/WindowsSecretWorkerTest.php` (1414 bytes)
- `docs/DEDICATED_APACHE_DEPLOYMENT_PLAN.md` (13368 bytes)
- `docs/RESTORE_INTEGRATION_CREDENTIAL_PROVISIONING_PLAN.md` (17057 bytes)
- `docs/RESTORE_INTEGRATION_LIVE_RUNBOOK.md` (17733 bytes)
- `docs/WINDOWS_SECRET_PROVIDER_DEPLOYMENT_DECISION.md` (9197 bytes)
- `docs/WINDOWS_SECRET_PROVIDER_DESIGN.md` (15099 bytes)
- `docs/WINDOWS_SECRET_PROVIDER_DOTNET_HELPER_DESIGN.md` (4468 bytes)
- `docs/WINDOWS_SECRET_PROVIDER_IMPLEMENTATION_PLAN.md` (1798 bytes)
- `docs/WINDOWS_SECRET_PROVIDER_NATIVE_DEPLOYMENT.md` (6153 bytes)
- `docs/WINDOWS_SECRET_PROVIDER_SIGNED_DEPLOYMENT_PREPARATION.md` (4853 bytes)
- `docs/superpowers/specs/2026-10-17-seamless-editorial-layout-design.md` (7956 bytes)
- `docs/superpowers/specs/2026-10-18-dedicated-apache-side-by-side-design.md` (4167 bytes)
- `docs/superpowers/plans/2026-10-16-phase1-foundation.md` (7477 bytes)
- `docs/superpowers/plans/2026-10-16-phase2-backup-jobs.md` (4393 bytes)
- `docs/superpowers/plans/2026-10-17-phase3-safe-restore.md` (5883 bytes)
- `docs/superpowers/plans/2026-10-17-phase4-modern-ui.md` (7062 bytes)
- `docs/superpowers/plans/2026-10-17-seamless-editorial-layout.md` (6705 bytes)
- `docs/superpowers/plans/2026-10-18-dedicated-apache-side-by-side.md` (3921 bytes)
- `docs/superpowers/plans/2026-10-18-manual-backup-safety.md` (4062 bytes)
- `docs/superpowers/plans/2026-10-18-native-dotnet-dpapi-helper.md` (3216 bytes)
- `docs/superpowers/plans/2026-10-18-native-helper-signed-deployment-preparation.md` (1610 bytes)
- `docs/superpowers/plans/2026-10-18-official-mysqldump.md` (5007 bytes)
- `docs/superpowers/plans/2026-10-18-windows-secret-provider.md` (10172 bytes)
- `deployment/dedicated-apache/deployment-contract.json` (1091 bytes)
- `deployment/dedicated-apache/source-allowlist.txt` (7894 bytes)
- `deployment/dedicated-apache/source-manifest.json` (34451 bytes)
- `deployment/dedicated-apache/php/php.ini` (1654 bytes)
- `deployment/dedicated-apache/conf/httpd.conf` (2356 bytes)
- `native/WindowsSecretHelper.Tests/AclPathPolicyTests.cs` (1110 bytes)
- `native/WindowsSecretHelper.Tests/packages.lock.json` (7994 bytes)
- `native/WindowsSecretHelper.Tests/ProtocolParserTests.cs` (2158 bytes)
- `native/WindowsSecretHelper.Tests/WindowsSecretHelper.Tests.csproj` (1089 bytes)
- `native/WindowsSecretHelper.Tests/obj/project.assets.json` (137991 bytes)
- `native/WindowsSecretHelper.Tests/obj/project.nuget.cache` (3881 bytes)
- `native/WindowsSecretHelper.Tests/obj/WindowsSecretHelper.Tests.csproj.nuget.dgspec.json` (46256 bytes)
- `native/WindowsSecretHelper.Tests/obj/WindowsSecretHelper.Tests.csproj.nuget.g.props` (3550 bytes)
- `native/WindowsSecretHelper.Tests/obj/WindowsSecretHelper.Tests.csproj.nuget.g.targets` (1791 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/apphost.exe` (162816 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/.NETCoreApp,Version=v10.0.AssemblyAttributes.cs` (200 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/SelfRegisteredExtensions.cs` (857 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/WindowsS.00EEAA51.Up2Date` (0 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.Tests.AssemblyInfo.cs` (1308 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.Tests.AssemblyInfoInputs.cache` (66 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.Tests.assets.cache` (51507 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.Tests.csproj.AssemblyReference.cache` (14560 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.Tests.csproj.CoreCompileInputs.cache` (66 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.Tests.csproj.FileListAbsolute.txt` (51216 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.Tests.dll` (12800 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.Tests.genautoregisteredextensionsinputcache.cache` (66 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.Tests.GeneratedMSBuildEditorConfig.editorconfig` (942 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.Tests.genruntimeconfig.cache` (66 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.Tests.gentestingplatformentrypointinputcache.cache` (66 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.Tests.GlobalUsings.g.cs` (239 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.Tests.pdb` (15292 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.Tests.sourcelink.json` (136 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.Tests.xUnit.v3.EntryPoint.cache` (66 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/XunitAutoGeneratedEntryPoint.cs` (711 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/refint/WindowsSecretHelper.Tests.dll` (9216 bytes)
- `native/WindowsSecretHelper.Tests/obj/Release/net10.0-windows/win-x64/ref/WindowsSecretHelper.Tests.dll` (9216 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/backup-secret-helper.deps.json` (28868 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/backup-secret-helper.dll` (35840 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/backup-secret-helper.exe` (162816 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/backup-secret-helper.runtimeconfig.json` (431 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/clretwrc.dll` (317776 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/clrgc.dll` (674088 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/clrgcexp.dll` (711464 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/clrjit.dll` (2095912 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/coreclr.dll` (4613416 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/createdump.exe` (71512 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/hostfxr.dll` (379728 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/hostpolicy.dll` (378704 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/Microsoft.ApplicationInsights.dll` (386080 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/Microsoft.Bcl.AsyncInterfaces.dll` (16000 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/Microsoft.CSharp.dll` (980776 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/Microsoft.DiaSymReader.Native.amd64.dll` (2377016 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/Microsoft.Testing.Extensions.MSBuild.dll` (60448 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/Microsoft.Testing.Extensions.Telemetry.dll` (44576 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/Microsoft.Testing.Extensions.TrxReport.Abstractions.dll` (32800 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/Microsoft.Testing.Platform.dll` (1153568 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/Microsoft.TestPlatform.CommunicationUtilities.dll` (138272 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/Microsoft.TestPlatform.CoreUtilities.dll` (99360 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/Microsoft.TestPlatform.CrossPlatEngine.dll` (318496 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/Microsoft.TestPlatform.PlatformAbstractions.dll` (51784 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/Microsoft.TestPlatform.Utilities.dll` (69192 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/Microsoft.VisualBasic.Core.dll` (1193768 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/Microsoft.VisualBasic.dll` (17232 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/Microsoft.VisualStudio.CodeCoverage.Shim.dll` (17440 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/Microsoft.VisualStudio.TestPlatform.Common.dll` (248904 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/Microsoft.VisualStudio.TestPlatform.ObjectModel.dll` (335392 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/Microsoft.Win32.Primitives.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/Microsoft.Win32.Registry.dll` (116560 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/mscordaccore_amd64_amd64_10.0.1326.50504.dll` (1356120 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/mscordaccore.dll` (1356120 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/mscordbi.dll` (1237336 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/mscorlib.dll` (59688 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/mscorrc.dll` (133928 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/.msCoverageSourceRootsMapping_WindowsSecretHelper.Tests` (556 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/msquic.dll` (537400 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/netstandard.dll` (100648 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/Newtonsoft.Json.dll` (712464 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.AppContext.dll` (15184 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Buffers.dll` (15144 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Collections.Concurrent.dll` (231248 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Collections.dll` (296784 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Collections.Immutable.dll` (976720 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Collections.NonGeneric.dll` (104272 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Collections.Specialized.dll` (104232 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.ComponentModel.Annotations.dll` (198440 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.ComponentModel.DataAnnotations.dll` (16720 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.ComponentModel.dll` (30544 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.ComponentModel.EventBasedAsync.dll` (46888 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.ComponentModel.Primitives.dll` (79656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.ComponentModel.TypeConverter.dll` (767824 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Configuration.dll` (19240 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Console.dll` (169768 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Core.dll` (23336 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Data.Common.dll` (2770728 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Data.DataSetExtensions.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Data.dll` (25384 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Diagnostics.Contracts.dll` (16168 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Diagnostics.Debug.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Diagnostics.DiagnosticSource.dll` (501584 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Diagnostics.FileVersionInfo.dll` (46888 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Diagnostics.Process.dll` (329552 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Diagnostics.StackTrace.dll` (46888 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Diagnostics.TextWriterTraceListener.dll` (67408 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Diagnostics.Tools.dll` (15184 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Diagnostics.TraceSource.dll` (141096 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Diagnostics.Tracing.dll` (16168 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.dll` (50512 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Drawing.dll` (20264 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Drawing.Primitives.dll` (132904 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Dynamic.Runtime.dll` (16168 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Formats.Asn1.dll` (239400 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Formats.Tar.dll` (288552 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Globalization.Calendars.dll` (15696 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Globalization.dll` (15696 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Globalization.Extensions.dll` (15144 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.IO.Compression.Brotli.dll` (83792 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.IO.Compression.dll` (419624 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.IO.Compression.FileSystem.dll` (15184 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.IO.Compression.Native.dll` (937256 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.IO.Compression.ZipFile.dll` (100136 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.IO.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.IO.FileSystem.AccessControl.dll` (104232 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.IO.FileSystem.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.IO.FileSystem.DriveInfo.dll` (55120 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.IO.FileSystem.Primitives.dll` (15184 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.IO.FileSystem.Watcher.dll` (87848 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.IO.IsolatedStorage.dll` (87888 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.IO.MemoryMappedFiles.dll` (83792 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.IO.Pipelines.dll` (190288 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.IO.Pipes.AccessControl.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.IO.Pipes.dll` (165672 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.IO.UnmanagedMemoryStream.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Linq.AsyncEnumerable.dll` (1292072 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Linq.dll` (698152 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Linq.Expressions.dll` (3639080 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Linq.Parallel.dll` (776016 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Linq.Queryable.dll` (182056 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Memory.dll` (161576 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Net.dll` (17192 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Net.Http.dll` (1746728 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Net.Http.Json.dll` (128808 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Net.HttpListener.dll` (534312 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Net.Mail.dll` (481064 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Net.NameResolution.dll` (124712 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Net.NetworkInformation.dll` (157480 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Net.Ping.dll` (91944 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Net.Primitives.dll` (210728 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Net.Quic.dll` (358224 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Net.Requests.dll` (374568 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Net.Security.dll` (665384 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Net.ServerSentEvents.dll` (83792 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Net.ServicePoint.dll` (15144 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Net.Sockets.dll` (530216 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Net.WebClient.dll` (165672 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Net.WebHeaderCollection.dll` (63272 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Net.WebProxy.dll` (42792 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Net.WebSockets.Client.dll` (104232 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Net.WebSockets.dll` (243496 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Numerics.dll` (15144 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Numerics.Vectors.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.ObjectModel.dll` (79656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Private.CoreLib.dll` (16033576 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Private.DataContractSerialization.dll` (2062120 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Private.Uri.dll` (255824 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Private.Xml.dll` (7788368 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Private.Xml.Linq.dll` (390992 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Reflection.DispatchProxy.dll` (75560 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Reflection.dll` (16168 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Reflection.Emit.dll` (309032 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Reflection.Emit.ILGeneration.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Reflection.Emit.Lightweight.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Reflection.Extensions.dll` (15184 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Reflection.Metadata.dll` (1156904 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Reflection.Primitives.dll` (15696 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Reflection.TypeExtensions.dll` (42792 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Resources.Reader.dll` (15144 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Resources.ResourceManager.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Resources.Writer.dll` (50984 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Runtime.CompilerServices.Unsafe.dll` (15184 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Runtime.CompilerServices.VisualC.dll` (30544 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Runtime.dll` (44840 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Runtime.Extensions.dll` (17704 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Runtime.Handles.dll` (15696 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Runtime.InteropServices.dll` (112424 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Runtime.InteropServices.JavaScript.dll` (51024 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Runtime.InteropServices.RuntimeInformation.dll` (15696 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Runtime.Intrinsics.dll` (17192 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Runtime.Loader.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Runtime.Numerics.dll` (354088 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Runtime.Serialization.dll` (17192 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Runtime.Serialization.Formatters.dll` (124712 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Runtime.Serialization.Json.dll` (15696 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Runtime.Serialization.Primitives.dll` (38696 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Runtime.Serialization.Xml.dll` (16680 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Security.AccessControl.dll` (227112 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Security.Claims.dll` (100136 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Security.Cryptography.Algorithms.dll` (17192 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Security.Cryptography.Cng.dll` (16168 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Security.Cryptography.Csp.dll` (16208 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Security.Cryptography.dll` (2549584 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Security.Cryptography.Encoding.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Security.Cryptography.OpenSsl.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Security.Cryptography.Primitives.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Security.Cryptography.ProtectedData.dll` (40208 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Security.Cryptography.X509Certificates.dll` (16680 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Security.dll` (18256 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Security.Principal.dll` (15144 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Security.Principal.Windows.dll` (178000 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Security.SecureString.dll` (15696 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.ServiceModel.Web.dll` (16720 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.ServiceProcess.dll` (15696 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Text.Encoding.CodePages.dll` (861992 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Text.Encoding.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Text.Encoding.Extensions.dll` (15696 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Text.Encodings.Web.dll` (124712 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Text.Json.dll` (1890088 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Text.RegularExpressions.dll` (1034024 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Threading.AccessControl.dll` (75560 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Threading.Channels.dll` (157480 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Threading.dll` (83752 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Threading.Overlapped.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Threading.Tasks.Dataflow.dll` (472912 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Threading.Tasks.dll` (16680 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Threading.Tasks.Extensions.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Threading.Tasks.Parallel.dll` (132944 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Threading.Thread.dll` (15696 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Threading.ThreadPool.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Threading.Timer.dll` (15184 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Transactions.dll` (16680 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Transactions.Local.dll` (640808 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.ValueTuple.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Web.dll` (15144 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Web.HttpUtility.dll` (63272 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Windows.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Xml.dll` (23336 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Xml.Linq.dll` (15696 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Xml.ReaderWriter.dll` (21800 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Xml.Serialization.dll` (16168 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Xml.XDocument.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Xml.XmlDocument.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Xml.XmlSerializer.dll` (17744 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Xml.XPath.dll` (15656 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/System.Xml.XPath.XDocument.dll` (30544 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/testhost.dll` (49696 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/testhost.exe` (161824 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/WindowsBase.dll` (16168 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/WindowsSecretHelper.Tests.deps.json` (56347 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/WindowsSecretHelper.Tests.dll` (12800 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/WindowsSecretHelper.Tests.exe` (162816 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/WindowsSecretHelper.Tests.pdb` (15292 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/WindowsSecretHelper.Tests.runtimeconfig.json` (479 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/xunit.abstractions.dll` (21928 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/xunit.runner.visualstudio.testadapter.dll` (1253376 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/xunit.v3.assert.dll` (214792 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/xunit.v3.common.dll` (191752 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/xunit.v3.core.dll` (498440 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/xunit.v3.mtp-v1.dll` (123144 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/xunit.v3.runner.common.dll` (978696 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/xunit.v3.runner.inproc.console.dll` (75528 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/xunit.v3.runner.utility.netcore.dll` (174856 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/zh-Hant/Microsoft.Testing.Extensions.MSBuild.resources.dll` (15392 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/zh-Hant/Microsoft.Testing.Extensions.Telemetry.resources.dll` (15392 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/zh-Hant/Microsoft.Testing.Platform.resources.dll` (41032 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/zh-Hant/Microsoft.TestPlatform.CommunicationUtilities.resources.dll` (16416 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/zh-Hant/Microsoft.TestPlatform.CoreUtilities.resources.dll` (16928 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/zh-Hant/Microsoft.TestPlatform.CrossPlatEngine.resources.dll` (20552 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/zh-Hant/Microsoft.VisualStudio.TestPlatform.Common.resources.dll` (20000 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/zh-Hant/Microsoft.VisualStudio.TestPlatform.ObjectModel.resources.dll` (23112 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/zh-Hans/Microsoft.Testing.Extensions.MSBuild.resources.dll` (15432 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/zh-Hans/Microsoft.Testing.Extensions.Telemetry.resources.dll` (15424 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/zh-Hans/Microsoft.Testing.Platform.resources.dll` (40520 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/zh-Hans/Microsoft.TestPlatform.CommunicationUtilities.resources.dll` (16416 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/zh-Hans/Microsoft.TestPlatform.CoreUtilities.resources.dll` (16928 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/zh-Hans/Microsoft.TestPlatform.CrossPlatEngine.resources.dll` (20000 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/zh-Hans/Microsoft.VisualStudio.TestPlatform.Common.resources.dll` (20000 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/zh-Hans/Microsoft.VisualStudio.TestPlatform.ObjectModel.resources.dll` (23112 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/tr/Microsoft.Testing.Extensions.MSBuild.resources.dll` (15392 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/tr/Microsoft.Testing.Extensions.Telemetry.resources.dll` (15392 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/tr/Microsoft.Testing.Platform.resources.dll` (43040 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/tr/Microsoft.TestPlatform.CommunicationUtilities.resources.dll` (16928 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/tr/Microsoft.TestPlatform.CoreUtilities.resources.dll` (16928 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/tr/Microsoft.TestPlatform.CrossPlatEngine.resources.dll` (21064 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/tr/Microsoft.VisualStudio.TestPlatform.Common.resources.dll` (20512 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/tr/Microsoft.VisualStudio.TestPlatform.ObjectModel.resources.dll` (23624 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ru/Microsoft.Testing.Extensions.MSBuild.resources.dll` (15432 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ru/Microsoft.Testing.Extensions.Telemetry.resources.dll` (15432 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ru/Microsoft.Testing.Platform.resources.dll` (50248 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ru/Microsoft.TestPlatform.CommunicationUtilities.resources.dll` (17432 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ru/Microsoft.TestPlatform.CoreUtilities.resources.dll` (17440 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ru/Microsoft.TestPlatform.CrossPlatEngine.resources.dll` (23072 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ru/Microsoft.VisualStudio.TestPlatform.Common.resources.dll` (23072 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ru/Microsoft.VisualStudio.TestPlatform.ObjectModel.resources.dll` (26144 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/pt-BR/Microsoft.Testing.Extensions.MSBuild.resources.dll` (15392 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/pt-BR/Microsoft.Testing.Extensions.Telemetry.resources.dll` (15392 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/pt-BR/Microsoft.Testing.Platform.resources.dll` (43040 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/pt-BR/Microsoft.TestPlatform.CommunicationUtilities.resources.dll` (16408 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/pt-BR/Microsoft.TestPlatform.CoreUtilities.resources.dll` (16928 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/pt-BR/Microsoft.TestPlatform.CrossPlatEngine.resources.dll` (21064 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/pt-BR/Microsoft.VisualStudio.TestPlatform.Common.resources.dll` (21064 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/pt-BR/Microsoft.VisualStudio.TestPlatform.ObjectModel.resources.dll` (24136 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/pl/Microsoft.Testing.Extensions.MSBuild.resources.dll` (15392 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/pl/Microsoft.Testing.Extensions.Telemetry.resources.dll` (15432 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/pl/Microsoft.Testing.Platform.resources.dll` (44064 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/pl/Microsoft.TestPlatform.CommunicationUtilities.resources.dll` (16928 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/pl/Microsoft.TestPlatform.CoreUtilities.resources.dll` (16968 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/pl/Microsoft.TestPlatform.CrossPlatEngine.resources.dll` (21064 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/pl/Microsoft.VisualStudio.TestPlatform.Common.resources.dll` (21024 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/pl/Microsoft.VisualStudio.TestPlatform.ObjectModel.resources.dll` (24096 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ko/Microsoft.Testing.Extensions.MSBuild.resources.dll` (15392 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ko/Microsoft.Testing.Extensions.Telemetry.resources.dll` (15432 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ko/Microsoft.Testing.Platform.resources.dll` (44064 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ko/Microsoft.TestPlatform.CommunicationUtilities.resources.dll` (16928 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ko/Microsoft.TestPlatform.CoreUtilities.resources.dll` (16928 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ko/Microsoft.TestPlatform.CrossPlatEngine.resources.dll` (21576 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ko/Microsoft.VisualStudio.TestPlatform.Common.resources.dll` (21064 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ko/Microsoft.VisualStudio.TestPlatform.ObjectModel.resources.dll` (24096 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ja/Microsoft.Testing.Extensions.MSBuild.resources.dll` (15392 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ja/Microsoft.Testing.Extensions.Telemetry.resources.dll` (15392 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ja/Microsoft.Testing.Platform.resources.dll` (46152 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ja/Microsoft.TestPlatform.CommunicationUtilities.resources.dll` (16968 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ja/Microsoft.TestPlatform.CoreUtilities.resources.dll` (17440 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ja/Microsoft.TestPlatform.CrossPlatEngine.resources.dll` (22048 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ja/Microsoft.VisualStudio.TestPlatform.Common.resources.dll` (21576 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/ja/Microsoft.VisualStudio.TestPlatform.ObjectModel.resources.dll` (24608 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/it/Microsoft.Testing.Extensions.MSBuild.resources.dll` (15392 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/it/Microsoft.Testing.Extensions.Telemetry.resources.dll` (15432 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/it/Microsoft.Testing.Platform.resources.dll` (43552 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/it/Microsoft.TestPlatform.CommunicationUtilities.resources.dll` (16968 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/it/Microsoft.TestPlatform.CoreUtilities.resources.dll` (16928 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/it/Microsoft.TestPlatform.CrossPlatEngine.resources.dll` (21024 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/it/Microsoft.VisualStudio.TestPlatform.Common.resources.dll` (21024 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/it/Microsoft.VisualStudio.TestPlatform.ObjectModel.resources.dll` (24096 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/fr/Microsoft.Testing.Extensions.MSBuild.resources.dll` (15432 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/fr/Microsoft.Testing.Extensions.Telemetry.resources.dll` (15392 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/fr/Microsoft.Testing.Platform.resources.dll` (45088 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/fr/Microsoft.TestPlatform.CommunicationUtilities.resources.dll` (16928 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/fr/Microsoft.TestPlatform.CoreUtilities.resources.dll` (16968 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/fr/Microsoft.TestPlatform.CrossPlatEngine.resources.dll` (21064 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/fr/Microsoft.VisualStudio.TestPlatform.Common.resources.dll` (21024 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/fr/Microsoft.VisualStudio.TestPlatform.ObjectModel.resources.dll` (24096 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/es/Microsoft.Testing.Extensions.MSBuild.resources.dll` (15392 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/es/Microsoft.Testing.Extensions.Telemetry.resources.dll` (15392 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/es/Microsoft.Testing.Platform.resources.dll` (43552 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/es/Microsoft.TestPlatform.CommunicationUtilities.resources.dll` (16968 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/es/Microsoft.TestPlatform.CoreUtilities.resources.dll` (16968 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/es/Microsoft.TestPlatform.CrossPlatEngine.resources.dll` (21024 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/es/Microsoft.VisualStudio.TestPlatform.Common.resources.dll` (21024 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/es/Microsoft.VisualStudio.TestPlatform.ObjectModel.resources.dll` (24136 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/de/Microsoft.Testing.Extensions.MSBuild.resources.dll` (15392 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/de/Microsoft.Testing.Extensions.Telemetry.resources.dll` (15392 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/de/Microsoft.Testing.Platform.resources.dll` (43592 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/de/Microsoft.TestPlatform.CommunicationUtilities.resources.dll` (16928 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/de/Microsoft.TestPlatform.CoreUtilities.resources.dll` (16928 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/de/Microsoft.TestPlatform.CrossPlatEngine.resources.dll` (21024 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/de/Microsoft.VisualStudio.TestPlatform.Common.resources.dll` (21024 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/de/Microsoft.VisualStudio.TestPlatform.ObjectModel.resources.dll` (24096 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/cs/Microsoft.Testing.Extensions.MSBuild.resources.dll` (15392 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/cs/Microsoft.Testing.Extensions.Telemetry.resources.dll` (15392 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/cs/Microsoft.Testing.Platform.resources.dll` (43592 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/cs/Microsoft.TestPlatform.CommunicationUtilities.resources.dll` (16416 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/cs/Microsoft.TestPlatform.CoreUtilities.resources.dll` (16928 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/cs/Microsoft.TestPlatform.CrossPlatEngine.resources.dll` (21064 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/cs/Microsoft.VisualStudio.TestPlatform.Common.resources.dll` (20512 bytes)
- `native/WindowsSecretHelper.Tests/bin/Release/net10.0-windows/win-x64/cs/Microsoft.VisualStudio.TestPlatform.ObjectModel.resources.dll` (23584 bytes)
- `native/WindowsSecretHelper/AclPathPolicy.cs` (2500 bytes)
- `native/WindowsSecretHelper/packages.lock.json` (1188 bytes)
- `native/WindowsSecretHelper/Program.cs` (1399 bytes)
- `native/WindowsSecretHelper/Protocol.cs` (5332 bytes)
- `native/WindowsSecretHelper/WindowsSecretHelper.csproj` (1133 bytes)
- `native/WindowsSecretHelper/WorkerServices.cs` (4427 bytes)
- `native/WindowsSecretHelper/obj/project.assets.json` (31528 bytes)
- `native/WindowsSecretHelper/obj/project.nuget.cache` (1179 bytes)
- `native/WindowsSecretHelper/obj/WindowsSecretHelper.csproj.nuget.dgspec.json` (23040 bytes)
- `native/WindowsSecretHelper/obj/WindowsSecretHelper.csproj.nuget.g.props` (1763 bytes)
- `native/WindowsSecretHelper/obj/WindowsSecretHelper.csproj.nuget.g.targets` (150 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/apphost.exe` (162816 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/backup-secret-helper.deps.json` (27433 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/backup-secret-helper.dll` (35840 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/.NETCoreApp,Version=v10.0.AssemblyAttributes.cs` (200 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/PublishOutputs.68a0eb2f86.txt` (120 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/PublishOutputs.90073b37db.txt` (120 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/PublishOutputs.a63c4d9046.txt` (198 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/singlefilehost.exe` (9991680 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/WindowsS.880CFA42.Up2Date` (0 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.AssemblyInfo.cs` (1195 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.AssemblyInfoInputs.cache` (66 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.assets.cache` (2659 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.csproj.AssemblyReference.cache` (560 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.csproj.CoreCompileInputs.cache` (66 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.csproj.FileListAbsolute.txt` (27088 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.genbundle.cache` (66 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.GeneratedMSBuildEditorConfig.editorconfig` (1206 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.genpublishdeps.cache` (66 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.genruntimeconfig.cache` (66 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.GlobalUsings.g.cs` (239 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/WindowsSecretHelper.sourcelink.json` (136 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/refint/backup-secret-helper.dll` (11776 bytes)
- `native/WindowsSecretHelper/obj/Release/net10.0-windows/win-x64/ref/backup-secret-helper.dll` (11776 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/backup-secret-helper.deps.json` (28868 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/backup-secret-helper.dll` (35840 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/backup-secret-helper.exe` (162816 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/backup-secret-helper.runtimeconfig.json` (431 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/clretwrc.dll` (317776 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/clrgc.dll` (674088 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/clrgcexp.dll` (711464 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/clrjit.dll` (2095912 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/coreclr.dll` (4613416 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/createdump.exe` (71512 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/hostfxr.dll` (379728 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/hostpolicy.dll` (378704 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/Microsoft.CSharp.dll` (980776 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/Microsoft.DiaSymReader.Native.amd64.dll` (2377016 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/Microsoft.VisualBasic.Core.dll` (1193768 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/Microsoft.VisualBasic.dll` (17232 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/Microsoft.Win32.Primitives.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/Microsoft.Win32.Registry.dll` (116560 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/mscordaccore_amd64_amd64_10.0.1326.50504.dll` (1356120 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/mscordaccore.dll` (1356120 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/mscordbi.dll` (1237336 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/mscorlib.dll` (59688 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/mscorrc.dll` (133928 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/msquic.dll` (537400 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/netstandard.dll` (100648 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.AppContext.dll` (15184 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Buffers.dll` (15144 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Collections.Concurrent.dll` (231248 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Collections.dll` (296784 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Collections.Immutable.dll` (976720 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Collections.NonGeneric.dll` (104272 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Collections.Specialized.dll` (104232 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.ComponentModel.Annotations.dll` (198440 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.ComponentModel.DataAnnotations.dll` (16720 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.ComponentModel.dll` (30544 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.ComponentModel.EventBasedAsync.dll` (46888 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.ComponentModel.Primitives.dll` (79656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.ComponentModel.TypeConverter.dll` (767824 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Configuration.dll` (19240 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Console.dll` (169768 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Core.dll` (23336 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Data.Common.dll` (2770728 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Data.DataSetExtensions.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Data.dll` (25384 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Diagnostics.Contracts.dll` (16168 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Diagnostics.Debug.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Diagnostics.DiagnosticSource.dll` (501584 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Diagnostics.FileVersionInfo.dll` (46888 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Diagnostics.Process.dll` (329552 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Diagnostics.StackTrace.dll` (46888 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Diagnostics.TextWriterTraceListener.dll` (67408 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Diagnostics.Tools.dll` (15184 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Diagnostics.TraceSource.dll` (141096 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Diagnostics.Tracing.dll` (16168 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.dll` (50512 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Drawing.dll` (20264 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Drawing.Primitives.dll` (132904 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Dynamic.Runtime.dll` (16168 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Formats.Asn1.dll` (239400 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Formats.Tar.dll` (288552 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Globalization.Calendars.dll` (15696 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Globalization.dll` (15696 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Globalization.Extensions.dll` (15144 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.IO.Compression.Brotli.dll` (83792 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.IO.Compression.dll` (419624 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.IO.Compression.FileSystem.dll` (15184 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.IO.Compression.Native.dll` (937256 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.IO.Compression.ZipFile.dll` (100136 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.IO.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.IO.FileSystem.AccessControl.dll` (104232 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.IO.FileSystem.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.IO.FileSystem.DriveInfo.dll` (55120 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.IO.FileSystem.Primitives.dll` (15184 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.IO.FileSystem.Watcher.dll` (87848 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.IO.IsolatedStorage.dll` (87888 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.IO.MemoryMappedFiles.dll` (83792 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.IO.Pipelines.dll` (190288 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.IO.Pipes.AccessControl.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.IO.Pipes.dll` (165672 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.IO.UnmanagedMemoryStream.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Linq.AsyncEnumerable.dll` (1292072 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Linq.dll` (698152 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Linq.Expressions.dll` (3639080 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Linq.Parallel.dll` (776016 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Linq.Queryable.dll` (182056 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Memory.dll` (161576 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Net.dll` (17192 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Net.Http.dll` (1746728 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Net.Http.Json.dll` (128808 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Net.HttpListener.dll` (534312 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Net.Mail.dll` (481064 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Net.NameResolution.dll` (124712 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Net.NetworkInformation.dll` (157480 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Net.Ping.dll` (91944 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Net.Primitives.dll` (210728 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Net.Quic.dll` (358224 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Net.Requests.dll` (374568 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Net.Security.dll` (665384 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Net.ServerSentEvents.dll` (83792 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Net.ServicePoint.dll` (15144 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Net.Sockets.dll` (530216 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Net.WebClient.dll` (165672 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Net.WebHeaderCollection.dll` (63272 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Net.WebProxy.dll` (42792 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Net.WebSockets.Client.dll` (104232 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Net.WebSockets.dll` (243496 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Numerics.dll` (15144 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Numerics.Vectors.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.ObjectModel.dll` (79656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Private.CoreLib.dll` (16033576 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Private.DataContractSerialization.dll` (2062120 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Private.Uri.dll` (255824 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Private.Xml.dll` (7788368 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Private.Xml.Linq.dll` (390992 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Reflection.DispatchProxy.dll` (75560 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Reflection.dll` (16168 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Reflection.Emit.dll` (309032 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Reflection.Emit.ILGeneration.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Reflection.Emit.Lightweight.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Reflection.Extensions.dll` (15184 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Reflection.Metadata.dll` (1156904 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Reflection.Primitives.dll` (15696 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Reflection.TypeExtensions.dll` (42792 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Resources.Reader.dll` (15144 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Resources.ResourceManager.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Resources.Writer.dll` (50984 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Runtime.CompilerServices.Unsafe.dll` (15184 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Runtime.CompilerServices.VisualC.dll` (30544 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Runtime.dll` (44840 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Runtime.Extensions.dll` (17704 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Runtime.Handles.dll` (15696 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Runtime.InteropServices.dll` (112424 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Runtime.InteropServices.JavaScript.dll` (51024 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Runtime.InteropServices.RuntimeInformation.dll` (15696 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Runtime.Intrinsics.dll` (17192 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Runtime.Loader.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Runtime.Numerics.dll` (354088 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Runtime.Serialization.dll` (17192 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Runtime.Serialization.Formatters.dll` (124712 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Runtime.Serialization.Json.dll` (15696 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Runtime.Serialization.Primitives.dll` (38696 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Runtime.Serialization.Xml.dll` (16680 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Security.AccessControl.dll` (227112 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Security.Claims.dll` (100136 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Security.Cryptography.Algorithms.dll` (17192 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Security.Cryptography.Cng.dll` (16168 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Security.Cryptography.Csp.dll` (16208 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Security.Cryptography.dll` (2549584 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Security.Cryptography.Encoding.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Security.Cryptography.OpenSsl.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Security.Cryptography.Primitives.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Security.Cryptography.ProtectedData.dll` (40208 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Security.Cryptography.X509Certificates.dll` (16680 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Security.dll` (18256 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Security.Principal.dll` (15144 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Security.Principal.Windows.dll` (178000 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Security.SecureString.dll` (15696 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.ServiceModel.Web.dll` (16720 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.ServiceProcess.dll` (15696 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Text.Encoding.CodePages.dll` (861992 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Text.Encoding.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Text.Encoding.Extensions.dll` (15696 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Text.Encodings.Web.dll` (124712 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Text.Json.dll` (1890088 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Text.RegularExpressions.dll` (1034024 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Threading.AccessControl.dll` (75560 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Threading.Channels.dll` (157480 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Threading.dll` (83752 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Threading.Overlapped.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Threading.Tasks.Dataflow.dll` (472912 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Threading.Tasks.dll` (16680 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Threading.Tasks.Extensions.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Threading.Tasks.Parallel.dll` (132944 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Threading.Thread.dll` (15696 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Threading.ThreadPool.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Threading.Timer.dll` (15184 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Transactions.dll` (16680 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Transactions.Local.dll` (640808 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.ValueTuple.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Web.dll` (15144 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Web.HttpUtility.dll` (63272 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Windows.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Xml.dll` (23336 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Xml.Linq.dll` (15696 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Xml.ReaderWriter.dll` (21800 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Xml.Serialization.dll` (16168 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Xml.XDocument.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Xml.XmlDocument.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Xml.XmlSerializer.dll` (17744 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Xml.XPath.dll` (15656 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/System.Xml.XPath.XDocument.dll` (30544 bytes)
- `native/WindowsSecretHelper/bin/Release/net10.0-windows/win-x64/WindowsBase.dll` (16168 bytes)
- `native/artifacts/offline/win-x64/backup-secret-helper.exe` (73604645 bytes)
- `native/artifacts/offline/win-x64/backup-secret-helper.pdb` (18292 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/source-manifest.json` (34451 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/views/layout.php` (3315 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/views/partials/navigation.php` (1744 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/views/pages/backup.php` (7643 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/views/pages/dashboard.php` (3544 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/views/pages/history.php` (4309 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/views/pages/login.php` (1196 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/views/pages/placeholder.php` (447 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/views/pages/restore-integration.php` (2363 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/views/pages/restore.php` (6519 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/views/pages/settings.php` (3878 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/public/download.php` (2422 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/public/index.php` (16300 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/public/job.php` (7719 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/public/restore-integration.php` (2764 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/public/restore-job.php` (5743 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/public/assets/app.css` (18643 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/public/assets/app.js` (2230 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/public/assets/backup-jobs.js` (9218 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/public/assets/restore-integration.js` (562 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/public/assets/restore-jobs.js` (3876 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/config/app.php` (574 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/config/local.php.example` (629 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/bootstrap.php` (1122 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Support/Auth.php` (2175 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Support/BackupPathPolicy.php` (5433 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Support/Config.php` (680 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Support/DownloadAuthorization.php` (3080 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Support/Flash.php` (515 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Support/Logger.php` (1180 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Support/Request.php` (672 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Support/RuntimeDataBootstrap.php` (1260 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Support/RuntimeDataPaths.php` (6904 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Support/Security.php` (1447 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Support/Session.php` (1997 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Support/View.php` (1444 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/BackupProvenanceIndex.php` (2653 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/DatabaseOperationLockContract.php` (418 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/RestoreArtifactValidator.php` (8516 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/RestoreExecutionGuard.php` (1701 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/RestoreJobAccess.php` (3163 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/RestoreJobRequestException.php` (362 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/RestoreJobStore.php` (7490 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/RestorePreflightRunner.php` (6696 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/RestoreSafetyGate.php` (602 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/RestoreTargetValidator.php` (842 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/SqlStructureValidator.php` (220 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/StructuralSqlParser.php` (15434 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Sql/LexedStatement.php` (573 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Sql/SqlFileByteReader.php` (1622 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Sql/SqlStatementLexer.php` (8888 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Validation/PostRestoreValidator.php` (7056 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Validation/RowCanonicalizer.php` (1835 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Validation/RowEvidenceAccumulator.php` (5505 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Validation/RowMultisetComparator.php` (1296 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Validation/SchemaComparator.php` (1195 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Validation/StoredObjectComparator.php` (548 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Validation/TableDataEvidence.php` (813 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Validation/TypedSqlValue.php` (1007 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Validation/ValidationReport.php` (799 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/AbstractExecutableStatement.php` (541 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/CreateTableEvidenceParser.php` (5885 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/ExecutableRestoreStatement.php` (217 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/ExecutableStatementPolicy.php` (2978 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/FileStatementPayload.php` (466 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/InlineStatementPayload.php` (269 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/InsertRowsParser.php` (4456 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/ParsedInsertRows.php` (665 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/RestorePlanCompiler.php` (9781 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/RestorePlanItem.php` (149 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/RestorePlan.php` (1620 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/RestoreStatementKind.php` (463 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/StatementPayload.php` (146 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/StatementSpoolLease.php` (1480 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/StatementSpoolWriter.php` (1877 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/ValidatedExecutableStatement.php` (808 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/Statement/AlterTable.php` (375 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/Statement/CreatePlaceholderView.php` (398 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/Statement/CreateTable.php` (619 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/Statement/DropObject.php` (375 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/Statement/InsertRows.php` (895 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/Statement/LockTables.php` (375 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/Statement/SessionDirective.php` (387 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/Statement/SourceDatabaseDeclaration.php` (432 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/Statement/SourceDatabaseSelection.php` (428 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Sql/Statement/UnlockTables.php` (379 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Secrets/AtomicFileSecretEnvelopeStore.php` (4081 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Secrets/FileSecretEnvelopeStore.php` (629 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Secrets/NativeDpapiHelper.php` (5278 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Secrets/NativeHelperDeploymentVerifier.php` (176 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Secrets/NativeSecretEnvelopeStore.php` (377 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Secrets/ProcOpenWorkerProcessRunner.php` (2827 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Secrets/ProductionNativeHelperDeploymentVerifier.php` (1495 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Secrets/RuntimeIdentityProvider.php` (170 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Secrets/SecretEnvelopeCodec.php` (6050 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Secrets/SecretEnvelopeStore.php` (316 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Secrets/SecretProtector.php` (336 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Secrets/SecretProviderException.php` (169 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Secrets/SecretRole.php` (195 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Secrets/SecretServerBinding.php` (973 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Secrets/SecretValue.php` (1380 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Secrets/WindowsAclHardener.php` (277 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Secrets/WindowsIdentity.php` (944 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Secrets/WindowsSecretProvider.php` (2742 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Secrets/WorkerProcessResult.php` (229 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Secrets/WorkerProcessRunner.php` (268 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Readiness/ReadinessAssessment.php` (2167 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Readiness/ReadinessEvidence.php` (4029 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Readiness/RestoreIntegrationReadinessChecker.php` (11911 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Provisioning/CredentialProvisioningPlan.php` (3941 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Policy/RestoreIntegrationPolicy.php` (1107 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Policy/TrustedRunId.php` (398 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Policy/TrustedTargetName.php` (614 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Ownership/CleanupDecisionEngine.php` (1647 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Ownership/CleanupDecision.php` (506 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Ownership/OwnershipEvent.php` (821 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Ownership/OwnershipJournal.php` (5000 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Ownership/OwnershipMarker.php` (1458 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Ownership/OwnershipSnapshot.php` (1057 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Job/RestoreIntegrationJobAccess.php` (1398 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Job/RestoreIntegrationJobException.php` (174 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Job/RestoreIntegrationJobGuard.php` (1224 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Job/RestoreIntegrationJobStore.php` (7134 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Job/RestoreIntegrationState.php` (874 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Http/RestoreIntegrationApiAuthorization.php` (934 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Execution/CleanupCoordinator.php` (2265 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Execution/ExecutionResult.php` (439 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Execution/RestoreIntegrationRunner.php` (9378 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Execution/TargetDatabaseProvisioner.php` (2817 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Execution/TargetProvisioningException.php` (639 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Execution/TypedRestoreExecutor.php` (1096 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Database/AuthorizedSourceCatalog.php` (794 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Database/IntegrationBackupOperation.php` (1056 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Database/MetadataConnection.php` (642 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Database/MysqliRestoreConnection.php` (11932 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Database/OfflineRestoreConnection.php` (234 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Database/RestoreConnectionFactory.php` (1487 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Database/RestoreConnection.php` (1233 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Authorization/ExecutionAuthorization.php` (541 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Authorization/LiveAuthorizationReceipt.php` (2863 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Authorization/LiveAuthorizationReceiptStore.php` (3909 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Attestation/LiveRestoreAttestationGate.php` (2674 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Attestation/MariaDbPrivilegeDialect.php` (157 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Attestation/MySqlPrivilegeDialect.php` (1265 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Attestation/PrivilegeAttestationService.php` (1879 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Attestation/PrivilegeAttestor.php` (4928 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Attestation/PrivilegeDialect.php` (251 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Attestation/PrivilegeEvidence.php` (827 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Attestation/ServerIdentity.php` (1303 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Restore/Integration/Attestation/ServerIdentityProbe.php` (943 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Http/Router.php` (789 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Http/Controller/AuthController.php` (421 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Http/Controller/BackupController.php` (3450 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Http/Controller/SettingsController.php` (1662 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Database/ConnectionFactory.php` (743 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Database/DatabaseOperationLock.php` (290 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Database/DatabaseRepository.php` (702 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Database/FileDatabaseOperationLock.php` (1444 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Backup/BackupArtifactPublisher.php` (6473 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Backup/BackupExecutionGuard.php` (2067 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Backup/BackupJobAccess.php` (6954 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Backup/BackupJobRequestException.php` (362 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Backup/BackupJobRunner.php` (9012 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Backup/BackupJobStore.php` (8125 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Backup/BackupOperation.php` (353 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Backup/BackupService.php` (32861 bytes)
- `build/dedicated-apache/packages/wt-b5246f66b9fd94a3/Application/app/Backup/MysqliBackupOperation.php` (1722 bytes)
- `global.json` (105 bytes)
- `PHASE1_AUDIT.md` (9935 bytes)
- `PHASE1_DESIGN.md` (16580 bytes)
- `PHASE2_DESIGN.md` (9915 bytes)
- `PHASE3_DESIGN.md` (12794 bytes)
- `PHASE4_DESIGN.md` (7421 bytes)
- `RESTORE_INTEGRATION_DESIGN.md` (47213 bytes)
- `RESTORE_INTEGRATION_IMPLEMENTATION_PLAN.md` (50909 bytes)

## Verification

Existing live Export 4/4, focused 39/39, mysql.exe exit 0, schema9/9, current-source values9/9 and finalization12/12 are reused, not re-executed. Snapshot fidelity remains NOT VERIFIED / overall Import PARTIAL.


## Completed verification and current structure

Independent read-only cleanup dependency review: **PASS**, no Critical/Important/Minor findings. Reviewer verified the current graph/deleted-file references, not live databases/Apache/private credential contents or protected hashes. Overall **CLEANUP PASS (offline)**; historical Import acceptance remains PARTIAL, not upgraded. No further refactor or live phase started.

- [x] All five cleanup plan tasks completed; exact reconciliation: **960/960 listed files absent**, no unexpected deletion against the pre-cleanup inventory. Removed bytes: **257,105,758** (including ignored generated runtime build copies/native outputs).
- [x] **66 protected files** (all original storage files plus config/local.php) match exact path inventory, size, SHA-256 and LastWriteTimeUtc before/after. No extra protected files. Reparse points were rejected during enumeration; native/build trees were verified project-local before deletion; no storage wildcard deletion. .git/.agents/.superpowers left untouched.
- [x] Fresh focused PHP checks **50 PASS / 0 FAIL / 1 SKIP** across 10 directly affected suites, **51 selected**: CleanupDependency3, ImportSchemaComparison3, SecurityFoundation10, current Phase4Ui8, RoutingAndView4, LocalImportArtifact3, LocalManagerPresentation5, LocalImportUi2, Phase2JobApiAndUi5, StorageAndDownload7 PASS +1 SKIP. Skip: Windows cannot create the isolated test symlink; no claim of a new symlink-escape PASS. These are not the complete regression suite.
- [x] Changed PHP syntax **14/14 PASS**. No UI JavaScript changed, so prior Node presentation5/5 is reused, not rerun. git diff --check PASS.
- [x] Nine actual PHP entrypoint smoke cases on isolated copies/temp storage with mysqli_init disabled: five current pages200, unknown page404, job API wrong method405, Import API CSRF419, unauthorized Download404. No real Apache request/session write; no new claim of Apache deployment verification. Actual Apache routing evidence from the preceding finalization is reused. No DB/socket/live export/import/comparison executed.
- [x] Autoload declarations + explicit namespaced references in app/public/views/tools resolve. No deleted prototype classes/files referenced by remaining runtime sources or tests. Shared locks, native process adapters, ImportRunner/Policy/Store, artifact publishing/hash/download algorithms were not changed. Legacy History metadata test demonstrates candidate is not integrity_verified or eligible, changed same-size SQL is not hashed by History, missing provenance remains unverified, coercive metadata fails.

Observed RED->GREEN: cleanup dependency inventory initially failed because unreachable integration classes still existed; after exact deletion and reference pruning it passes. Retired reset-tool CLI cases were removed from the comparer test; malformed input of the retained inspector correctly uses its existing safe generic error, not the authorization-mismatch code. No production error behavior was changed to satisfy that assertion.

Current logical structure: app/{Backup,Import,Database,Http,Support,Restore(shared History utilities only)}, config/, public/assets/, views/, tests/, tools/verification/, docs/, existing storage/. No new framework/dependency/container, no move/rename/migration of runtime data. No full regression, SQL/client execution, Windows/service/trust/ACL mutation, new resources or Git mutation. Source tracked by Git can be recovered from existing history; ignored generated outputs can be rebuilt from the prior revision. Deleted untracked obsolete plans/reset wrappers were explicitly inventoried; they are not promised recoverable from Git.

## Exact retained-file inventory (104 application/source/test/doc/tool files)

- `app/bootstrap.php`
- `app/Support/Auth.php`
- `app/Support/BackupPathPolicy.php`
- `app/Support/Config.php`
- `app/Support/DownloadAuthorization.php`
- `app/Support/Flash.php`
- `app/Support/Logger.php`
- `app/Support/Request.php`
- `app/Support/RuntimeDataBootstrap.php`
- `app/Support/RuntimeDataPaths.php`
- `app/Support/Security.php`
- `app/Support/Session.php`
- `app/Support/View.php`
- `app/Restore/BackupProvenanceIndex.php`
- `app/Restore/RestoreArtifactValidator.php`
- `app/Import/EffectiveSchemaComparison.php`
- `app/Import/ImportAccess.php`
- `app/Import/ImportArtifactVerifier.php`
- `app/Import/ImportClient.php`
- `app/Import/ImportDatabase.php`
- `app/Import/ImportJobStore.php`
- `app/Import/ImportPolicy.php`
- `app/Import/ImportResultPresentation.php`
- `app/Import/ImportRunner.php`
- `app/Import/MysqliImportDatabase.php`
- `app/Import/MysqlImportClient.php`
- `app/Http/Router.php`
- `app/Http/Controller/BackupController.php`
- `app/Http/Controller/SettingsController.php`
- `app/Database/AuthorizedSourcePolicy.php`
- `app/Database/ConnectionFactory.php`
- `app/Database/DatabaseOperationLock.php`
- `app/Database/DatabaseRepository.php`
- `app/Database/FileDatabaseOperationLock.php`
- `app/Backup/BackupArtifactPublisher.php`
- `app/Backup/BackupExecutionGuard.php`
- `app/Backup/BackupJobAccess.php`
- `app/Backup/BackupJobRequestException.php`
- `app/Backup/BackupJobRunner.php`
- `app/Backup/BackupJobStore.php`
- `app/Backup/BackupOperation.php`
- `app/Backup/BackupService.php`
- `app/Backup/DumpEngine.php`
- `app/Backup/MysqldumpCommand.php`
- `app/Backup/MysqldumpEngine.php`
- `app/Backup/MysqldumpProcess.php`
- `app/Backup/MysqliBackupOperation.php`
- `app/Backup/TemporaryMysqlCredentials.php`
- `public/download.php`
- `public/.htaccess`
- `public/import-job.php`
- `public/index.php`
- `public/job.php`
- `public/assets/app.css`
- `public/assets/app.js`
- `public/assets/backup-jobs.js`
- `public/assets/local-import.js`
- `views/layout.php`
- `views/partials/navigation.php`
- `views/pages/backup.php`
- `views/pages/dashboard.php`
- `views/pages/history.php`
- `views/pages/import.php`
- `views/pages/placeholder.php`
- `views/pages/settings.php`
- `config/app.php`
- `config/local.php.example`
- `tools/provision-local-import.php`
- `tools/verify-existing-import.php`
- `tools/verification/LocalImportAcceptance.php`
- `tests/bootstrap.php`
- `tests/CleanupDependencyTest.php`
- `tests/ImportSchemaComparisonTest.php`
- `tests/LocalImportAcceptanceTest.php`
- `tests/LocalImportArtifactTest.php`
- `tests/LocalImportRunnerTest.php`
- `tests/LocalImportTest.php`
- `tests/LocalImportUiTest.php`
- `tests/LocalManagerPresentationTest.php`
- `tests/ManualBackupSourcePolicyTest.php`
- `tests/MysqldumpBackupTest.php`
- `tests/MysqldumpServiceTest.php`
- `tests/Phase2ArtifactPublisherTest.php`
- `tests/Phase2JobApiAndUiTest.php`
- `tests/Phase2JobRunnerTest.php`
- `tests/Phase2JobStoreAndGuardTest.php`
- `tests/Phase4UiTest.php`
- `tests/RoutingAndViewTest.php`
- `tests/run.php`
- `tests/RuntimeDataPathsTest.php`
- `tests/SecurityFoundationTest.php`
- `tests/SharedDatabaseOperationLockTest.php`
- `tests/StorageAndDownloadTest.php`
- `tests/js/local-import-presentation.test.js`
- `docs/LOCAL_IMPORT_ACCEPTANCE_2026-10-19.md`
- `docs/LOCAL_MYSQL_MANAGER.md`
- `docs/LOCAL_USER_GUIDE.md`
- `docs/PROJECT_CLEANUP.md`
- `.gitattributes`
- `.gitignore`
- `.htaccess`
- `index.php`
- `README.md`
- `TEST_BASELINE.md`

The inventory excludes protected config/local.php and all protected storage files, which are retained separately. Native/build/deployment contents are no longer present. The small reserved legacy runtime-directory mappings remain only for backwards-compatible path configuration; they do not instantiate an obsolete Restore framework. Auth compatibility methods continue returning false for configured-login attempts; no website password or login module remains active.

## Git diff --stat (tracked working tree, not cleanup-only)

Includes pre-existing uncommitted development changes and excludes untracked additions/ignored outputs. This is not a statement that all listed engine diffs were made during cleanup. No staging/commit/push/history rewrite performed. Captured before final evidence-only baseline append; baseline line counts may grow as this record is finalized.

```text
 .htaccess                                          |   2 +-
 PHASE1_AUDIT.md                                    |  67 --
 PHASE1_DESIGN.md                                   | 187 -----
 PHASE2_DESIGN.md                                   | 117 ---
 PHASE3_DESIGN.md                                   | 156 ----
 PHASE4_DESIGN.md                                   | 115 ---
 README.md                                          | 175 +---
 RESTORE_INTEGRATION_DESIGN.md                      | 395 ---------
 RESTORE_INTEGRATION_IMPLEMENTATION_PLAN.md         | 606 --------------
 TEST_BASELINE.md                                   | 277 ++++++-
 app/Backup/BackupJobAccess.php                     |  11 +-
 app/Backup/BackupJobStore.php                      |  14 +
 app/Backup/BackupService.php                       | 885 +++------------------
 app/Backup/MysqliBackupOperation.php               |  37 +-
 app/Database/DatabaseRepository.php                |  27 +-
 app/Deployment/DedicatedApacheConfigValidator.php  | 159 ----
 app/Deployment/SourcePackageBuilder.php            | 340 --------
 app/Http/Controller/AuthController.php             |  25 -
 app/Http/Controller/BackupController.php           |  53 +-
 app/Http/Router.php                                |   3 +-
 app/Restore/DatabaseOperationLockContract.php      |  16 -
 .../Attestation/LiveRestoreAttestationGate.php     |  53 --
 .../Attestation/MariaDbPrivilegeDialect.php        |   9 -
 .../Attestation/MySqlPrivilegeDialect.php          |  37 -
 .../Attestation/PrivilegeAttestationService.php    |  42 -
 .../Integration/Attestation/PrivilegeAttestor.php  |  83 --
 .../Integration/Attestation/PrivilegeDialect.php   |  11 -
 .../Integration/Attestation/PrivilegeEvidence.php  |  22 -
 .../Integration/Attestation/ServerIdentity.php     |  30 -
 .../Attestation/ServerIdentityProbe.php            |  25 -
 .../Authorization/ExecutionAuthorization.php       |  16 -
 .../Authorization/LiveAuthorizationReceipt.php     |  65 --
 .../LiveAuthorizationReceiptStore.php              |  69 --
 .../Database/AuthorizedSourceCatalog.php           |  26 -
 .../Database/IntegrationBackupOperation.php        |  29 -
 .../Integration/Database/MetadataConnection.php    |  19 -
 .../Database/MysqliRestoreConnection.php           | 232 ------
 .../Database/OfflineRestoreConnection.php          |  10 -
 .../Integration/Database/RestoreConnection.php     |  25 -
 .../Database/RestoreConnectionFactory.php          |  32 -
 .../Integration/Execution/CleanupCoordinator.php   |  39 -
 .../Integration/Execution/ExecutionResult.php      |  13 -
 .../Execution/RestoreIntegrationRunner.php         | 168 ----
 .../Execution/TargetDatabaseProvisioner.php        |  55 --
 .../Execution/TargetProvisioningException.php      |  23 -
 .../Integration/Execution/TypedRestoreExecutor.php |  28 -
 .../Http/RestoreIntegrationApiAuthorization.php    |  21 -
 .../Job/RestoreIntegrationJobAccess.php            |  26 -
 .../Job/RestoreIntegrationJobException.php         |  11 -
 .../Integration/Job/RestoreIntegrationJobGuard.php |  28 -
 .../Integration/Job/RestoreIntegrationJobStore.php | 133 ----
 .../Integration/Job/RestoreIntegrationState.php    |  22 -
 .../Integration/Ownership/CleanupDecision.php      |  14 -
 .../Ownership/CleanupDecisionEngine.php            |  36 -
 .../Integration/Ownership/OwnershipEvent.php       |  25 -
 .../Integration/Ownership/OwnershipJournal.php     | 113 ---
 .../Integration/Ownership/OwnershipMarker.php      |  32 -
 .../Integration/Ownership/OwnershipSnapshot.php    |  25 -
 .../Policy/RestoreIntegrationPolicy.php            |  36 -
 app/Restore/Integration/Policy/TrustedRunId.php    |  18 -
 .../Integration/Policy/TrustedTargetName.php       |  20 -
 .../Provisioning/CredentialProvisioningPlan.php    | 111 ---
 .../Integration/Readiness/ReadinessAssessment.php  |  59 --
 .../Integration/Readiness/ReadinessEvidence.php    |  89 ---
 .../RestoreIntegrationReadinessChecker.php         | 280 -------
 .../Secrets/AtomicFileSecretEnvelopeStore.php      |  87 --
 .../Secrets/FileSecretEnvelopeStore.php            |  20 -
 .../Integration/Secrets/NativeDpapiHelper.php      | 129 ---
 .../Secrets/NativeHelperDeploymentVerifier.php     |  10 -
 .../Secrets/NativeSecretEnvelopeStore.php          |  15 -
 .../Secrets/ProcOpenWorkerProcessRunner.php        |  52 --
 .../ProductionNativeHelperDeploymentVerifier.php   |  37 -
 .../Secrets/RuntimeIdentityProvider.php            |   4 -
 .../Integration/Secrets/SecretEnvelopeCodec.php    | 133 ----
 .../Integration/Secrets/SecretEnvelopeStore.php    |  10 -
 .../Integration/Secrets/SecretProtector.php        |   8 -
 .../Secrets/SecretProviderException.php            |  11 -
 app/Restore/Integration/Secrets/SecretRole.php     |  11 -
 .../Integration/Secrets/SecretServerBinding.php    |  33 -
 app/Restore/Integration/Secrets/SecretValue.php    |  45 --
 .../Integration/Secrets/WindowsAclHardener.php     |   9 -
 .../Integration/Secrets/WindowsIdentity.php        |  30 -
 .../Integration/Secrets/WindowsSecretProvider.php  |  57 --
 .../Integration/Secrets/WorkerProcessResult.php    |   7 -
 .../Integration/Secrets/WorkerProcessRunner.php    |   8 -
 .../Sql/AbstractExecutableStatement.php            |  17 -
 .../Integration/Sql/CreateTableEvidenceParser.php  | 123 ---
 .../Integration/Sql/ExecutableRestoreStatement.php |  11 -
 .../Integration/Sql/ExecutableStatementPolicy.php  |  66 --
 .../Integration/Sql/FileStatementPayload.php       |  16 -
 .../Integration/Sql/InlineStatementPayload.php     |  11 -
 app/Restore/Integration/Sql/InsertRowsParser.php   | 104 ---
 app/Restore/Integration/Sql/ParsedInsertRows.php   |  21 -
 app/Restore/Integration/Sql/RestorePlan.php        |  34 -
 .../Integration/Sql/RestorePlanCompiler.php        | 195 -----
 app/Restore/Integration/Sql/RestorePlanItem.php    |  10 -
 .../Integration/Sql/RestoreStatementKind.php       |  17 -
 .../Integration/Sql/Statement/AlterTable.php       |  13 -
 .../Sql/Statement/CreatePlaceholderView.php        |  13 -
 .../Integration/Sql/Statement/CreateTable.php      |  16 -
 .../Integration/Sql/Statement/DropObject.php       |  13 -
 .../Integration/Sql/Statement/InsertRows.php       |  23 -
 .../Integration/Sql/Statement/LockTables.php       |  13 -
 .../Integration/Sql/Statement/SessionDirective.php |  13 -
 .../Sql/Statement/SourceDatabaseDeclaration.php    |  14 -
 .../Sql/Statement/SourceDatabaseSelection.php      |  14 -
 .../Integration/Sql/Statement/UnlockTables.php     |  13 -
 app/Restore/Integration/Sql/StatementPayload.php   |  10 -
 .../Integration/Sql/StatementSpoolLease.php        |  40 -
 .../Integration/Sql/StatementSpoolWriter.php       |  47 --
 .../Sql/ValidatedExecutableStatement.php           |  27 -
 .../Validation/PostRestoreValidator.php            | 133 ----
 .../Integration/Validation/RowCanonicalizer.php    |  43 -
 .../Validation/RowEvidenceAccumulator.php          | 139 ----
 .../Validation/RowMultisetComparator.php           |  28 -
 .../Integration/Validation/SchemaComparator.php    |  29 -
 .../Validation/StoredObjectComparator.php          |  15 -
 .../Integration/Validation/TableDataEvidence.php   |  23 -
 .../Integration/Validation/TypedSqlValue.php       |  33 -
 .../Integration/Validation/ValidationReport.php    |  19 -
 app/Restore/RestoreArtifactValidator.php           |  65 +-
 app/Restore/RestoreExecutionGuard.php              |  59 --
 app/Restore/RestoreJobAccess.php                   |  92 ---
 app/Restore/RestoreJobRequestException.php         |  20 -
 app/Restore/RestoreJobStore.php                    | 209 -----
 app/Restore/RestorePreflightRunner.php             | 137 ----
 app/Restore/RestoreSafetyGate.php                  |  23 -
 app/Restore/RestoreTargetValidator.php             |  29 -
 app/Restore/Sql/LexedStatement.php                 |  21 -
 app/Restore/Sql/SqlFileByteReader.php              |  51 --
 app/Restore/Sql/SqlStatementLexer.php              | 230 ------
 app/Restore/SqlStructureValidator.php              |  11 -
 app/Restore/StructuralSqlParser.php                | 396 ---------
 app/Support/Auth.php                               |  35 +-
 config/app.php                                     |  10 -
 config/local.php.example                           |   6 +-
 deployment/dedicated-apache/conf/httpd.conf        |  79 --
 .../dedicated-apache/deployment-contract.json      |  39 -
 deployment/dedicated-apache/php/php.ini            |  58 --
 deployment/dedicated-apache/source-allowlist.txt   | 167 ----
 deployment/dedicated-apache/source-manifest.json   | 841 --------------------
 docs/DEDICATED_APACHE_DEPLOYMENT_PLAN.md           | 123 ---
 ...ORE_INTEGRATION_CREDENTIAL_PROVISIONING_PLAN.md | 178 -----
 docs/RESTORE_INTEGRATION_LIVE_RUNBOOK.md           | 162 ----
 .../WINDOWS_SECRET_PROVIDER_DEPLOYMENT_DECISION.md |  71 --
 docs/WINDOWS_SECRET_PROVIDER_DESIGN.md             | 201 -----
 ...WINDOWS_SECRET_PROVIDER_DOTNET_HELPER_DESIGN.md |  51 --
 .../WINDOWS_SECRET_PROVIDER_IMPLEMENTATION_PLAN.md |  18 -
 docs/WINDOWS_SECRET_PROVIDER_NATIVE_DEPLOYMENT.md  |  66 --
 ...ECRET_PROVIDER_SIGNED_DEPLOYMENT_PREPARATION.md |  51 --
 .../plans/2026-10-16-phase1-foundation.md          | 139 ----
 .../plans/2026-10-16-phase2-backup-jobs.md         |  80 --
 .../plans/2026-10-17-phase3-safe-restore.md        |  93 ---
 .../plans/2026-10-17-phase4-modern-ui.md           | 136 ----
 .../plans/2026-10-17-seamless-editorial-layout.md  | 122 ---
 .../2026-10-18-dedicated-apache-side-by-side.md    |  78 --
 .../plans/2026-10-18-native-dotnet-dpapi-helper.md |  49 --
 ...-native-helper-signed-deployment-preparation.md |  25 -
 .../plans/2026-10-18-windows-secret-provider.md    | 135 ----
 .../2026-10-17-seamless-editorial-layout-design.md | 107 ---
 ...6-10-18-dedicated-apache-side-by-side-design.md |  41 -
 global.json                                        |   7 -
 .../AclPathPolicyTests.cs                          |  25 -
 .../ProtocolParserTests.cs                         |  47 --
 .../WindowsSecretHelper.Tests.csproj               |  25 -
 .../WindowsSecretHelper.Tests/packages.lock.json   | 197 -----
 native/WindowsSecretHelper/AclPathPolicy.cs        |  67 --
 native/WindowsSecretHelper/Program.cs              |  47 --
 native/WindowsSecretHelper/Protocol.cs             | 163 ----
 .../WindowsSecretHelper/WindowsSecretHelper.csproj |  25 -
 native/WindowsSecretHelper/WorkerServices.cs       | 111 ---
 native/WindowsSecretHelper/packages.lock.json      |  33 -
 public/assets/app.css                              |  25 +-
 public/assets/restore-integration.js               |  12 -
 public/assets/restore-jobs.js                      |  96 ---
 public/download.php                                |   2 +-
 public/index.php                                   | 157 +---
 public/job.php                                     |   9 +-
 public/restore-integration.php                     |  50 --
 public/restore-job.php                             | 125 ---
 tests/DedicatedApacheDeploymentPreparationTest.php | 153 ----
 tests/DedicatedSourcePackageTest.php               | 145 ----
 tests/NativeHelperDeploymentPreparationTest.php    | 186 -----
 tests/Phase2JobApiAndUiTest.php                    |   4 +-
 tests/Phase2JobRunnerTest.php                      |  20 +-
 tests/Phase2JobStoreAndGuardTest.php               |   8 +-
 tests/Phase3ApiAndUiTest.php                       |  70 --
 tests/Phase3ArtifactValidationTest.php             | 145 ----
 tests/Phase3RestoreJobTest.php                     | 167 ----
 tests/Phase3SqlSafetyTest.php                      | 109 ---
 tests/Phase4UiTest.php                             |  64 +-
 tests/RestoreIntegrationApiAndUiTest.php           |  95 ---
 ...estoreIntegrationCredentialProvisioningTest.php | 142 ----
 tests/RestoreIntegrationExecutorTest.php           | 297 -------
 tests/RestoreIntegrationJobTest.php                |  78 --
 .../RestoreIntegrationOwnershipAndCleanupTest.php  |  75 --
 .../RestoreIntegrationPolicyAndAttestationTest.php | 178 -----
 tests/RestoreIntegrationReadinessTest.php          | 209 -----
 tests/RestoreIntegrationSqlPlanTest.php            | 120 ---
 tests/RestoreIntegrationValidationTest.php         | 112 ---
 tests/RoutingAndViewTest.php                       |   8 +-
 tests/SecurityFoundationTest.php                   |  13 +-
 tests/WindowsNativeSecretHelperProtocolTest.php    |  92 ---
 tests/WindowsNativeSecretHelperTest.php            |  98 ---
 tests/WindowsSecretEnvelopeTest.php                | 124 ---
 tests/WindowsSecretProviderTest.php                |  90 ---
 tests/WindowsSecretStorageTest.php                 | 108 ---
 tests/WindowsSecretWorkerProtocolTest.php          |  75 --
 tests/WindowsSecretWorkerTest.php                  |  26 -
 tests/apache-deployment-smoke.ps1                  | 103 ---
 tests/dashboard-responsive.test.js                 | 145 ----
 tests/dedicated-apache-syntax.ps1                  |  63 --
 tests/phase-restore-integration-ui.test.js         |  10 -
 tests/phase4-restore-polling.test.js               |  67 --
 tools/check-database-operation-lock-migration.php  |  26 -
 tools/dedicated-apache/build-source-package.php    |  42 -
 tools/dedicated-apache/deployment-preflight.ps1    | 107 ---
 .../validate-preflight-evidence.ps1                |  84 --
 tools/generate-admin-credentials.php               |  21 -
 tools/native-helper/NativeHelperDeployment.psm1    | 144 ----
 tools/native-helper/build-release.ps1              |  27 -
 tools/native-helper/deployment-preflight.ps1       |  44 -
 tools/native-helper/new-deployment-plan.ps1        |  46 --
 tools/native-helper/show-signing-plan.ps1          |  12 -
 .../native-helper/validate-deployment-evidence.ps1 |  19 -
 tools/native-helper/verify-deployed-helper.ps1     |  31 -
 tools/native-helper/verify-runtime-identity.ps1    |  62 --
 tools/native-helper/verify-signed-artifact.ps1     |  18 -
 tools/restore-integration-credential-plan.php      |  61 --
 tools/restore-integration-readiness.php            |  39 -
 tools/verify-restore-integration-offline.ps1       |  52 --
 tools/windows-dpapi-worker.ps1                     |  75 --
 tools/windows-secret-identity.php                  |  36 -
 views/layout.php                                   |   5 +-
 views/pages/backup.php                             |   9 +-
 views/pages/dashboard.php                          |  16 +-
 views/pages/history.php                            |   9 +-
 views/pages/login.php                              |  29 -
 views/pages/restore-integration.php                |  29 -
 views/pages/restore.php                            |  86 --
 views/pages/settings.php                           |   7 +-
 views/partials/navigation.php                      |  11 +-
 242 files changed, 634 insertions(+), 17638 deletions(-)
```
