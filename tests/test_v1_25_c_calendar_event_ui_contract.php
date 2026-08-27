<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$detailsPath = $root . '/public/js/calendar-event-details.js';
$loaderPath = $root . '/public/js/calendar.js';
$stylePath = $root . '/public/css/calendar-event-details.css';
$versionPath = $root . '/app/version.php';

$details = file_get_contents($detailsPath);
$loader = file_get_contents($loaderPath);
$style = file_get_contents($stylePath);
$version = file_get_contents($versionPath);

if (!is_string($details) || !is_string($loader) || !is_string($style) || !is_string($version)) {
    fwrite(STDERR, "V1.25-C source file could not be read.\n");
    exit(1);
}

$checks = [
    'formal APP_VERSION stays V1.24.0' => str_contains($version, "const APP_VERSION = '1.24.0';"),
    'current staged asset revision includes V1.25-D' => str_contains($version, "const APP_ASSET_REVISION = '1.25-d1';"),
    'detail CSS is staged by Calendar loader' => str_contains($loader, 'calendar-event-details.css?v=1.25-c1'),
    'detail JS is staged by Calendar loader' => str_contains($loader, 'calendar-event-details.js?v=1.25-c1'),
    'all-day field exists' => str_contains($details, "CalendarEventAllDay"),
    'start time field exists' => str_contains($details, "CalendarEventStartTime"),
    'end time field exists' => str_contains($details, "CalendarEventEndTime"),
    'URL field exists' => str_contains($details, "CalendarEventUrl"),
    'time input is used' => str_contains($details, "startTime.type = 'time';") && str_contains($details, "endTime.type = 'time';"),
    'URL input is used' => str_contains($details, "url.type = 'url';"),
    'URL length matches server boundary' => str_contains($details, 'url.maxLength = 2048;'),
    'meta list action is allowlisted client-side' => str_contains($details, "request('calendar.event.meta.list'"),
    'event create/update uses existing Calendar endpoint' => str_contains($details, "var endpoint = './calendar_color_api.php';")
        && str_contains($details, "'calendar.color.update' : 'calendar.color.create'"),
    'single transaction submit wins before legacy handlers' => str_contains($details, 'event.stopImmediatePropagation();'),
    'all-day payload clears clock values' => str_contains($details, "calendar_event_start_time: isAllDay ? ''")
        && str_contains($details, "calendar_event_end_time: isAllDay ? ''"),
    'URL and all-day are sent to B API' => str_contains($details, 'calendar_event_url:')
        && str_contains($details, 'calendar_event_all_day:'),
    'no innerHTML assignment in new UI layer' => !preg_match('/\.innerHTML\s*=/', $details),
    'no eval in new UI layer' => !preg_match('/\beval\s*\(/', $details),
    'timed label style exists' => str_contains($style, '.calendar-event-time-label'),
    'smartphone time inputs can stack' => str_contains($details, 'col-12 col-sm-6'),
];

$corePos = strpos($loader, "loadScript('./js/calendar-core.js?v=1.9.0');");
$detailPos = strpos($loader, "loadScript('./js/calendar-event-details.js?v=1.25-c1');");
$colorPos = strpos($loader, "loadScript('./js/calendar-colors.js?v=1.24.0');");
$checks['script order is core -> details -> color'] = is_int($corePos) && is_int($detailPos) && is_int($colorPos)
    && $corePos < $detailPos && $detailPos < $colorPos;

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

fwrite(STDOUT, 'PASS: V1.25-C Calendar event UI contract (' . count($checks) . " checks)\n");
