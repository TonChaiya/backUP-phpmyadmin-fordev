param(
    [Parameter(Mandatory)][string]$ExpectedRuntimeSid,
    [Parameter(Mandatory)][string]$ExpectedPublisher,
    [Parameter(Mandatory)][string]$ExpectedThumbprint,
    [Parameter(Mandatory)][string]$ExpectedSha256
)
$ErrorActionPreference = 'Stop'
Import-Module (Join-Path $PSScriptRoot 'NativeHelperDeployment.psm1') -Force -WarningAction SilentlyContinue
try {
    if (-not (Test-NativeHelperRuntimeSid $ExpectedRuntimeSid)) {
        [ordered]@{ ready = $false; code = 'runtime_sid_invalid' } | ConvertTo-Json -Compress
        exit 9
    }
    $paths = Get-NativeHelperProductionPaths
    if (-not (Test-Path -LiteralPath $paths.Executable -PathType Leaf)) {
        [ordered]@{ ready = $false; code = 'production_executable_missing' } | ConvertTo-Json -Compress
        exit 9
    }
    $evidence = Get-NativeHelperArtifactEvidence -ArtifactPath $paths.Executable
    $signature = Test-NativeHelperSignatureEvidence -Evidence $evidence -ExpectedPublisher $ExpectedPublisher -ExpectedThumbprint $ExpectedThumbprint -ExpectedSha256 $ExpectedSha256
    if (-not $signature.ready) {
        $signature | ConvertTo-Json -Compress -Depth 8
        exit 9
    }
    $acl = Test-NativeHelperAclReadiness -RuntimeSid $ExpectedRuntimeSid
    if (-not $acl.ready) {
        $acl | ConvertTo-Json -Compress -Depth 8
        exit 9
    }
    $start = [System.Diagnostics.ProcessStartInfo]::new()
    $start.FileName = $paths.Executable
    $start.ArgumentList.Add('identity')
    $start.UseShellExecute = $false
    $start.RedirectStandardInput = $true
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    $start.CreateNoWindow = $true
    $process = [System.Diagnostics.Process]::new()
    $process.StartInfo = $start
    if (-not $process.Start()) { throw 'identity_start_failed' }
    $process.StandardInput.Write('{}')
    $process.StandardInput.Close()
    if (-not $process.WaitForExit(5000)) {
        $process.Kill($true)
        [ordered]@{ ready = $false; code = 'identity_timeout' } | ConvertTo-Json -Compress
        exit 9
    }
    $stdout = $process.StandardOutput.ReadToEnd()
    $hadStderr = -not [string]::IsNullOrEmpty($process.StandardError.ReadToEnd())
    if ($stdout.Length -gt 65536 -or $hadStderr -or $process.ExitCode -ne 0) { throw 'identity_failed' }
    $identity = $stdout | ConvertFrom-Json -Depth 4
    $keys = @($identity.PSObject.Properties.Name | Where-Object { $null -ne $_ } | Sort-Object)
    if (($keys -join "`n") -cne ((@('account','ok','sid') | Sort-Object) -join "`n") -or $identity.ok -ne $true -or $identity.sid -cne $ExpectedRuntimeSid) {
        [ordered]@{ ready = $false; code = 'runtime_identity_mismatch' } | ConvertTo-Json -Compress
        exit 9
    }
    [ordered]@{ ready = $true; code = 'runtime_identity_verified'; mode = 'read_only'; sid = $identity.sid; account = $identity.account } | ConvertTo-Json -Compress
    exit 0
} catch {
    [ordered]@{ ready = $false; code = 'runtime_identity_failed' } | ConvertTo-Json -Compress
    exit 9
}
