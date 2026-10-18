$ErrorActionPreference = 'Stop'

function Write-Result([bool]$Ready, [string]$Code, [string[]]$Blockers) {
    [ordered]@{
        ready = $Ready
        code = $Code
        blockers = @($Blockers)
    } | ConvertTo-Json -Depth 5 -Compress
    exit $(if ($Ready) { 0 } else { 9 })
}

try {
    $raw = [Console]::In.ReadToEnd()
    if ([Text.Encoding]::UTF8.GetByteCount($raw) -gt 65536) {
        Write-Result $false 'evidence_input_too_large' @('evidence_input_too_large')
    }
    $evidence = $raw | ConvertFrom-Json -Depth 8
} catch {
    Write-Result $false 'evidence_input_invalid' @('evidence_input_invalid')
}

$requiredFields = @(
    'schema', 'listener', 'service_name', 'runtime_account', 'runtime_sid', 'server_identity', 'paths',
    'path_collisions', 'path_chain_ntfs', 'path_chain_reparse_free', 'port_available',
    'source_manifest_trusted', 'source_integrity_verified', 'runtime_data_tree_ready',
    'runtime_path_policy_verified', 'account_exists', 'account_sid_matches', 'account_non_admin',
    'service_logon_granted', 'apache_config_valid', 'php_config_valid', 'shared_laragon_unchanged'
)
$actualFields = @($evidence.PSObject.Properties.Name)
if (@($requiredFields | Where-Object { $_ -notin $actualFields }).Count -ne 0 -or
    @($actualFields | Where-Object { $_ -notin $requiredFields }).Count -ne 0) {
    Write-Result $false 'evidence_shape_invalid' @('evidence_shape_invalid')
}

$expectedPaths = [ordered]@{
    application_root = 'C:\Program Files\BackupPhpMyAdmin\Application'
    apache_root = 'C:\Program Files\BackupPhpMyAdmin\Runtime\Apache'
    php_root = 'C:\Program Files\BackupPhpMyAdmin\Runtime\PHP'
    runtime_data_root = 'C:\ProgramData\BackupPhpMyAdmin\RuntimeData'
}
$blockers = [Collections.Generic.List[string]]::new()

if ($evidence.schema -ne 1) { $blockers.Add('evidence_schema_invalid') }
if ($evidence.listener -cne '127.0.0.1:8788') { $blockers.Add('listener_mismatch') }
if ($evidence.service_name -cne 'BackupPhpMyAdminApache') { $blockers.Add('service_name_mismatch') }
if ($evidence.runtime_account -cne '.\BackupPhpRuntime') { $blockers.Add('runtime_account_mismatch') }
if ($evidence.runtime_sid -notmatch '^S-1-5-21-(?:\d+-){3}\d+$') { $blockers.Add('runtime_sid_invalid') }
if (-not ($evidence.server_identity -is [string]) -or [string]::IsNullOrWhiteSpace($evidence.server_identity)) {
    $blockers.Add('server_identity_missing')
}
foreach ($entry in $expectedPaths.GetEnumerator()) {
    if ($null -eq $evidence.paths -or $evidence.paths.($entry.Key) -cne $entry.Value) {
        $blockers.Add('deployment_path_mismatch')
        break
    }
}

$checks = [ordered]@{
    path_collisions = @($false, 'path_collision')
    path_chain_ntfs = @($true, 'path_filesystem_invalid')
    path_chain_reparse_free = @($true, 'path_reparse_point')
    port_available = @($true, 'port_unavailable')
    source_manifest_trusted = @($true, 'source_manifest_untrusted')
    source_integrity_verified = @($true, 'source_integrity_unverified')
    runtime_data_tree_ready = @($true, 'runtime_data_tree_missing')
    runtime_path_policy_verified = @($true, 'runtime_path_policy_unverified')
    account_exists = @($true, 'runtime_account_missing')
    account_sid_matches = @($true, 'runtime_sid_mismatch')
    account_non_admin = @($true, 'runtime_account_privileged')
    service_logon_granted = @($true, 'service_logon_missing')
    apache_config_valid = @($true, 'apache_config_invalid')
    php_config_valid = @($true, 'php_config_invalid')
    shared_laragon_unchanged = @($true, 'shared_laragon_change_detected')
}
foreach ($check in $checks.GetEnumerator()) {
    if ($evidence.($check.Key) -ne $check.Value[0]) {
        $blockers.Add([string]$check.Value[1])
    }
}

if ($blockers.Count -ne 0) {
    Write-Result $false $blockers[0] $blockers.ToArray()
}
Write-Result $true 'ready' @()
