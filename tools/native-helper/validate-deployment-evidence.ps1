param(
    [Parameter(Mandatory)][string]$ExpectedPublisher,
    [Parameter(Mandatory)][string]$ExpectedThumbprint,
    [Parameter(Mandatory)][string]$ExpectedSha256
)
$ErrorActionPreference = 'Stop'
Import-Module (Join-Path $PSScriptRoot 'NativeHelperDeployment.psm1') -Force -WarningAction SilentlyContinue
try {
    $inputJson = [Console]::In.ReadToEnd()
    if ([string]::IsNullOrWhiteSpace($inputJson) -or $inputJson.Length -gt 65536) { throw 'evidence_input_invalid' }
    $evidence = $inputJson | ConvertFrom-Json -Depth 8
    $result = Test-NativeHelperSignatureEvidence -Evidence $evidence -ExpectedPublisher $ExpectedPublisher -ExpectedThumbprint $ExpectedThumbprint -ExpectedSha256 $ExpectedSha256
    $result | ConvertTo-Json -Compress -Depth 8
    if ($result.ready) { exit 0 }
    exit 9
} catch {
    [ordered]@{ ready = $false; code = 'evidence_input_invalid' } | ConvertTo-Json -Compress
    exit 9
}
