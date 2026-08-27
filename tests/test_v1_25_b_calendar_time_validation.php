<?php

declare(strict_types=1);

function calendar_validate_event_range(mixed $start, mixed $end): ?array
{
    if (!is_string($start) || !is_string($end)
        || preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) !== 1
        || preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) !== 1
        || $end < $start) {
        return null;
    }
    return [$start, $end];
}

function app_validate_external_link(mixed $value, int $maxLength): ?string
{
    if (!is_string($value) || strlen($value) > $maxLength || filter_var($value, FILTER_VALIDATE_URL) === false) {
        return null;
    }
    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $value : null;
}

require __DIR__ . '/../app/calendar_time.php';

function v125b_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$legacy = calendar_event_time_settings('1', '', '', '', '2026-08-27', '2026-08-27');
v125b_assert(is_array($legacy), 'legacy all-day request is accepted');
v125b_assert(
    $legacy['all_day'] === true
        && $legacy['start_time'] === null
        && $legacy['end_time'] === null
        && $legacy['url'] === null,
    'legacy request normalizes to all-day NULL metadata'
);

$timed = calendar_event_time_settings(
    '0',
    '09:30',
    '10:45',
    'https://example.com/item?id=1',
    '2026-08-27',
    '2026-08-27'
);
v125b_assert(is_array($timed), 'timed request is accepted');
v125b_assert(
    $timed['all_day'] === false
        && $timed['start_time'] === '09:30:00'
        && $timed['end_time'] === '10:45:00',
    'times normalize to HH:MM:SS'
);
v125b_assert($timed['url'] === 'https://example.com/item?id=1', 'https URL is retained');

v125b_assert(
    calendar_event_time_settings('0', '', '10:00', '', '2026-08-27', '2026-08-27') === null,
    'timed event requires start time'
);
v125b_assert(
    calendar_event_time_settings('0', '10:00', '09:00', '', '2026-08-27', '2026-08-27') === null,
    'same-day end before start is rejected'
);
v125b_assert(
    is_array(calendar_event_time_settings('0', '23:00', '01:00', '', '2026-08-27', '2026-08-28')),
    'multi-day event may end at an earlier clock time'
);
v125b_assert(
    calendar_event_time_settings('0', '24:00', '', '', '2026-08-27', '2026-08-27') === null,
    '24:00 is rejected'
);
v125b_assert(
    calendar_event_time_settings('0', '09:00', '', 'javascript:alert(1)', '2026-08-27', '2026-08-27') === null,
    'non-http(s) URL is rejected'
);

$allDayWithTimes = calendar_event_time_settings(
    '1',
    '09:00',
    '10:00',
    'https://example.com/',
    '2026-08-27',
    '2026-08-27'
);
v125b_assert(
    is_array($allDayWithTimes)
        && $allDayWithTimes['start_time'] === null
        && $allDayWithTimes['end_time'] === null,
    'all-day request clears clock values'
);
v125b_assert(calendar_event_time_public_clock('09:30:00') === '09:30', 'DB TIME is exposed as HH:MM');

echo "PASS: V1.25-B Calendar time validation\n";
