param(
    [Parameter(Mandatory)][string]$ExpectedPublisher,
    [Parameter(Mandatory)][string]$ExpectedThumbprint,
    [Parameter(Mandatory)][string]$ExpectedSha256,
    [Parameter(Mandatory)][string]$RuntimeSid
)
$ErrorActionPreference = 'Stop'
Import-Module (Join-Path $PSScriptRoot 'NativeHelperDeployment.psm1') -Force -WarningAction SilentlyContinue
try {
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
    $acl = Test-NativeHelperAclReadiness -RuntimeSid $RuntimeSid
    if (-not $acl.ready) {
        $acl | ConvertTo-Json -Compress -Depth 8
        exit 9
    }
    [ordered]@{ ready = $true; code = 'deployed_helper_verified'; mode = 'read_only'; production_mutation_performed = $false; sha256 = $signature.sha256 } | ConvertTo-Json -Compress
    exit 0
} catch {
    [ordered]@{ ready = $false; code = 'deployed_verification_failed' } | ConvertTo-Json -Compress
    exit 9
}
