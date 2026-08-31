<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$domain = file_get_contents($root . '/app/calendar_recurrence.php');
$api = file_get_contents($root . '/public/calendar_recurrence_api.php');
$ui = file_get_contents($root . '/public/js/calendar-recurrence.js');
$loader = file_get_contents($root . '/public/js/calendar.js');
$version = file_get_contents($root . '/app/version.php');
$migration = file_get_contents($root . '/database/migrations/019_v1_25_calendar_recurrence.sql');
$schema = file_get_contents($root . '/database/schema.sql');
$css = file_get_contents($root . '/public/css/calendar-recurrence.css');

foreach ([$domain, $api, $ui, $loader, $version, $migration, $schema, $css] as $source) {
    if (!is_string($source)) {
        fwrite(STDERR, "FAIL: V1.25-D source read\n");
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
    'current APP_VERSION is defined' => is_string($appVersion) && preg_match('/^\d+\.\d+\.\d+(?:-[A-Za-z0-9._-]+)?$/', $appVersion) === 1,
    'active asset revision is defined' => is_string($assetRevision) && preg_match('/^[A-Za-z0-9._-]+$/', $assetRevision) === 1,
    'migration adds repeat type' => str_contains($migration, 'calendar_event_repeat_type'),
    'migration defaults repeat type to none' => str_contains($migration, "DEFAULT ''none''"),
    'migration adds nullable repeat until' => str_contains($migration, 'calendar_event_repeat_until') && str_contains($migration, 'DATE NULL DEFAULT NULL'),
    'migration is idempotent per column' => substr_count($migration, 'information_schema.COLUMNS') === 2,
    'fresh-install schema integrates recurrence migration' => str_contains($schema, 'V1.25 Calendar recurrence (019)'),
    'fresh-install schema adds repeat type' => str_contains($schema, "`calendar_event_repeat_type` VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT ''none''"),
    'fresh-install schema adds repeat until' => str_contains($schema, '`calendar_event_repeat_until` DATE NULL DEFAULT NULL'),
    'repeat allowlist is fixed' => str_contains($domain, "['none', 'daily', 'weekly', 'monthly', 'yearly']"),
    'active recurring series bound exists' => str_contains($domain, 'CALENDAR_RECURRENCE_MAX_ACTIVE_SERIES = 50'),
    'monthly occurrence bound exists' => str_contains($domain, 'CALENDAR_RECURRENCE_MAX_MONTH_OCCURRENCES = 2000'),
    'owner scope protects recurrence update' => str_contains($domain, 'calendar_event_id = :event_id AND calendar_event_owner = :owner AND calendar_event_flag = 0'),
    'recurrence count is owner scoped' => str_contains($domain, 'calendar_event_owner = :owner AND calendar_event_flag = 0'),
    'recurrence wraps B time/color transaction' => str_contains($domain, 'calendar_event_time_color_create(') && str_contains($domain, 'calendar_event_time_color_update('),
    'API is POST only' => str_contains($api, "REQUEST_METHOD'] ?? 'GET') !== 'POST'"),
    'API requires authenticated session' => str_contains($api, 'app_session_user_id()') && str_contains($api, "Authentication is required."),
    'API requires CSRF' => str_contains($api, 'app_csrf_is_valid'),
    'API enforces request body limit' => str_contains($api, 'APP_API_MAX_REQUEST_BYTES'),
    'API uses fixed action allowlist' => str_contains($api, "['calendar.recurrence.list', 'calendar.upcoming.list', 'calendar.recurrence.create', 'calendar.recurrence.update']"),
    'API releases session before DB work' => str_contains($api, 'app_session_release();'),
    'API ownership comes from session user id' => str_contains($api, 'calendar_event_recurrence_month_list($userId') && str_contains($api, '$userId,'),
    'API validates color/time/repeat settings' => str_contains($api, 'calendar_event_color_validate') && str_contains($api, 'calendar_event_time_settings') && str_contains($api, 'calendar_event_recurrence_settings'),
    'API exposes no delete action' => !str_contains($api, 'calendar.recurrence.delete'),
    'UI offers four repeat cadences' => str_contains($ui, "['daily', '毎日']") && str_contains($ui, "['weekly', '毎週']") && str_contains($ui, "['monthly', '毎月']") && str_contains($ui, "['yearly', '毎年']"),
    'UI explains series edit/delete semantics' => str_contains($ui, '変更・削除はシリーズ全体に反映されます'),
    'UI sends repeat fields' => str_contains($ui, 'calendar_event_repeat_type:') && str_contains($ui, 'calendar_event_repeat_until:'),
    'UI submit wins before C/color handlers' => str_contains($ui, 'event.stopImmediatePropagation();'),
    'UI uses recurrence API endpoint' => str_contains($ui, "var endpoint = './calendar_recurrence_api.php';"),
    'UI renders recurring marker' => str_contains($ui, 'calendar-event-repeat-label') && str_contains($css, '.calendar-event-repeat-label'),
    'UI does not assign innerHTML' => !preg_match('/\.innerHTML\s*=/', $ui),
    'UI does not use eval' => !preg_match('/\beval\s*\(/', $ui),
    'recurrence layer is loaded with current asset cache key' => is_string($assetRevision)
        && str_contains($loader, 'calendar-recurrence.js?v=' . $assetRevision)
        && str_contains($loader, 'calendar-recurrence.css?v=' . $assetRevision),
];

$corePos = is_string($assetRevision) ? strpos($loader, "loadScript('./js/calendar-core.js?v={$assetRevision}');") : false;
$repeatPos = is_string($assetRevision) ? strpos($loader, "loadScript('./js/calendar-recurrence.js?v={$assetRevision}');") : false;
$detailPos = is_string($assetRevision) ? strpos($loader, "loadScript('./js/calendar-event-details.js?v={$assetRevision}');") : false;
$colorPos = is_string($assetRevision) ? strpos($loader, "loadScript('./js/calendar-colors.js?v={$assetRevision}');") : false;
$checks['script order is core -> recurrence -> details -> color'] = is_int($corePos) && is_int($repeatPos) && is_int($detailPos) && is_int($colorPos)
    && $corePos < $repeatPos && $repeatPos < $detailPos && $detailPos < $colorPos;

$forbiddenFetchPatterns = [
    'curl_exec(',
    'file_get_contents($url',
    'fopen($url',
    'app_http_fetch',
];
$hasOutbound = false;
foreach ($forbiddenFetchPatterns as $pattern) {
    if (str_contains($domain . $api, $pattern)) {
        $hasOutbound = true;
    }
}
$checks['recurrence layer performs no outbound URL fetch'] = !$hasOutbound;

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

echo 'PASS: V1.25-D recurrence contract (' . count($checks) . " checks)\n";
