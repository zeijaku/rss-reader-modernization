param(
    [switch]$WhatIf
)

$ErrorActionPreference = "Stop"
$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path

if (-not (Test-Path -LiteralPath (Join-Path $ProjectRoot ".git"))) {
    throw "Git working tree not found: $ProjectRoot"
}
if (-not (Test-Path -LiteralPath (Join-Path $ProjectRoot "public/index.php"))) {
    throw "public/index.php not found. Check the project location."
}

$RemoveFiles = @(
    "public/css/all.min.css",
    "public/css/bootstrap-grid.css",
    "public/css/bootstrap-grid.css.map",
    "public/css/bootstrap-grid.min.css",
    "public/css/bootstrap-grid.min.css.map",
    "public/css/bootstrap-reboot.css",
    "public/css/bootstrap-reboot.css.map",
    "public/css/bootstrap-reboot.min.css",
    "public/css/bootstrap-reboot.min.css.map",
    "public/css/bootstrap.css",
    "public/css/bootstrap.css.map",
    "public/css/brands.css",
    "public/css/brands.min.css",
    "public/css/drawer.css",
    "public/css/fontawesome.css",
    "public/css/fontawesome.min.css",
    "public/css/regular.css",
    "public/css/regular.min.css",
    "public/css/solid.css",
    "public/css/solid.min.css",
    "public/css/svg-with-js.css",
    "public/css/svg-with-js.min.css",
    "public/css/v4-shims.css",
    "public/css/v4-shims.min.css",
    "public/js/all.js",
    "public/js/all.min.js",
    "public/js/bootstrap.bundle.js",
    "public/js/bootstrap.bundle.js.map",
    "public/js/bootstrap.bundle.min.js",
    "public/js/bootstrap.bundle.min.js.map",
    "public/js/bootstrap.js",
    "public/js/bootstrap.js.map",
    "public/js/brands.js",
    "public/js/brands.min.js",
    "public/js/drawer.js",
    "public/js/fontawesome.js",
    "public/js/fontawesome.min.js",
    "public/js/regular.js",
    "public/js/regular.min.js",
    "public/js/solid.js",
    "public/js/solid.min.js",
    "public/js/v4-shims.js",
    "public/js/v4-shims.min.js"
)

$RemoveDirectories = @(
    "public/less",
    "public/scss",
    "public/metadata",
    "public/sprites"
)

$Removed = 0
foreach ($RelativePath in @($RemoveFiles + $RemoveDirectories)) {
    $Target = Join-Path $ProjectRoot $RelativePath
    if (Test-Path -LiteralPath $Target) {
        Remove-Item -LiteralPath $Target -Recurse -Force -WhatIf:$WhatIf
        $Removed++
    }
}

if ($WhatIf) {
    Write-Host "Dry run complete. No files were removed."
} else {
    Write-Host "M2-E cleanup complete: $Removed path(s)"
    Write-Host "Run git status --short to review the deletions."
}
