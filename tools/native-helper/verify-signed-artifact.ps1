param(
    [Parameter(Mandatory)][string]$ArtifactPath,
    [Parameter(Mandatory)][string]$ExpectedPublisher,
    [Parameter(Mandatory)][string]$ExpectedThumbprint,
    [Parameter(Mandatory)][string]$ExpectedSha256
)
$ErrorActionPreference = 'Stop'
Import-Module (Join-Path $PSScriptRoot 'NativeHelperDeployment.psm1') -Force -WarningAction SilentlyContinue
try {
    $evidence = Get-NativeHelperArtifactEvidence -ArtifactPath $ArtifactPath
    $result = Test-NativeHelperSignatureEvidence -Evidence $evidence -ExpectedPublisher $ExpectedPublisher -ExpectedThumbprint $ExpectedThumbprint -ExpectedSha256 $ExpectedSha256
    $result | ConvertTo-Json -Compress -Depth 8
    if ($result.ready) { exit 0 }
    exit 9
} catch {
    [ordered]@{ ready = $false; code = 'artifact_verification_failed' } | ConvertTo-Json -Compress
    exit 9
}
