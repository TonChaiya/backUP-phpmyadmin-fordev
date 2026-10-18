param(
    [Parameter(Mandatory)][string]$ExpectedPublisher,
    [Parameter(Mandatory)][string]$ExpectedThumbprint,
    [Parameter(Mandatory)][string]$ExpectedSha256,
    [Parameter(Mandatory)][string]$RuntimeSid
)
$ErrorActionPreference = 'Stop'
Import-Module (Join-Path $PSScriptRoot 'NativeHelperDeployment.psm1') -Force -WarningAction SilentlyContinue
try {
    $inputJson = [Console]::In.ReadToEnd()
    if ([string]::IsNullOrWhiteSpace($inputJson) -or $inputJson.Length -gt 65536) { throw 'evidence_input_invalid' }
    $evidence = $inputJson | ConvertFrom-Json -Depth 8
    $verification = Test-NativeHelperSignatureEvidence -Evidence $evidence -ExpectedPublisher $ExpectedPublisher -ExpectedThumbprint $ExpectedThumbprint -ExpectedSha256 $ExpectedSha256
    if (-not $verification.ready) {
        $verification | ConvertTo-Json -Compress -Depth 8
        exit 9
    }
    if (-not (Test-NativeHelperRuntimeSid $RuntimeSid)) {
        [ordered]@{ ready = $false; code = 'runtime_sid_invalid' } | ConvertTo-Json -Compress
        exit 9
    }
    $paths = Get-NativeHelperProductionPaths
    [ordered]@{
        ready = $true
        code = 'dry_run_ready'
        mode = 'dry_run_only'
        production_mutation_performed = $false
        source_artifact = $evidence.path
        executable_path = $paths.Executable
        secret_directory = $paths.Secrets
        runtime_sid = $RuntimeSid
        sha256 = $verification.sha256
        publisher = $verification.publisher
        thumbprint = $verification.thumbprint
        required_operations = @(
            'authorized_operator_creates_fixed_programdata_directories'
            'authorized_operator_applies_admin_system_runtime_acl'
            'authorized_operator_atomically_places_verified_signed_executable'
            'read_only_tools_reverify_signature_hash_acl_and_identity'
        )
    } | ConvertTo-Json -Compress -Depth 8
    exit 0
} catch {
    [ordered]@{ ready = $false; code = 'deployment_plan_failed' } | ConvertTo-Json -Compress
    exit 9
}
