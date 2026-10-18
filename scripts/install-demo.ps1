param(
    [string]$DemoPath = 'C:\xampp\htdocs\vmt_payfast_demo',
    [string]$ComposerPhar = 'C:\composer\composer.phar'
)
$ErrorActionPreference = 'Stop'
$packageRoot = Split-Path $PSScriptRoot -Parent
$demoRoot = [System.IO.Path]::GetFullPath($DemoPath)
if ($demoRoot -eq $packageRoot -or -not (Test-Path -LiteralPath (Join-Path $demoRoot 'artisan'))) {
    throw 'Create a fresh, separate Laravel 13 skeleton first. The package source is not a demo destination.'
}
$composerFile = Join-Path $demoRoot 'composer.json'
$demoComposer = Get-Content -LiteralPath $composerFile -Raw | ConvertFrom-Json -AsHashtable
if ($demoComposer.name -notin @('laravel/laravel', 'vmt/payfast-sandbox-demo')) {
    throw 'Refusing to install over an unrelated application.'
}
$template = Join-Path $packageRoot 'examples/demo-app'
foreach ($source in Get-ChildItem -LiteralPath $template -File -Recurse -Force) {
    $relative = [System.IO.Path]::GetRelativePath($template, $source.FullName)
    $destination = Join-Path $demoRoot $relative
    New-Item -ItemType Directory -Path (Split-Path $destination -Parent) -Force | Out-Null
    Copy-Item -LiteralPath $source.FullName -Destination $destination -Force
}
$demoComposer.name = 'vmt/payfast-sandbox-demo'
$demoComposer.description = 'Isolated sandbox-only acceptance application for vmt/payfast'
$demoComposer.repositories = @(@{type = 'path'; url = $packageRoot.Replace('\', '/'); options = @{symlink = $true; versions = @{'vmt/payfast' = 'dev-main'}}})
$demoComposer.require['vmt/payfast'] = 'dev-main'
$demoComposer | ConvertTo-Json -Depth 30 | Set-Content -LiteralPath $composerFile -Encoding utf8NoBOM
$envFile = Join-Path $demoRoot '.env'
if (-not (Test-Path -LiteralPath $envFile)) {
    Copy-Item -LiteralPath (Join-Path $template '.env.example') -Destination $envFile
}
# Preserve any sandbox values the user has already saved. Never print the file.
$environmentText = Get-Content -LiteralPath $envFile -Raw
foreach ($line in Get-Content -LiteralPath (Join-Path $template '.env.example')) {
    if ($line -match '^([A-Z_]+)=') {
        $settingName = $Matches[1]
        if ($environmentText -notmatch "(?m)^$settingName=") { $environmentText += "`n$line" }
    }
}
$environmentText = $environmentText -replace '(?m)^APP_DEBUG=.*$', 'APP_DEBUG=false'
$demoDatabase = Join-Path $demoRoot 'database/database.sqlite'
if ($environmentText -notmatch '(?m)^DB_CONNECTION=sqlite\s*$' -or $environmentText -match '(?m)^APP_ENV=production\s*$') {
    throw 'Refusing migrations: the demo must use its dedicated SQLite database and a non-production environment.'
}
if ($environmentText -match '(?m)^DB_DATABASE=(.+)$') {
    $configuredDatabase = $Matches[1].Trim().Trim('"')
    if ([System.IO.Path]::GetFullPath($configuredDatabase) -ne [System.IO.Path]::GetFullPath($demoDatabase)) {
        throw 'Refusing migrations: DB_DATABASE points outside the dedicated demo database.'
    }
} else { $environmentText += "`nDB_DATABASE=$($demoDatabase.Replace('\', '/'))`n" }
Set-Content -LiteralPath $envFile -Value $environmentText -Encoding utf8NoBOM
Push-Location $demoRoot
try {
    & php $ComposerPhar update --no-interaction --prefer-dist
    if ($LASTEXITCODE -ne 0) { throw 'Demo Composer installation failed.' }
    & php artisan config:clear
    & php artisan migrate --force
    if ($LASTEXITCODE -ne 0) { throw 'Demo migration failed.' }
} finally { Pop-Location }
Write-Output "Sandbox demo installed at $demoRoot. Credentials were not displayed or replaced."
