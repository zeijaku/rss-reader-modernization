<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'calendar_time' => $root . '/app/calendar_time.php',
    'calendar_api' => $root . '/public/calendar_color_api.php',
    'migration' => $root . '/database/migrations/018_v1_25_calendar_event_time_url.sql',
    'schema' => $root . '/database/schema.sql',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "FAIL: {$label} file is missing\n");
        exit(1);
    }
    $files[$label] = (string) file_get_contents($path);
}

function v125b_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

foreach (['calendar_event_all_day', 'calendar_event_start_time', 'calendar_event_end_time', 'calendar_event_url'] as $column) {
    v125b_contract_assert(str_contains($files['migration'], $column), "migration contains {$column}");
    v125b_contract_assert(str_contains($files['schema'], $column), "fresh schema contains {$column}");
}

v125b_contract_assert(
    str_contains($files['migration'], 'TINYINT UNSIGNED NOT NULL DEFAULT 1'),
    'existing events default to all-day'
);
v125b_contract_assert(
    str_contains($files['migration'], 'VARCHAR(2048) NULL DEFAULT NULL'),
    'Calendar URL uses nullable 2048-char storage'
);

v125b_contract_assert(
    str_contains($files['calendar_api'], "'calendar.event.meta.list'"),
    'Calendar API exposes allowlisted metadata list action'
);
v125b_contract_assert(
    str_contains($files['calendar_api'], "\$_POST['calendar_event_all_day'] ?? '1'"),
    'legacy create/update requests default to all-day'
);
v125b_contract_assert(
    str_contains($files['calendar_api'], 'calendar_event_time_color_create(')
        && str_contains($files['calendar_api'], 'calendar_event_time_color_update('),
    'Calendar color create/update persist time metadata in the shared transaction path'
);

v125b_contract_assert(
    str_contains($files['calendar_time'], 'app_validate_external_link($value, 2048)'),
    'URL uses the existing external-link validator'
);
v125b_contract_assert(
    str_contains($files['calendar_time'], 'calendar_event_owner = :owner AND calendar_event_flag = 0'),
    'metadata reads/writes retain owner scope and active-row scope'
);
v125b_contract_assert(
    !str_contains($files['calendar_time'], 'curl_exec(')
        && !str_contains($files['calendar_time'], 'file_get_contents($url')
        && !str_contains($files['calendar_time'], 'fopen($url'),
    'Calendar URL storage does not introduce outbound URL fetching'
);

v125b_contract_assert(
    str_contains($files['calendar_time'], '$range[0] === $range[1] && $endTime !== \'\' && $endTime < $startTime'),
    'same-day reversed time range is rejected'
);

v125b_contract_assert(
    !str_contains($files['schema'], 'APP_VERSION') && !str_contains($files['migration'], 'APP_VERSION'),
    'DB foundation does not alter application versioning'
);

echo "PASS: V1.25-B Calendar time/schema/security contract\n";
