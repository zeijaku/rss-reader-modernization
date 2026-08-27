<?php

declare(strict_types=1);

function calendar_validate_date(mixed $value): ?string
{
    if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date instanceof DateTimeImmutable
        || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
        || $date->format('Y-m-d') !== $value
        || (int) $date->format('Y') < 2000
        || (int) $date->format('Y') > 2100) {
        return null;
    }
    return $value;
}

function calendar_validate_event_range(mixed $start, mixed $end): ?array
{
    $start = calendar_validate_date($start);
    $end = calendar_validate_date($end);
    if ($start === null || $end === null || $end < $start) {
        return null;
    }
    $days = (int) (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days;
    return $days <= 365 ? [$start, $end] : null;
}

function db_table_identifier(string $name): string { return '`' . $name . '`'; }
function conn_db(): PDO { throw new RuntimeException('DB not used in pure recurrence test'); }
function calendar_normalize_event_row(array $row): ?array { return $row; }
function calendar_event_color_validate(mixed $value): ?string { return is_string($value) && in_array($value, ['red','blue','green'], true) ? $value : null; }
function calendar_event_time_validate_all_day(mixed $value): ?bool { return in_array($value, [true,1,'1'], true) ? true : (in_array($value, [false,0,'0'], true) ? false : null); }
function calendar_event_time_public_clock(mixed $value): ?string { return is_string($value) && preg_match('/^\d{2}:\d{2}/', $value) ? substr($value,0,5) : null; }
function calendar_event_time_validate_url(mixed $value): string|false { return $value === null || $value === '' ? '' : (is_string($value) && preg_match('#^https?://#', $value) ? $value : false); }
function calendar_event_time_color_create(...$args): int { return 1; }
function calendar_event_time_color_update(...$args): bool { return true; }
function calendar_validate_year(mixed $value): ?int { return is_int($value) && $value >= 2000 && $value <= 2100 ? $value : null; }
function calendar_validate_month(mixed $value): ?int { return is_int($value) && $value >= 1 && $value <= 12 ? $value : null; }
function calendar_month_range(int $year, int $month): array
{
    $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
    return ['start' => $start->format('Y-m-d'), 'end' => $start->modify('last day of this month')->format('Y-m-d')];
}

require __DIR__ . '/../app/calendar_recurrence.php';

function v125d_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS: {$message}\n");
}

v125d_assert(calendar_event_recurrence_validate_type('daily') === 'daily', 'daily repeat type accepted');
v125d_assert(calendar_event_recurrence_validate_type('hourly') === null, 'unknown repeat type rejected');
v125d_assert(calendar_event_recurrence_settings('none', '2026-12-31', '2026-08-01', '2026-08-01') === ['repeat_type' => 'none', 'repeat_until' => null], 'none clears repeat-until');
v125d_assert(calendar_event_recurrence_settings('daily', '', '2026-08-01', '2026-08-02') === null, 'daily multi-day range rejected');
v125d_assert(is_array(calendar_event_recurrence_settings('weekly', '', '2026-08-01', '2026-08-07')), 'weekly up to six-day span accepted');
v125d_assert(calendar_event_recurrence_settings('weekly', '', '2026-08-01', '2026-08-08') === null, 'weekly seven-day span rejected');
v125d_assert(calendar_event_recurrence_settings('monthly', '', '2026-08-01', '2026-08-29') === null, 'monthly 28-day span rejected');
v125d_assert(calendar_event_recurrence_settings('yearly', '2026-01-01', '2026-08-01', '2026-08-01') === null, 'repeat-until before start rejected');

v125d_assert(
    calendar_recurrence_occurrence_starts('daily', '2026-08-01', '2026-08-28', '2026-08-31', null)
        === ['2026-08-28', '2026-08-29', '2026-08-30', '2026-08-31'],
    'daily occurrences jump directly to target window'
);
v125d_assert(
    calendar_recurrence_occurrence_starts('weekly', '2026-08-03', '2026-08-01', '2026-08-31', null)
        === ['2026-08-03', '2026-08-10', '2026-08-17', '2026-08-24', '2026-08-31'],
    'weekly occurrences preserve anchor weekday'
);
v125d_assert(
    calendar_recurrence_occurrence_starts('monthly', '2026-01-31', '2026-02-01', '2026-02-28', null) === [],
    'monthly 31st skips month without that date'
);
v125d_assert(
    calendar_recurrence_occurrence_starts('monthly', '2026-01-31', '2026-03-01', '2026-03-31', null) === ['2026-03-31'],
    'monthly 31st resumes on valid month'
);
v125d_assert(
    calendar_recurrence_occurrence_starts('yearly', '2024-02-29', '2027-01-01', '2027-12-31', null) === [],
    'yearly Feb 29 skips non-leap year'
);
v125d_assert(
    calendar_recurrence_occurrence_starts('yearly', '2024-02-29', '2028-01-01', '2028-12-31', null) === ['2028-02-29'],
    'yearly Feb 29 returns in leap year'
);
v125d_assert(
    calendar_recurrence_occurrence_starts('weekly', '2026-08-03', '2026-08-01', '2026-08-31', '2026-08-17')
        === ['2026-08-03', '2026-08-10', '2026-08-17'],
    'repeat-until is inclusive'
);
v125d_assert(CALENDAR_RECURRENCE_MAX_ACTIVE_SERIES === 50, 'active recurring-series resource bound fixed at 50');
v125d_assert(CALENDAR_RECURRENCE_MAX_MONTH_OCCURRENCES === 2000, 'monthly occurrence resource bound fixed at 2000');

echo "PASS: V1.25-D recurrence validation suite\n";
