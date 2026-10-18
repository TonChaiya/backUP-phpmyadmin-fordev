param(
    [string]$Php = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
)

$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
Set-Location -LiteralPath $root

$filters = @(
    'RestoreIntegrationSqlPlanTest',
    'RestoreIntegrationPolicyAndAttestationTest',
    'RestoreIntegrationOwnershipAndCleanupTest',
    'SharedDatabaseOperationLockTest',
    'RestoreIntegrationJobTest',
    'RestoreIntegrationExecutorTest',
    'RestoreIntegrationValidationTest',
    'RestoreIntegrationApiAndUiTest',
    'Phase3SqlSafetyTest',
    'Phase2JobStoreAndGuardTest',
    'Phase2JobRunnerTest',
    'RoutingAndViewTest',
    'SecurityFoundationTest'
)

foreach ($filter in $filters) {
    & $Php tests/run.php $filter
    if ($LASTEXITCODE -ne 0) { throw "Test filter failed: $filter" }
}

node tests/phase-restore-integration-ui.test.js
if ($LASTEXITCODE -ne 0) { throw 'Restore Integration JavaScript test failed' }

$phpFiles = @(
    Get-ChildItem app/Restore/Integration,app/Restore/Sql,app/Database -Filter '*.php' -Recurse
    Get-Item app/Restore/StructuralSqlParser.php,app/Restore/DatabaseOperationLockContract.php,app/Backup/BackupExecutionGuard.php,app/bootstrap.php,app/Http/Router.php,app/Support/Auth.php,public/index.php,public/job.php,public/restore-integration.php,views/pages/restore-integration.php
)
foreach ($file in $phpFiles) {
    & $Php -l $file.FullName | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "PHP lint failed: $($file.FullName)" }
}

node --check public/assets/restore-integration.js
if ($LASTEXITCODE -ne 0) { throw 'JavaScript syntax check failed' }

$productionFiles = Get-ChildItem app/Restore/Integration,app/Restore/Sql -Filter '*.php' -Recurse
$forbidden = Select-String -Path $productionFiles.FullName -Pattern '->multi_query|SHOW\s+DATABASES|DROP\s+DATABASE\s+IF\s+EXISTS|shell_exec|proc_open|mysql(?:\.exe)?\s+-'
if ($forbidden) {
    $forbidden | ForEach-Object { Write-Error "$($_.Path):$($_.LineNumber): $($_.Line.Trim())" }
    throw 'Forbidden API/SQL scan failed'
}

Write-Output 'OFFLINE RESTORE INTEGRATION VERIFICATION PASSED'
