param()
$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$project = Join-Path $projectRoot 'native\WindowsSecretHelper\WindowsSecretHelper.csproj'
$staging = Join-Path $env:LOCALAPPDATA 'BackupPhpMyAdmin\BuildArtifacts\native-helper\win-x64'
$dotnet = 'C:\Program Files\dotnet\dotnet.exe'
if (-not (Test-Path -LiteralPath $dotnet -PathType Leaf)) { throw 'dotnet_unavailable' }
$sdkVersion = (& $dotnet --version).Trim()
if ($LASTEXITCODE -ne 0 -or $sdkVersion -ne '10.0.402') { throw 'dotnet_sdk_mismatch' }
if (Test-Path -LiteralPath $staging) { throw 'staging_collision' }
[void][System.IO.Directory]::CreateDirectory($staging)
& $dotnet restore $project --locked-mode --runtime win-x64 --nologo
if ($LASTEXITCODE -ne 0) { throw 'restore_failed' }
& $dotnet publish $project --configuration Release --runtime win-x64 --self-contained true --no-restore --nologo --output $staging -p:ContinuousIntegrationBuild=true -p:Deterministic=true -p:PublishSingleFile=true -p:PublishTrimmed=false -p:PublishAot=false -p:DebugType=embedded
if ($LASTEXITCODE -ne 0) { throw 'publish_failed' }
$artifact = Join-Path $staging 'backup-secret-helper.exe'
if (-not (Test-Path -LiteralPath $artifact -PathType Leaf)) { throw 'artifact_missing' }
$unexpected = @(Get-ChildItem -LiteralPath $staging -File | Where-Object { $_.Name -ne 'backup-secret-helper.exe' })
if ($unexpected.Count -ne 0) { throw 'single_file_contract_failed' }
[ordered]@{
    mode = 'offline_build_only'
    sdk = $sdkVersion
    runtime = 'win-x64'
    artifact = $artifact
    sha256_unsigned = (Get-FileHash -LiteralPath $artifact -Algorithm SHA256).Hash
    production_authorized = $false
} | ConvertTo-Json -Compress
