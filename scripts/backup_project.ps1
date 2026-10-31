param(
    [string]$Description = "Automated session backup",
    [switch]$SkipZip = $false,
    [switch]$SkipBundle = $false
)

$ErrorActionPreference = "Stop"
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Split-Path -Parent $scriptDir

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$readableDate = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
$backupsDir = Join-Path $projectRoot "backups"
$snapshotsDir = Join-Path $backupsDir "snapshots"
$bundlesDir = Join-Path $backupsDir "git_bundles"
$logFile = Join-Path $backupsDir "backup_log.json"

Write-Host "==================================================" -ForegroundColor Cyan
Write-Host " Zolo SmartSchool - Continuous Project Backup" -ForegroundColor Green
Write-Host " Timestamp: $readableDate" -ForegroundColor Gray
Write-Host "==================================================" -ForegroundColor Cyan

if (-not (Test-Path $snapshotsDir)) { New-Item -ItemType Directory -Path $snapshotsDir -Force | Out-Null }
if (-not (Test-Path $bundlesDir)) { New-Item -ItemType Directory -Path $bundlesDir -Force | Out-Null }

$gitCommit = "unknown"
$gitBranch = "unknown"
try {
    $gitCommit = (git -C $projectRoot rev-parse --short HEAD 2>$null)
    $gitBranch = (git -C $projectRoot rev-parse --abbrev-ref HEAD 2>$null)
} catch { }

$bundleFileName = $null
$bundleFileSize = 0
$bundleSizeMb = 0
if (-not $SkipBundle) {
    $bundleFileName = "zolo_git_" + $timestamp + "_" + $gitCommit + ".bundle"
    $bundlePath = Join-Path $bundlesDir $bundleFileName
    Write-Host "[1/2] Creating Git bundle: $bundleFileName" -ForegroundColor Yellow
    try {
        & git -C $projectRoot bundle create $bundlePath --all
        if (Test-Path $bundlePath) {
            $bundleFileSize = (Get-Item $bundlePath).Length
            $bundleSizeMb = [math]::Round($bundleFileSize / 1048576, 2)
            Write-Host "      Git bundle created successfully: $bundleSizeMb MB" -ForegroundColor Green
        }
    } catch {
        Write-Host "      Git bundle warning: $_" -ForegroundColor DarkYellow
    }
}

$zipFileName = $null
$zipFileSize = 0
$zipSizeMb = 0
if (-not $SkipZip) {
    $zipFileName = "zolo_snapshot_" + $timestamp + "_" + $gitCommit + ".zip"
    $zipPath = Join-Path $snapshotsDir $zipFileName
    Write-Host "[2/2] Creating codebase snapshot: $zipFileName" -ForegroundColor Yellow

    $tempDir = Join-Path $env:TEMP ("zolo_backup_stage_" + $timestamp)
    if (Test-Path $tempDir) { Remove-Item -Recurse -Force $tempDir }
    New-Item -ItemType Directory -Path $tempDir | Out-Null

    try {
        $destPhpCode = Join-Path $tempDir "PHP_CODE"
        $srcPhpCode = Join-Path $projectRoot "PHP_CODE"
        if (Test-Path $srcPhpCode) {
            & robocopy $srcPhpCode $destPhpCode /E /XD vendor node_modules .git cache sessions views storage/logs storage/framework/cache storage/framework/sessions storage/framework/views /XF *.zip *.bundle *.log /NP /NJH /NJS /NDL | Out-Null
        }

        Get-ChildItem -Path $projectRoot -File -Filter "*.md" | ForEach-Object {
            Copy-Item -Path $_.FullName -Destination $tempDir -Force
        }
        if (Test-Path (Join-Path $projectRoot "design-reference")) {
            Copy-Item -Path (Join-Path $projectRoot "design-reference") -Destination (Join-Path $tempDir "design-reference") -Recurse -Force
        }

        Compress-Archive -Path (Join-Path $tempDir "*") -DestinationPath $zipPath -CompressionLevel Optimal
        if (Test-Path $zipPath) {
            $zipFileSize = (Get-Item $zipPath).Length
            $zipSizeMb = [math]::Round($zipFileSize / 1048576, 2)
            Write-Host "      Codebase snapshot created: $zipSizeMb MB" -ForegroundColor Green
        }
    } finally {
        if (Test-Path $tempDir) { Remove-Item -Recurse -Force $tempDir -ErrorAction SilentlyContinue }
    }
}

$logEntries = @()
if (Test-Path $logFile) {
    try {
        $raw = Get-Content -Path $logFile -Raw
        # Windows PowerShell 5.1 emits a parsed JSON array as ONE object; unroll it, or
        # every run nests the previous log one level deeper until Depth truncates it.
        if ($raw) { $logEntries = @($raw | ConvertFrom-Json | ForEach-Object { $_ }) }
    } catch {
        Write-Host "      Existing backup log is unreadable; starting a new one" -ForegroundColor DarkYellow
        Copy-Item -Path $logFile -Destination ($logFile + ".corrupt_" + $timestamp) -Force
    }
}

$newEntry = [PSCustomObject]@{
    timestamp   = $readableDate
    branch      = $gitBranch
    commit      = $gitCommit
    description = $Description
    snapshot    = [PSCustomObject]@{
        file = $zipFileName
        size_bytes = $zipFileSize
        size_mb = $zipSizeMb
    }
    git_bundle  = [PSCustomObject]@{
        file = $bundleFileName
        size_bytes = $bundleFileSize
        size_mb = $bundleSizeMb
    }
}

$logEntries += $newEntry
# -InputObject keeps a one-entry log as a JSON array instead of a bare object.
ConvertTo-Json -InputObject @($logEntries) -Depth 5 | Set-Content -Path $logFile -Encoding UTF8

Write-Host "==================================================" -ForegroundColor Cyan
Write-Host " Backup Complete!" -ForegroundColor Green
Write-Host " Log updated: backups/backup_log.json" -ForegroundColor Gray
Write-Host "==================================================" -ForegroundColor Cyan
