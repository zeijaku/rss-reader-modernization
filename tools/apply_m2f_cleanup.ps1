param(
    [switch]$WhatIf
)

$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot

if (-not (Test-Path -LiteralPath (Join-Path $ProjectRoot ".git"))) {
    throw "Git working tree not found: $ProjectRoot"
}

if (-not (Test-Path -LiteralPath (Join-Path $ProjectRoot "public/index.php"))) {
    throw "public/index.php not found: $ProjectRoot"
}

$RemoveFiles = @(
    "public/js/jquery-3.3.1.min.js",
    "public/webfonts/fa-brands-400.eot",
    "public/webfonts/fa-brands-400.svg",
    "public/webfonts/fa-brands-400.woff",
    "public/webfonts/fa-regular-400.eot",
    "public/webfonts/fa-regular-400.svg",
    "public/webfonts/fa-regular-400.woff",
    "public/webfonts/fa-solid-900.eot",
    "public/webfonts/fa-solid-900.svg",
    "public/webfonts/fa-solid-900.woff"
)

$Removed = 0
foreach ($RelativePath in $RemoveFiles) {
    $Target = Join-Path $ProjectRoot $RelativePath
    if (Test-Path -LiteralPath $Target) {
        Remove-Item -LiteralPath $Target -Force -WhatIf:$WhatIf
        $Removed++
    }
}

if ($WhatIf) {
    Write-Host "Dry run complete. No files were removed."
} else {
    Write-Host "M2-F cleanup complete: $Removed file(s)"
    Write-Host "Run git status --short to review the changes."
}
