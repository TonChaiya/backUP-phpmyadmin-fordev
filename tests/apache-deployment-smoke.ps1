param(
    [string] $BaseUrl = 'http://localhost:8081/backUP-phpmyadmin-fordev/'
)

$ErrorActionPreference = 'Stop'
$base = $BaseUrl.TrimEnd('/') + '/'
$session = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
$failures = [System.Collections.Generic.List[string]]::new()
$checks = 0

function Request([string] $relativePath) {
    Invoke-WebRequest -Uri ($base + $relativePath) -WebSession $session -SkipHttpErrorCheck
}

function Check([bool] $condition, [string] $message) {
    $script:checks++
    if (-not $condition) {
        $script:failures.Add($message)
        Write-Host "FAIL $message"
        return
    }

    Write-Host "PASS $message"
}

$pages = [ordered]@{
    ''                    = 'Dashboard'
    'index.php?page=backup'   = 'Backup'
    'index.php?page=restore'  = 'Restore'
    'index.php?page=history'  = 'History'
    'index.php?page=settings' = 'Settings'
}

foreach ($entry in $pages.GetEnumerator()) {
    $response = Request $entry.Key
    Check ($response.StatusCode -eq 200) "$($entry.Value) returns 200 through Apache"
    Check ($response.Content -match '<nav class="primary-nav"') "$($entry.Value) renders the shared application shell"
    Check ($response.Content -match ('data-page="' + $entry.Value.ToLowerInvariant() + '"')) "$($entry.Value) route reaches the expected controller page"
}

$css = Request 'assets/app.css'
Check ($css.StatusCode -eq 200) 'app.css returns 200 from the subdirectory URL'
Check (($css.Headers.'Content-Type' -join '') -match '^text/css') 'app.css has a CSS content type'

$javascript = Request 'assets/app.js'
Check ($javascript.StatusCode -eq 200) 'app.js returns 200 from the subdirectory URL'
Check (($javascript.Headers.'Content-Type' -join '') -match 'javascript') 'app.js has a JavaScript content type'

$job = Request 'job.php?action=status&job_id=00000000000000000000000000000000'
Check ($job.StatusCode -eq 419) 'Backup API reaches PHP and rejects a status request without CSRF'
Check (($job.Headers.'Content-Type' -join '') -match '^application/json') 'Backup API returns JSON'

$restoreJob = Request 'restore-job.php?action=status&job_id=00000000000000000000000000000000'
Check ($restoreJob.StatusCode -eq 419) 'Restore API reaches PHP and rejects a status request without CSRF'
Check (($restoreJob.Headers.'Content-Type' -join '') -match '^application/json') 'Restore API returns JSON'

$csrfPage = Request 'index.php?page=backup'
$csrfMatch = [regex]::Match($csrfPage.Content, 'name="csrf_token"\s+value="([a-f0-9]{64})"')
Check ($csrfMatch.Success) 'Backup page exposes a session-bound CSRF token'
if ($csrfMatch.Success) {
    $csrfHeaders = @{ 'X-CSRF-Token' = $csrfMatch.Groups[1].Value }
    $ownedJob = Invoke-WebRequest -Uri ($base + 'job.php?action=status&job_id=00000000000000000000000000000000') -WebSession $session -Headers $csrfHeaders -SkipHttpErrorCheck
    Check ($ownedJob.StatusCode -eq 404) 'Backup API accepts the session CSRF token before enforcing job ownership'

    $ownedRestoreJob = Invoke-WebRequest -Uri ($base + 'restore-job.php?action=status&job_id=00000000000000000000000000000000') -WebSession $session -Headers $csrfHeaders -SkipHttpErrorCheck
    Check ($ownedRestoreJob.StatusCode -eq 404) 'Restore API accepts the session CSRF token before enforcing job ownership'
}

$download = Request 'download.php?file=missing&token=missing'
Check ($download.StatusCode -eq 404) 'Download endpoint rejects an unauthorized artifact'
Check ($download.Content.Trim() -eq 'Backup file is not available.') 'Download rejection comes from the application boundary'

$sensitivePaths = @(
    'app/bootstrap.php',
    'config/app.php',
    'storage/logs/app.log',
    'views/layout.php',
    'tests/run.php',
    'tools/',
    'docs/',
    'PHASE3_DESIGN.md',
    'TEST_BASELINE.md',
    '.git/config',
    '.htaccess'
)

foreach ($path in $sensitivePaths) {
    $response = Request $path
    Check ($response.StatusCode -in @(403, 404)) "$path is not directly readable"
}

$cookieSession = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
$cookieResponse = Invoke-WebRequest -Uri $base -WebSession $cookieSession -SkipHttpErrorCheck
$setCookie = $cookieResponse.Headers.'Set-Cookie' -join '; '
Check ($setCookie -match '(?i)HttpOnly') 'Session cookie is HttpOnly'
Check ($setCookie -match '(?i)SameSite=Strict') 'Session cookie keeps SameSite=Strict'
Check ($setCookie -match '(?i)(?:^|;\s*)path=/') 'Session cookie remains valid for the application subdirectory'

if ($failures.Count -gt 0) {
    Write-Error "Apache deployment smoke failed: $($failures.Count) of $checks checks failed."
}

Write-Host "Apache deployment smoke passed: $checks checks."
