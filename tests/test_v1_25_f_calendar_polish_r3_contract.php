<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$ui = file_get_contents($root . '/public/js/calendar-polish-r3.js');
$css = file_get_contents($root . '/public/css/calendar-polish-r3.css');
$loader = file_get_contents($root . '/public/js/calendar.js');
$version = file_get_contents($root . '/app/version.php');

foreach ([$ui, $css, $loader, $version] as $source) {
    if (!is_string($source)) {
        fwrite(STDERR, "FAIL: V1.25-F R3 source read\n");
        exit(1);
    }
}

$appVersion = null;
$assetRevision = null;
if (preg_match("/const APP_VERSION = '([^']+)';/", $version, $versionMatch) === 1) {
    $appVersion = $versionMatch[1];
}
if (preg_match("/const APP_ASSET_REVISION = '([^']+)';/", $version, $assetMatch) === 1) {
    $assetRevision = $assetMatch[1];
}

$checks = [
    'formal APP_VERSION is defined' => is_string($appVersion) && preg_match('/^\d+\.\d+\.\d+$/', $appVersion) === 1,
    'formal asset revision follows APP_VERSION' => is_string($assetRevision) && $assetRevision === $appVersion,
    'R2 Calendar polish uses current release cache key' => is_string($assetRevision)
        && str_contains($loader, 'calendar-polish.js?v=' . $assetRevision),
    'R3 CSS uses current release cache key' => is_string($assetRevision)
        && str_contains($loader, 'calendar-polish-r3.css?v=' . $assetRevision),
    'R3 JS uses current release cache key' => is_string($assetRevision)
        && str_contains($loader, 'calendar-polish-r3.js?v=' . $assetRevision),
    'upcoming collapsed limit is three' => str_contains($ui, 'upcomingCollapsedLimit = 3'),
    'upcoming extra items are hidden while collapsed' => str_contains($ui, 'index >= upcomingCollapsedLimit')
        && str_contains($ui, ".prop('hidden', !expanded"),
    'upcoming toggle exposes more label' => str_contains($ui, 'もっと見る（') && str_contains($ui, '閉じる'),
    'upcoming toggle exposes aria-expanded' => str_contains($ui, ".attr('aria-expanded', expanded ? 'true' : 'false')"),
    'month navigation capture covers previous next and today' => str_contains($ui, '.calendar-prev-month, .calendar-next-month, .calendar-today')
        && str_contains($ui, "addEventListener('click', holdCalendarHeight, true)"),
    'calendar height is measured before core empties grid' => str_contains($ui, 'getBoundingClientRect().height')
        && str_contains($ui, "days.style.minHeight = Math.ceil(height) + 'px'"),
    'height hold has explicit marker' => str_contains($ui, 'data-calendar-height-held'),
    'height releases after ajax settles' => str_contains($ui, '$(document).ajaxStop(function ()')
        && str_contains($ui, 'releaseHeldCalendarHeights(false)'),
    'height hold has bounded fallback release' => str_contains($ui, '6000')
        && str_contains($ui, 'releaseHeldCalendarHeights(true)'),
    'R3 does not make API requests' => !str_contains($ui, '$.ajax(')
        && !str_contains($ui, 'fetch(')
        && !str_contains($ui, 'XMLHttpRequest'),
    'R3 does not assign innerHTML' => !preg_match('/\.innerHTML\s*=/', $ui),
    'R3 does not use eval' => !preg_match('/\beval\s*\(/', $ui),
    'hidden upcoming items are removed from layout' => str_contains($css, '.calendar-upcoming-item[hidden]')
        && str_contains($css, 'display: none !important;'),
    'smartphone toggle rules exist' => str_contains($css, '@media (max-width: 575.98px)')
        && str_contains($css, '.calendar-upcoming-toggle'),
];

$r2Pos = is_string($assetRevision) ? strpos($loader, "calendar-polish.js?v={$assetRevision}") : false;
$r3Pos = is_string($assetRevision) ? strpos($loader, "calendar-polish-r3.js?v={$assetRevision}") : false;
$checks['R3 JS loads after R2 polish'] = is_int($r2Pos) && is_int($r3Pos) && $r2Pos < $r3Pos;

$failed = [];
foreach ($checks as $name => $passed) {
    if ($passed) {
        fwrite(STDOUT, "PASS: {$name}\n");
    } else {
        fwrite(STDERR, "FAIL: {$name}\n");
        $failed[] = $name;
    }
}

if ($failed !== []) {
    exit(1);
}

echo 'PASS: V1.25-F R3 Calendar polish contract (' . count($checks) . " checks)\n";
