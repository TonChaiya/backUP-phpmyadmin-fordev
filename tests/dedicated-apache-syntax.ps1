$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$apacheBinaryRoot = 'C:\laragon\bin\apache\httpd-2.4.66-260223-Win64-VS18'
$phpBinaryRoot = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64'
$temporaryBase = [IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd('\')
$temporaryRoot = Join-Path $temporaryBase ('dedicated-apache-syntax-' + [Guid]::NewGuid().ToString('N'))

try {
    foreach ($directory in @('runtime\logs', 'runtime\temporary', 'runtime\sessions', 'app\public', 'secret-provider')) {
        [IO.Directory]::CreateDirectory((Join-Path $temporaryRoot $directory)) | Out-Null
    }

    $apacheConfig = Get-Content -Raw -LiteralPath (Join-Path $projectRoot 'deployment\dedicated-apache\conf\httpd.conf')
    $apacheConfig = $apacheConfig.Replace('C:/Program Files/BackupPhpMyAdmin/Runtime/Apache', $apacheBinaryRoot.Replace('\', '/'))
    $apacheConfig = $apacheConfig.Replace('C:/Program Files/BackupPhpMyAdmin/Runtime/PHP', $phpBinaryRoot.Replace('\', '/'))
    $apacheConfig = $apacheConfig.Replace('C:/Program Files/BackupPhpMyAdmin/Application', (Join-Path $temporaryRoot 'app').Replace('\', '/'))
    $apacheConfig = $apacheConfig.Replace('C:/ProgramData/BackupPhpMyAdmin/RuntimeData', (Join-Path $temporaryRoot 'runtime').Replace('\', '/'))
    $apacheConfigPath = Join-Path $temporaryRoot 'httpd.conf'
    [IO.File]::WriteAllText($apacheConfigPath, $apacheConfig, [Text.UTF8Encoding]::new($false))

    $apacheOutput = & (Join-Path $apacheBinaryRoot 'bin\httpd.exe') -t -f $apacheConfigPath 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Apache syntax failed: $($apacheOutput -join ' ')"
    }

    $phpConfig = Get-Content -Raw -LiteralPath (Join-Path $projectRoot 'deployment\dedicated-apache\php\php.ini')
    $phpConfig = $phpConfig.Replace('C:\Program Files\BackupPhpMyAdmin\Runtime\PHP', $phpBinaryRoot)
    $phpConfig = $phpConfig.Replace('C:\Program Files\BackupPhpMyAdmin\Application', (Join-Path $temporaryRoot 'app'))
    $phpConfig = $phpConfig.Replace('C:\ProgramData\BackupPhpMyAdmin\RuntimeData', (Join-Path $temporaryRoot 'runtime'))
    $phpConfig = $phpConfig.Replace('C:\ProgramData\BackupPhpMyAdmin\SecretProvider', (Join-Path $temporaryRoot 'secret-provider'))
    $phpConfigPath = Join-Path $temporaryRoot 'php.ini'
    [IO.File]::WriteAllText($phpConfigPath, $phpConfig, [Text.UTF8Encoding]::new($false))

    $probe = 'echo json_encode(["mysqli"=>extension_loaded("mysqli"),"sodium"=>extension_loaded("sodium"),"session"=>ini_get("session.save_path"),"temp"=>ini_get("sys_temp_dir"),"display_errors"=>ini_get("display_errors"),"ini"=>php_ini_loaded_file(),"extension_dir"=>ini_get("extension_dir")]);'
    $phpOutput = & (Join-Path $phpBinaryRoot 'php.exe') -c $phpConfigPath -r $probe 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "PHP config failed: $($phpOutput -join ' ')"
    }
    $phpEvidence = ($phpOutput -join '') | ConvertFrom-Json
    if (-not $phpEvidence.mysqli -or -not $phpEvidence.sodium -or
        ([string]$phpEvidence.display_errors) -notin @('', '0', 'Off')) {
        $phpLog = Join-Path $temporaryRoot 'runtime\logs\php-error.log'
        $logText = if (Test-Path -LiteralPath $phpLog) { Get-Content -Raw -LiteralPath $phpLog } else { 'no PHP log' }
        throw "PHP extension or error-display isolation check failed: $($phpEvidence | ConvertTo-Json -Compress); $logText"
    }
    if ($phpEvidence.session -cne (Join-Path $temporaryRoot 'runtime\sessions') -or
        $phpEvidence.temp -cne (Join-Path $temporaryRoot 'runtime\temporary')) {
        throw 'PHP session/temp path isolation check failed'
    }

    [ordered]@{
        ready = $true
        apache = ($apacheOutput -join ' ')
        php = $phpEvidence
    } | ConvertTo-Json -Depth 4 -Compress
} finally {
    $resolved = [IO.Path]::GetFullPath($temporaryRoot)
    if ($resolved.StartsWith($temporaryBase + '\dedicated-apache-syntax-', [StringComparison]::OrdinalIgnoreCase) -and
        [IO.Directory]::Exists($resolved)) {
        [IO.Directory]::Delete($resolved, $true)
    }
}
