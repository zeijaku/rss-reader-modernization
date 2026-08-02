param(
    [switch]$WhatIf
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = "Stop"

$Root = Split-Path -Parent $PSScriptRoot
$Marker1 = Join-Path $Root "app\version.php"
$Marker2 = Join-Path $Root "public\index.php"

if (-not (Test-Path -LiteralPath $Marker1 -PathType Leaf)) {
    throw "Project marker not found: app/version.php"
}
if (-not (Test-Path -LiteralPath $Marker2 -PathType Leaf)) {
    throw "Project marker not found: public/index.php"
}

$RelativeTargets = @(
    "licenses\fontawesome-5.3.1-LICENSE.txt"
)

foreach ($Relative in $RelativeTargets) {
    $Target = Join-Path $Root $Relative
    $FullTarget = [System.IO.Path]::GetFullPath($Target)
    $FullRoot = [System.IO.Path]::GetFullPath($Root + [System.IO.Path]::DirectorySeparatorChar)

    if (-not $FullTarget.StartsWith($FullRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Unsafe cleanup target: $Relative"
    }

    if (Test-Path -LiteralPath $FullTarget -PathType Leaf) {
        if ($WhatIf) {
            Write-Host "WHATIF remove $Relative"
        }
        else {
            Remove-Item -LiteralPath $FullTarget -Force
            Write-Host "Removed $Relative"
        }
    }
    else {
        Write-Host "Already absent $Relative"
    }
}
