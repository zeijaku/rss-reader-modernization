<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$upcoming = file_get_contents($root . '/app/calendar_upcoming.php');
$api = file_get_contents($root . '/public/calendar_recurrence_api.php');
$ui = file_get_contents($root . '/public/js/calendar-polish.js');
$css = file_get_contents($root . '/public/css/calendar-polish.css');
$loader = file_get_contents($root . '/public/js/calendar.js');
$version = file_get_contents($root . '/app/version.php');

foreach ([$upcoming, $api, $ui, $css, $loader, $version] as $source) {
    if (!is_string($source)) {
        fwrite(STDERR, "FAIL: V1.25-F source read\n");
        exit(1);
    }
}

$checks = [
    'formal APP_VERSION is V1.25.0' => str_contains($version, "const APP_VERSION = '1.25.0';"),
    'formal asset revision is V1.25.0' => str_contains($version, "const APP_ASSET_REVISION = '1.25.0';"),
    'F CSS uses release cache key' => str_contains($loader, "calendar-polish.css?v=1.25.0"),
    'F JS uses release cache key' => str_contains($loader, "calendar-polish.js?v=1.25.0"),
    'upcoming window is fixed to 14 days' => str_contains($upcoming, 'CALENDAR_UPCOMING_DAYS = 14'),
    'upcoming result is bounded to 8 events' => str_contains($upcoming, 'CALENDAR_UPCOMING_LIMIT = 8')
        && str_contains($upcoming, 'array_slice($events, 0, CALENDAR_UPCOMING_LIMIT)'),
    'non-recurring upcoming query is owner scoped' => str_contains($upcoming, 'calendar_event_owner = :owner AND calendar_event_flag = 0'),
    'non-recurring upcoming query excludes recurring source rows' => str_contains($upcoming, "calendar_event_repeat_type = 'none'"),
    'recurring upcoming expansion keeps owner scope' => str_contains($upcoming, 'calendar_event_recurrence_month_list(')
        && str_contains($upcoming, '$ownerId,'),
    'upcoming query is bounded' => str_contains($upcoming, 'LIMIT 500'),
    'upcoming helper performs no outbound URL fetch' => !str_contains($upcoming, 'curl_exec(')
        && !str_contains($upcoming, 'file_get_contents($url')
        && !str_contains($upcoming, 'fopen($url')
        && !str_contains($upcoming, 'app_http_fetch'),
    'existing recurrence API remains POST only' => str_contains($api, "REQUEST_METHOD'] ?? 'GET') !== 'POST'"),
    'existing recurrence API still requires auth' => str_contains($api, 'app_session_user_id()')
        && str_contains($api, 'Authentication is required.'),
    'existing recurrence API still requires CSRF' => str_contains($api, 'app_csrf_is_valid'),
    'existing recurrence API still enforces body limit' => str_contains($api, 'APP_API_MAX_REQUEST_BYTES'),
    'upcoming action is in fixed allowlist' => str_contains($api, "['calendar.recurrence.list', 'calendar.upcoming.list', 'calendar.recurrence.create', 'calendar.recurrence.update']"),
    'upcoming action uses server date' => str_contains($api, "substr((string) app_now(), 0, 10)")
        && str_contains($api, 'calendar_event_upcoming_list($userId, $today)'),
    'upcoming action does not accept client date range' => !str_contains($api, "\$_POST['upcoming_start']")
        && !str_contains($api, "\$_POST['upcoming_end']"),
    'Today button label is polished to 今日' => str_contains($ui, "\$button.text('今日')"),
    'Today cell exposes aria-current date' => str_contains($ui, ".attr('aria-current', 'date')"),
    'Today navigation can restore focus to current day' => str_contains($ui, "data-calendar-focus-today")
        && str_contains($ui, 'focusTodayCell('),
    'modal active descendant is blurred before Bootstrap hide' => str_contains($ui, "addEventListener('hide.bs.modal', modalHide)")
        && str_contains($ui, 'releaseModalFocusBeforeHide(event.target)')
        && str_contains($ui, 'active.blur();'),
    'modal focus is restored only after hidden event' => str_contains($ui, "addEventListener('hidden.bs.modal', modalHidden)")
        && str_contains($ui, 'restoreModalFocusAfterHide(event.target)'),
    'focus restoration prefers opening trigger' => str_contains($ui, 'lastModalTrigger')
        && str_contains($ui, 'fallbackFocusTarget()'),
    'F does not manually write aria-hidden or inert' => !preg_match('/setAttribute\([\'\"]aria-hidden[\'\"]/', $ui)
        && !preg_match('/\.inert\s*=/', $ui),
    'upcoming section is injected into existing Calendar card' => str_contains($ui, "addClass('calendar-upcoming')")
        && str_contains($ui, "insertAfter(\$days)"),
    'upcoming items reuse existing Calendar edit modal' => str_contains($ui, 'calendar-upcoming-item')
        && str_contains($ui, 'calendar-event-edit-trigger')
        && str_contains($ui, "data-bs-target', '#changeCalendarEvent'"),
    'upcoming items carry B/C/D metadata' => str_contains($ui, 'data-calendar-event-all-day')
        && str_contains($ui, 'data-calendar-event-start-time')
        && str_contains($ui, 'data-calendar-event-url')
        && str_contains($ui, 'data-calendar-event-repeat-type'),
    'upcoming client calls only same-origin Calendar API' => str_contains($ui, "var endpoint = './calendar_recurrence_api.php';")
        && !str_contains($ui, 'http://')
        && !str_contains($ui, 'https://'),
    'F UI does not assign innerHTML' => !preg_match('/\.innerHTML\s*=/', $ui),
    'F UI does not use eval' => !preg_match('/\beval\s*\(/', $ui),
    'Smartphone Calendar rules exist' => str_contains($css, '@media (max-width: 575.98px)')
        && str_contains($css, '.calendar-upcoming-item')
        && str_contains($css, '.calendar-toolbar')
        && str_contains($css, '.calendar-day'),
];

$sourcePos = strpos($loader, "loadScript('./js/calendar-source-actions.js?v=1.25.0');");
$polishPos = strpos($loader, "loadScript('./js/calendar-polish.js?v=1.25.0');");
$checks['F polish loads after E source actions'] = is_int($sourcePos) && is_int($polishPos) && $sourcePos < $polishPos;

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

echo 'PASS: V1.25-F Calendar polish contract (' . count($checks) . " checks)\n";
