param(
    [string]$ExpectedRuntimeSid = '',
    [string]$SourceRoot = '',
    [string]$SourceManifestPath = '',
    [string]$ExpectedManifestSha256 = ''
)

$ErrorActionPreference = 'Stop'
$fixedPaths = [ordered]@{
    application_root = 'C:\Program Files\BackupPhpMyAdmin\Application'
    apache_root = 'C:\Program Files\BackupPhpMyAdmin\Runtime\Apache'
    php_root = 'C:\Program Files\BackupPhpMyAdmin\Runtime\PHP'
    runtime_data_root = 'C:\ProgramData\BackupPhpMyAdmin\RuntimeData'
}
$runtimeChildren = @(
    'backups', 'jobs', 'logs', 'restore-jobs', 'restore-integration-jobs',
    'restore-integration-ownership', 'database-operations', 'temporary', 'sessions'
)

function Test-PathChain([string]$Path) {
    $current = [IO.Path]::GetPathRoot($Path)
    $ntfs = $true
    $reparseFree = $true
    foreach ($segment in $Path.Substring($current.Length).Split([IO.Path]::DirectorySeparatorChar, [StringSplitOptions]::RemoveEmptyEntries)) {
        $current = Join-Path $current $segment
        if (-not (Test-Path -LiteralPath $current)) { break }
        $item = Get-Item -LiteralPath $current -Force
        if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { $reparseFree = $false }
        $drive = Get-Volume -DriveLetter $item.PSDrive.Name -ErrorAction SilentlyContinue
        if ($null -eq $drive -or $drive.FileSystem -cne 'NTFS') { $ntfs = $false }
    }
    return @($ntfs, $reparseFree)
}

function Test-SourceManifest([string]$Root, [string]$Manifest, [string]$ExpectedHash) {
    if ($Root -eq '' -or $Manifest -eq '' -or $ExpectedHash -notmatch '^[A-Fa-f0-9]{64}$' -or
        -not (Test-Path -LiteralPath $Root -PathType Container) -or
        -not (Test-Path -LiteralPath $Manifest -PathType Leaf)) { return @($false, $false) }
    if ((Get-FileHash -LiteralPath $Manifest -Algorithm SHA256).Hash -cne $ExpectedHash.ToUpperInvariant()) {
        return @($false, $false)
    }
    try { $entries = Get-Content -Raw -LiteralPath $Manifest | ConvertFrom-Json -Depth 6 } catch { return @($false, $false) }
    if ($entries.schema -ne 1 -or $entries.files -isnot [array]) { return @($false, $false) }
    foreach ($entry in $entries.files) {
        if ($entry.path -notmatch '^[A-Za-z0-9._/-]+$' -or $entry.sha256 -notmatch '^[A-Fa-f0-9]{64}$') { return @($true, $false) }
        $candidate = [IO.Path]::GetFullPath((Join-Path $Root ([string]$entry.path)))
        $canonicalRoot = [IO.Path]::GetFullPath($Root).TrimEnd('\') + '\'
        if (-not $candidate.StartsWith($canonicalRoot, [StringComparison]::OrdinalIgnoreCase) -or
            -not (Test-Path -LiteralPath $candidate -PathType Leaf) -or
            (Get-FileHash -LiteralPath $candidate -Algorithm SHA256).Hash -cne ([string]$entry.sha256).ToUpperInvariant()) {
            return @($true, $false)
        }
    }
    return @($true, $true)
}

$pathNtfs = $true
$pathReparseFree = $true
foreach ($path in $fixedPaths.Values) {
    $chain = Test-PathChain $path
    $pathNtfs = $pathNtfs -and $chain[0]
    $pathReparseFree = $pathReparseFree -and $chain[1]
}
$pathCollisions = @($fixedPaths.Values | Where-Object { Test-Path -LiteralPath $_ }).Count -ne 0
$portInUse = $null -ne (Get-NetTCPConnection -LocalAddress '127.0.0.1' -LocalPort 8788 -ErrorAction SilentlyContinue | Select-Object -First 1)

$account = Get-LocalUser -Name 'BackupPhpRuntime' -ErrorAction SilentlyContinue
$accountExists = $null -ne $account
$actualSid = if ($accountExists) { $account.SID.Value } else { '' }
$sidMatches = $accountExists -and $ExpectedRuntimeSid -match '^S-1-5-21-(?:\d+-){3}\d+$' -and $actualSid -ceq $ExpectedRuntimeSid
$administratorMember = $false
if ($accountExists) {
    $administratorMember = $null -ne (Get-LocalGroupMember -SID 'S-1-5-32-544' -ErrorAction SilentlyContinue |
        Where-Object { $_.SID.Value -ceq $actualSid } | Select-Object -First 1)
}

$manifest = Test-SourceManifest $SourceRoot $SourceManifestPath $ExpectedManifestSha256
$runtimeReady = (Test-Path -LiteralPath $fixedPaths.runtime_data_root -PathType Container) -and
    @($runtimeChildren | Where-Object { -not (Test-Path -LiteralPath (Join-Path $fixedPaths.runtime_data_root $_) -PathType Container) }).Count -eq 0

$evidence = [ordered]@{
    schema = 1
    listener = '127.0.0.1:8788'
    service_name = 'BackupPhpMyAdminApache'
    runtime_account = '.\BackupPhpRuntime'
    runtime_sid = $(if ($actualSid -ne '') { $actualSid } else { $ExpectedRuntimeSid })
    server_identity = $env:COMPUTERNAME
    paths = $fixedPaths
    path_collisions = $pathCollisions
    path_chain_ntfs = $pathNtfs
    path_chain_reparse_free = $pathReparseFree
    port_available = -not $portInUse
    source_manifest_trusted = $manifest[0]
    source_integrity_verified = $manifest[1]
    runtime_data_tree_ready = $runtimeReady
    runtime_path_policy_verified = $runtimeReady -and $pathNtfs -and $pathReparseFree
    account_exists = $accountExists
    account_sid_matches = $sidMatches
    account_non_admin = $accountExists -and -not $administratorMember
    service_logon_granted = $false
    apache_config_valid = $false
    php_config_valid = $false
    shared_laragon_unchanged = $false
}

$evidence | ConvertTo-Json -Depth 7
exit 9
