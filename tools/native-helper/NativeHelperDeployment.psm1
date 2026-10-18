Set-StrictMode -Version Latest

$script:CodeSigningEku = '1.3.6.1.5.5.7.3.3'
$script:ProductionRoot = 'C:\ProgramData\BackupPhpMyAdmin\SecretProvider'
$script:ProductionExecutable = Join-Path $script:ProductionRoot 'backup-secret-helper.exe'
$script:ProductionSecretDirectory = Join-Path $script:ProductionRoot 'secrets'

function New-NativeHelperResult {
    param([bool]$Ready, [string]$Code, [hashtable]$Details = @{})
    $result = [ordered]@{ ready = $Ready; code = $Code }
    foreach ($entry in $Details.GetEnumerator()) { $result[$entry.Key] = $entry.Value }
    return [pscustomobject]$result
}

function Normalize-NativeHelperThumbprint {
    param([AllowEmptyString()][string]$Value)
    return ($Value -replace '[^0-9A-Fa-f]', '').ToUpperInvariant()
}

function Normalize-NativeHelperSha256 {
    param([AllowEmptyString()][string]$Value)
    return ($Value -replace '[^0-9A-Fa-f]', '').ToUpperInvariant()
}

function Test-NativeHelperRuntimeSid {
    param([string]$RuntimeSid)
    try {
        $sid = [System.Security.Principal.SecurityIdentifier]::new($RuntimeSid)
        return $sid.Value -ceq $RuntimeSid
    } catch { return $false }
}

function Test-NativeHelperSignatureEvidence {
    param(
        [Parameter(Mandatory)]$Evidence,
        [Parameter(Mandatory)][string]$ExpectedPublisher,
        [Parameter(Mandatory)][string]$ExpectedThumbprint,
        [Parameter(Mandatory)][string]$ExpectedSha256
    )
    $required = @('schema','path','signature_status','publisher','thumbprint','eku_oids','timestamp_present','timestamp_valid','hash_algorithm','sha256','reparse_point')
    $actual = @($Evidence.PSObject.Properties.Name | Where-Object { $null -ne $_ } | Sort-Object)
    $sortedRequired = @($required | Sort-Object)
    if (($actual -join "`n") -cne ($sortedRequired -join "`n")) { return New-NativeHelperResult $false 'evidence_schema_invalid' }
    if ($Evidence.schema -ne 1 -or -not ($Evidence.path -is [string]) -or [string]::IsNullOrWhiteSpace($Evidence.path)) { return New-NativeHelperResult $false 'evidence_schema_invalid' }
    if ($Evidence.reparse_point -ne $false) { return New-NativeHelperResult $false 'artifact_reparse_point' }
    if ($Evidence.signature_status -cne 'Valid') { return New-NativeHelperResult $false 'signature_invalid' }
    if ($ExpectedPublisher.Length -lt 3 -or $Evidence.publisher -cne $ExpectedPublisher) { return New-NativeHelperResult $false 'publisher_mismatch' }
    $expectedThumb = Normalize-NativeHelperThumbprint $ExpectedThumbprint
    $actualThumb = Normalize-NativeHelperThumbprint ([string]$Evidence.thumbprint)
    if ($expectedThumb -notmatch '^[0-9A-F]{40}([0-9A-F]{24})?$' -or $actualThumb -cne $expectedThumb) { return New-NativeHelperResult $false 'thumbprint_mismatch' }
    $ekuOids = @($Evidence.eku_oids)
    if ($ekuOids -notcontains $script:CodeSigningEku) { return New-NativeHelperResult $false 'code_signing_eku_missing' }
    if ($Evidence.timestamp_present -ne $true) { return New-NativeHelperResult $false 'timestamp_missing' }
    if ($Evidence.timestamp_valid -ne $true) { return New-NativeHelperResult $false 'timestamp_invalid' }
    if ($Evidence.hash_algorithm -cne 'SHA256') { return New-NativeHelperResult $false 'hash_algorithm_invalid' }
    $expectedHash = Normalize-NativeHelperSha256 $ExpectedSha256
    $actualHash = Normalize-NativeHelperSha256 ([string]$Evidence.sha256)
    if ($expectedHash -notmatch '^[0-9A-F]{64}$' -or $actualHash -cne $expectedHash) { return New-NativeHelperResult $false 'artifact_hash_mismatch' }
    return New-NativeHelperResult $true 'verified' @{
        publisher = $Evidence.publisher
        thumbprint = $actualThumb
        sha256 = $actualHash
        path = $Evidence.path
    }
}

function Test-NativeHelperReparsePoint {
    param([Parameter(Mandatory)][string]$Path)
    try {
        $item = Get-Item -LiteralPath $Path -Force -ErrorAction Stop
        return (($item.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0)
    } catch { return $true }
}

function Get-NativeHelperArtifactEvidence {
    param([Parameter(Mandatory)][string]$ArtifactPath)
    $resolved = Resolve-Path -LiteralPath $ArtifactPath -ErrorAction Stop
    $item = Get-Item -LiteralPath $resolved.Path -Force -ErrorAction Stop
    if ($item.PSIsContainer) { throw 'artifact_invalid' }
    $isReparse = (($item.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0)
    $signature = Get-AuthenticodeSignature -LiteralPath $resolved.Path
    $certificate = $signature.SignerCertificate
    $ekuOids = @()
    if ($null -ne $certificate) {
        foreach ($extension in $certificate.Extensions) {
            if ($extension -is [System.Security.Cryptography.X509Certificates.X509EnhancedKeyUsageExtension]) {
                $ekuOids = @($extension.EnhancedKeyUsages | ForEach-Object { $_.Value })
            }
        }
    }
    $timestampPresent = $null -ne $signature.TimeStamperCertificate
    $timestampValid = $timestampPresent -and $signature.Status -eq [System.Management.Automation.SignatureStatus]::Valid
    return [pscustomobject][ordered]@{
        schema = 1
        path = $resolved.Path
        signature_status = [string]$signature.Status
        publisher = if ($null -ne $certificate) { [string]$certificate.Subject } else { '' }
        thumbprint = if ($null -ne $certificate) { Normalize-NativeHelperThumbprint $certificate.Thumbprint } else { '' }
        eku_oids = $ekuOids
        timestamp_present = $timestampPresent
        timestamp_valid = $timestampValid
        hash_algorithm = 'SHA256'
        sha256 = (Get-FileHash -LiteralPath $resolved.Path -Algorithm SHA256).Hash.ToUpperInvariant()
        reparse_point = $isReparse
    }
}

function Test-NativeHelperAclReadiness {
    param([Parameter(Mandatory)][string]$RuntimeSid)
    if (-not (Test-NativeHelperRuntimeSid $RuntimeSid)) { return New-NativeHelperResult $false 'runtime_sid_invalid' }
    if (-not (Test-Path -LiteralPath $script:ProductionExecutable -PathType Leaf)) { return New-NativeHelperResult $false 'production_executable_missing' }
    if (-not (Test-Path -LiteralPath $script:ProductionSecretDirectory -PathType Container)) { return New-NativeHelperResult $false 'secret_directory_missing' }
    if ((Test-NativeHelperReparsePoint $script:ProductionRoot) -or (Test-NativeHelperReparsePoint $script:ProductionExecutable) -or (Test-NativeHelperReparsePoint $script:ProductionSecretDirectory)) {
        return New-NativeHelperResult $false 'production_reparse_point'
    }
    try {
        $exeAcl = Get-Acl -LiteralPath $script:ProductionExecutable -ErrorAction Stop
        $secretAcl = Get-Acl -LiteralPath $script:ProductionSecretDirectory -ErrorAction Stop
        $runtimeExe = @($exeAcl.Access | Where-Object { $_.IdentityReference.Translate([System.Security.Principal.SecurityIdentifier]).Value -eq $RuntimeSid })
        $runtimeSecret = @($secretAcl.Access | Where-Object { $_.IdentityReference.Translate([System.Security.Principal.SecurityIdentifier]).Value -eq $RuntimeSid })
        $allowedExecutableWriters = @('S-1-5-18', 'S-1-5-32-544')
        $runtimeWritesExe = @($runtimeExe | Where-Object { $_.AccessControlType -eq 'Allow' -and ($_.FileSystemRights -band ([System.Security.AccessControl.FileSystemRights]::Write -bor [System.Security.AccessControl.FileSystemRights]::Modify -bor [System.Security.AccessControl.FileSystemRights]::FullControl)) })
        $runtimeReadsExe = @($runtimeExe | Where-Object { $_.AccessControlType -eq 'Allow' -and ($_.FileSystemRights -band [System.Security.AccessControl.FileSystemRights]::ReadAndExecute) })
        $runtimeControlsSecret = @($runtimeSecret | Where-Object { $_.AccessControlType -eq 'Allow' -and ($_.FileSystemRights -band [System.Security.AccessControl.FileSystemRights]::FullControl) })
        $unexpectedExecutableWriter = @($exeAcl.Access | Where-Object {
            $sid = $_.IdentityReference.Translate([System.Security.Principal.SecurityIdentifier]).Value
            $_.AccessControlType -eq 'Allow' -and $allowedExecutableWriters -notcontains $sid -and
                ($_.FileSystemRights -band ([System.Security.AccessControl.FileSystemRights]::Write -bor [System.Security.AccessControl.FileSystemRights]::Modify -bor [System.Security.AccessControl.FileSystemRights]::FullControl))
        })
        $unexpectedSecretPrincipal = @($secretAcl.Access | Where-Object {
            $_.AccessControlType -eq 'Allow' -and $_.IdentityReference.Translate([System.Security.Principal.SecurityIdentifier]).Value -ne $RuntimeSid
        })
        if ($runtimeWritesExe.Count -ne 0 -or $runtimeReadsExe.Count -eq 0) { return New-NativeHelperResult $false 'executable_acl_invalid' }
        if ($unexpectedExecutableWriter.Count -ne 0) { return New-NativeHelperResult $false 'executable_acl_invalid' }
        if ($secretAcl.AreAccessRulesProtected -ne $true -or $runtimeControlsSecret.Count -eq 0 -or $unexpectedSecretPrincipal.Count -ne 0) { return New-NativeHelperResult $false 'secret_acl_invalid' }
        return New-NativeHelperResult $true 'acl_verified'
    } catch { return New-NativeHelperResult $false 'acl_verification_failed' }
}

function Get-NativeHelperProductionPaths {
    return [pscustomobject]@{ Root = $script:ProductionRoot; Executable = $script:ProductionExecutable; Secrets = $script:ProductionSecretDirectory }
}

Export-ModuleMember -Function New-NativeHelperResult,Test-NativeHelperRuntimeSid,Test-NativeHelperSignatureEvidence,Get-NativeHelperArtifactEvidence,Test-NativeHelperAclReadiness,Get-NativeHelperProductionPaths
