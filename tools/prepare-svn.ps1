# Prepares MaxtDesign Role-Based Pricing for WordPress.org SVN upload.
# Windows-native PowerShell counterpart to tools/prepare-svn.sh.
#
# Usage: powershell -NoProfile -ExecutionPolicy Bypass -File tools/prepare-svn.ps1 [version]

param(
    [string]$Version = ""
)

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$RootDir = Split-Path -Parent $ScriptDir
Set-Location $RootDir

if (-not $Version) {
    if (Test-Path "package.json") {
        $pkg = Get-Content -Raw "package.json" | ConvertFrom-Json
        $Version = $pkg.version
    } else {
        $Version = "dev"
    }
}

$OutDir = "svn-upload/trunk"

Write-Host "=================================================="
Write-Host "MaxtDesign Role-Based Pricing - Prepare for SVN"
Write-Host "=================================================="
Write-Host "Version: $Version"
Write-Host "Output:  $OutDir/"
Write-Host ""

Write-Host "Running PHP lint..."
@(
    "maxtdesign-role-based-pricing.php",
    "uninstall.php",
    "includes/class-admin.php",
    "includes/class-core.php",
    "includes/class-frontend.php"
) | ForEach-Object {
    & php -l $_ | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "PHP lint failed on $_" }
}
Write-Host "Lint OK."
Write-Host ""

if (Test-Path $OutDir) { Remove-Item -Recurse -Force $OutDir }
New-Item -ItemType Directory -Force -Path "$OutDir/includes"   | Out-Null
New-Item -ItemType Directory -Force -Path "$OutDir/assets/css" | Out-Null

Copy-Item "maxtdesign-role-based-pricing.php" "$OutDir/"
Copy-Item "readme.txt"                        "$OutDir/"
Copy-Item "uninstall.php"                     "$OutDir/"

Copy-Item "includes/class-admin.php"    "$OutDir/includes/"
Copy-Item "includes/class-core.php"     "$OutDir/includes/"
Copy-Item "includes/class-frontend.php" "$OutDir/includes/"

Copy-Item "assets/css/admin.css"    "$OutDir/assets/css/"
Copy-Item "assets/css/frontend.css" "$OutDir/assets/css/"

$StableTag = (Select-String -Path "$OutDir/readme.txt" -Pattern "^Stable tag:" | ForEach-Object { ($_.Line -split '\s+')[2] })
$HeaderVer = (Select-String -Path "$OutDir/maxtdesign-role-based-pricing.php" -Pattern "^ \* Version:" | ForEach-Object { ($_.Line -split '\s+')[3] })

if ($StableTag -ne $Version -or $HeaderVer -ne $Version) {
    Write-Warning "Version mismatch"
    Write-Warning "  Requested: $Version"
    Write-Warning "  Plugin header Version: $HeaderVer"
    Write-Warning "  readme.txt Stable tag: $StableTag"
}

$count = (Get-ChildItem -Recurse -File $OutDir).Count
Write-Host ""
Write-Host "Staged $count files in $OutDir/"
Write-Host ""
Write-Host "Next: sync $OutDir/* into your SVN checkout at"
Write-Host "  C:/maxt/ops/wp-org-svn/maxtdesign-role-based-pricing/trunk/"
Write-Host "Then: svn cp trunk tags/$Version && svn ci ... (atomic, single commit)"
