param([Parameter(Mandatory=$true, Position=0)][string]$Operation)
$ErrorActionPreference = 'Stop'
$allowed = @('identity','protect','unprotect','harden-directory','harden-file')
function Reply([hashtable]$Value, [int]$Code = 0) { [Console]::Out.Write(($Value | ConvertTo-Json -Compress)); exit $Code }
function Fail([string]$Code) { Reply @{ok=$false;code=$Code} 9 }
function Identity {
    $id = [System.Security.Principal.WindowsIdentity]::GetCurrent()
    if ($null -eq $id -or $null -eq $id.User -or [string]::IsNullOrWhiteSpace($id.Name)) { Fail 'windows_identity_unavailable' }
    return @{ account=$id.Name; sid=$id.User.Value }
}
function Request([string[]]$Names) {
    $text = [Console]::In.ReadToEnd()
    if ($text.Length -gt 16384) { Fail 'worker_input_invalid' }
    try { $value = $text | ConvertFrom-Json } catch { Fail 'worker_input_invalid' }
    $actual = @($value.PSObject.Properties.Name | Where-Object { $null -ne $_ })
    if (($actual.Count -ne $Names.Count) -or (@($actual | Where-Object { $_ -notin $Names }).Count -ne 0)) { Fail 'worker_input_invalid' }
    return $value
}
function AssertSid($Request) {
    $current = Identity
    if (-not [string]::Equals([string]$Request.expected_sid, [string]$current.sid, [System.StringComparison]::Ordinal)) { Fail 'windows_identity_mismatch' }
    return $current
}
function AssertPrivatePath([string]$Path, [bool]$DirectoryOnly) {
    if ([string]::IsNullOrWhiteSpace($Path) -or -not [IO.Path]::IsPathFullyQualified($Path)) { Fail 'worker_input_invalid' }
    $projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
    $storage = [IO.Path]::GetFullPath((Join-Path $projectRoot 'storage'))
    $private = [IO.Path]::GetFullPath((Join-Path $storage 'private'))
    $allowed = [IO.Path]::GetFullPath((Join-Path $private 'restore-integration-secrets'))
    $full = [IO.Path]::GetFullPath($Path)
    foreach ($part in @($storage,$private,$allowed)) {
        if ([IO.Directory]::Exists($part) -and (([IO.File]::GetAttributes($part) -band [IO.FileAttributes]::ReparsePoint) -ne 0)) { Fail 'acl_hardening_failed' }
    }
    if ($DirectoryOnly) {
        if (-not [string]::Equals($full,$allowed,[StringComparison]::OrdinalIgnoreCase)) { Fail 'worker_input_invalid' }
        return $full
    }
    if (-not [string]::Equals([IO.Path]::GetDirectoryName($full),$allowed,[StringComparison]::OrdinalIgnoreCase)) { Fail 'worker_input_invalid' }
    $name = [IO.Path]::GetFileName($full)
    if ($name -notin @('backup-reader.dpapi.json','restore-runner.dpapi.json') -and $name -notmatch '^\.(backup-reader|restore-runner)\.dpapi\.json\.[0-9a-f]{24}\.tmp$') { Fail 'worker_input_invalid' }
    if ([IO.File]::Exists($full) -and (([IO.File]::GetAttributes($full) -band [IO.FileAttributes]::ReparsePoint) -ne 0)) { Fail 'acl_hardening_failed' }
    return $full
}
if ($Operation -notin $allowed) { Fail 'worker_input_invalid' }
try {
    if ($Operation -eq 'identity') { $null = Request @(); $id = Identity; Reply @{ok=$true;account=$id.account;sid=$id.sid} }
    if ($Operation -eq 'protect' -or $Operation -eq 'unprotect') {
        $request = Request @('expected_sid','data_b64','entropy_b64'); $null = AssertSid $request
        try { $data=[Convert]::FromBase64String([string]$request.data_b64); $entropy=[Convert]::FromBase64String([string]$request.entropy_b64) } catch { Fail 'worker_input_invalid' }
        if ($data.Length -eq 0 -or $data.Length -gt 65536 -or $entropy.Length -ne 32) { Fail 'worker_input_invalid' }
        try {
            if ($Operation -eq 'protect') { $result=[Security.Cryptography.ProtectedData]::Protect($data,$entropy,[Security.Cryptography.DataProtectionScope]::CurrentUser); Reply @{ok=$true;ciphertext_b64=[Convert]::ToBase64String($result)} }
            $result=[Security.Cryptography.ProtectedData]::Unprotect($data,$entropy,[Security.Cryptography.DataProtectionScope]::CurrentUser); Reply @{ok=$true;plaintext_b64=[Convert]::ToBase64String($result)}
        } catch { Fail 'secret_decryption_failed' }
    }
    $request = Request @('expected_sid','path'); $current = AssertSid $request
    $path = AssertPrivatePath ([string]$request.path) ($Operation -eq 'harden-directory')
    try {
        $sid = New-Object Security.Principal.SecurityIdentifier($current.sid)
        if ($Operation -eq 'harden-directory') {
            if (-not [IO.Directory]::Exists($path)) { Fail 'acl_hardening_failed' }
            $security = New-Object Security.AccessControl.DirectorySecurity
            $security.SetAccessRuleProtection($true,$false); $security.SetOwner($sid)
            $rule = New-Object Security.AccessControl.FileSystemAccessRule($sid,'FullControl','ContainerInherit,ObjectInherit','None','Allow')
            $security.AddAccessRule($rule); [IO.Directory]::SetAccessControl($path,$security)
        } else {
            if (-not [IO.File]::Exists($path)) { Fail 'acl_hardening_failed' }
            $security = New-Object Security.AccessControl.FileSecurity
            $security.SetAccessRuleProtection($true,$false); $security.SetOwner($sid)
            $rule = New-Object Security.AccessControl.FileSystemAccessRule($sid,'FullControl','Allow')
            $security.AddAccessRule($rule); [IO.File]::SetAccessControl($path,$security)
        }
        Reply @{ok=$true}
    } catch { Fail 'acl_hardening_failed' }
} catch { Fail 'worker_failed' }
