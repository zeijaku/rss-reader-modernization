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

$checks = [
    'formal APP_VERSION stays V1.24.0' => str_contains($version, "const APP_VERSION = '1.24.0';"),
    'asset revision is F R3 staged key' => str_contains($version, "const APP_ASSET_REVISION = '1.25-f3';"),
    'R2 Calendar polish remains loaded' => str_contains($loader, "calendar-polish.js?v=1.25-f2"),
    'R3 CSS is staged' => str_contains($loader, "calendar-polish-r3.css?v=1.25-f3"),
    'R3 JS is staged' => str_contains($loader, "calendar-polish-r3.js?v=1.25-f3"),
    'R3 JS loads after R2 polish' => strpos($loader, "calendar-polish.js?v=1.25-f2") < strpos($loader, "calendar-polish-r3.js?v=1.25-f3"),
    'upcoming collapsed limit is three' => str_contains($ui, 'upcomingCollapsedLimit = 3'),
    'upcoming extra items are hidden while collapsed' => str_contains($ui, "index >= upcomingCollapsedLimit")
        && str_contains($ui, ".prop('hidden', !expanded"),
    'upcoming toggle exposes more label' => str_contains($ui, "もっと見る（") && str_contains($ui, "閉じる"),
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
