param(
    [Parameter(Mandatory)][string]$ArtifactPath,
    [Parameter(Mandatory)][string]$ExpectedPublisher,
    [Parameter(Mandatory)][string]$ExpectedThumbprint,
    [Parameter(Mandatory)][string]$ExpectedSha256,
    [Parameter(Mandatory)][string]$RuntimeSid
)
$ErrorActionPreference = 'Stop'
Import-Module (Join-Path $PSScriptRoot 'NativeHelperDeployment.psm1') -Force -WarningAction SilentlyContinue
try {
    if (-not (Test-NativeHelperRuntimeSid $RuntimeSid)) {
        [ordered]@{ ready = $false; code = 'runtime_sid_invalid' } | ConvertTo-Json -Compress
        exit 9
    }
    $evidence = Get-NativeHelperArtifactEvidence -ArtifactPath $ArtifactPath
    $verification = Test-NativeHelperSignatureEvidence -Evidence $evidence -ExpectedPublisher $ExpectedPublisher -ExpectedThumbprint $ExpectedThumbprint -ExpectedSha256 $ExpectedSha256
    if (-not $verification.ready) {
        $verification | ConvertTo-Json -Compress -Depth 8
        exit 9
    }
    $paths = Get-NativeHelperProductionPaths
    if (Test-Path -LiteralPath $paths.Executable) {
        [ordered]@{ ready = $false; code = 'production_target_collision' } | ConvertTo-Json -Compress
        exit 9
    }
    if ((Test-Path -LiteralPath $paths.Root) -and ((Get-Item -LiteralPath $paths.Root -Force).Attributes -band [System.IO.FileAttributes]::ReparsePoint)) {
        [ordered]@{ ready = $false; code = 'production_reparse_point' } | ConvertTo-Json -Compress
        exit 9
    }
    [ordered]@{
        ready = $true
        code = 'preflight_ready'
        mode = 'read_only'
        production_mutation_performed = $false
        source_artifact = $evidence.path
        destination = $paths.Executable
        runtime_sid = $RuntimeSid
        sha256 = $verification.sha256
    } | ConvertTo-Json -Compress
    exit 0
} catch {
    [ordered]@{ ready = $false; code = 'deployment_preflight_failed' } | ConvertTo-Json -Compress
    exit 9
}
