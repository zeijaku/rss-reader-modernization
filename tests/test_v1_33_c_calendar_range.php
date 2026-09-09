<?php

declare(strict_types=1);

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function conn_db(): PDO
{
    global $pdo;
    return $pdo;
}

function db_table_identifier(string $name): string
{
    return '"' . str_replace('"', '""', $name) . '"';
}

function app_validate_positive_int(mixed $value): ?int
{
    if (is_int($value)) {
        return $value > 0 ? $value : null;
    }
    return is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1 ? (int) $value : null;
}

function app_validate_text(mixed $value, int $maxLength, bool $allowEmpty): ?string
{
    if (!is_string($value) || (!$allowEmpty && trim($value) === '') || mb_strlen($value, 'UTF-8') > $maxLength) {
        return null;
    }
    return $value;
}

function app_now(): string
{
    return '2026-09-08 12:00:00';
}

function dashboard_widget_decode_config(mixed $value): array
{
    if (!is_string($value)) {
        return [];
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function dashboard_widget_validate_boolean(mixed $value): ?bool
{
    return match ($value) {
        true, 1, '1' => true,
        false, 0, '0' => false,
        default => null,
    };
}

function dashboard_widget_validate_task_title(mixed $value): ?string
{
    return app_validate_text($value, 128, false);
}

function dashboard_widget_validate_task_due_date(mixed $value): ?string
{
    return $value === null || $value === '' ? '' : calendar_validate_date($value);
}

function dashboard_widget_validate_task_priority(mixed $value): ?string
{
    return is_string($value) && in_array($value, ['low', 'normal', 'high'], true) ? $value : null;
}

function app_validate_external_link(mixed $value, int $maxLength): ?string
{
    if (!is_string($value) || strlen($value) > $maxLength) {
        return null;
    }
    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $value : null;
}

function japanese_holiday_current_data(): array
{
    return [
        'holidays' => [
            '2026-08-11' => '山の日',
            '2026-09-21' => '敬老の日',
            '2026-09-22' => '国民の休日',
            '2026-10-12' => 'スポーツの日',
        ],
        'refresh_due' => false,
        'source' => 'test-fixture',
    ];
}

require __DIR__ . '/../app/calendar.php';
require __DIR__ . '/../app/calendar_color.php';
require __DIR__ . '/../app/calendar_time.php';
require __DIR__ . '/../app/calendar_recurrence.php';
require __DIR__ . '/../app/calendar_range.php';
require __DIR__ . '/../app/calendar_upcoming.php';

$pass = 0;
$fail = 0;
function c_assert(bool $condition, string $message): void
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "PASS: {$message}\n";
        return;
    }
    $fail++;
    fwrite(STDERR, "FAIL: {$message}\n");
}

$pdo->exec('CREATE TABLE dashboard_widget (
    widget_id INTEGER PRIMARY KEY, widget_owner INTEGER NOT NULL, widget_type TEXT NOT NULL,
    widget_flag INTEGER NOT NULL DEFAULT 0, widget_config TEXT NOT NULL
)');
$pdo->exec('CREATE TABLE calendar_event (
    calendar_event_id INTEGER PRIMARY KEY AUTOINCREMENT,
    calendar_event_date TEXT NOT NULL, calendar_event_updated_at TEXT NOT NULL,
    calendar_event_flag INTEGER NOT NULL DEFAULT 0, calendar_event_owner INTEGER NOT NULL,
    calendar_event_title TEXT NOT NULL, calendar_event_start_date TEXT NOT NULL,
    calendar_event_end_date TEXT NOT NULL, calendar_event_note TEXT NOT NULL,
    calendar_event_color TEXT NOT NULL DEFAULT "blue", calendar_event_all_day INTEGER NOT NULL DEFAULT 1,
    calendar_event_start_time TEXT NULL, calendar_event_end_time TEXT NULL, calendar_event_url TEXT NULL,
    calendar_event_repeat_type TEXT NOT NULL DEFAULT "none", calendar_event_repeat_until TEXT NULL
)');
$pdo->exec('CREATE TABLE calendar_event_exception (
    calendar_event_exception_id INTEGER PRIMARY KEY AUTOINCREMENT,
    calendar_event_exception_owner INTEGER NOT NULL,
    calendar_event_exception_event_id INTEGER NOT NULL,
    calendar_event_exception_original_start_date TEXT NOT NULL,
    calendar_event_exception_kind TEXT NOT NULL,
    calendar_event_exception_revision INTEGER NOT NULL DEFAULT 1,
    calendar_event_exception_flag INTEGER NOT NULL DEFAULT 0,
    calendar_event_exception_start_date TEXT NULL,
    calendar_event_exception_end_date TEXT NULL,
    calendar_event_exception_title TEXT NULL,
    calendar_event_exception_note TEXT NULL,
    calendar_event_exception_color TEXT NULL,
    calendar_event_exception_all_day INTEGER NULL,
    calendar_event_exception_start_time TEXT NULL,
    calendar_event_exception_end_time TEXT NULL,
    calendar_event_exception_url TEXT NULL,
    calendar_event_exception_created_at TEXT NOT NULL,
    calendar_event_exception_updated_at TEXT NOT NULL,
    UNIQUE (calendar_event_exception_owner, calendar_event_exception_event_id, calendar_event_exception_original_start_date)
)');
$pdo->exec('CREATE TABLE task (
    task_id INTEGER PRIMARY KEY AUTOINCREMENT, task_date TEXT NOT NULL, task_updated_at TEXT NOT NULL,
    task_flag INTEGER NOT NULL DEFAULT 0, task_owner INTEGER NOT NULL, task_widget_id INTEGER NOT NULL,
    task_title TEXT NOT NULL, task_due_date TEXT NULL, task_priority TEXT NOT NULL DEFAULT "normal",
    task_completed INTEGER NOT NULL DEFAULT 0, task_completed_at TEXT NULL, task_sort_order INTEGER NOT NULL DEFAULT 0
)');

$widgetStmt = $pdo->prepare('INSERT INTO dashboard_widget VALUES (?, ?, ?, ?, ?)');
$widgetStmt->execute([10, 1, 'calendar', 0, json_encode(['schema' => 1, 'title' => 'Calendar', 'show_completed_tasks' => false])]);
$widgetStmt->execute([11, 1, 'calendar', 0, json_encode(['schema' => 1, 'title' => 'All', 'show_completed_tasks' => true])]);
$widgetStmt->execute([20, 2, 'calendar', 0, json_encode(['schema' => 1, 'title' => 'Other', 'show_completed_tasks' => false])]);
$widgetStmt->execute([30, 1, 'task', 0, '{}']);
$widgetStmt->execute([31, 1, 'task', 1, '{}']);
$widgetStmt->execute([40, 2, 'task', 0, '{}']);

$eventStmt = $pdo->prepare('INSERT INTO calendar_event (
    calendar_event_id, calendar_event_date, calendar_event_updated_at, calendar_event_flag,
    calendar_event_owner, calendar_event_title, calendar_event_start_date, calendar_event_end_date,
    calendar_event_note, calendar_event_color, calendar_event_all_day, calendar_event_start_time,
    calendar_event_end_time, calendar_event_url, calendar_event_repeat_type, calendar_event_repeat_until
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

$events = [
    [1, '2026-09-01 00:00:00', '2026-09-01 00:00:00', 0, 1, '単日', '2026-09-10', '2026-09-10', '', 'blue', 1, null, null, null, 'none', null],
    [2, '2026-08-31 00:00:00', '2026-09-01 00:00:00', 0, 1, '月跨ぎ', '2026-08-31', '2026-09-02', 'note', 'purple', 0, '09:30:00', '18:00:00', 'https://example.com/a', 'none', null],
    [3, '2026-08-31 00:00:00', '2026-09-01 00:00:00', 0, 1, '毎週', '2026-08-31', '2026-09-01', '', 'yellow', 1, null, null, null, 'weekly', '2026-09-21'],
    [4, '2026-01-31 00:00:00', '2026-01-31 00:00:00', 0, 1, '月末', '2026-01-31', '2026-01-31', '', 'red', 1, null, null, null, 'monthly', '2026-12-31'],
    [5, '2026-09-01 00:00:00', '2026-09-01 00:00:00', 0, 2, '他人', '2026-09-12', '2026-09-12', '', 'green', 1, null, null, null, 'none', null],
    [6, '2026-09-01 00:00:00', '2026-09-01 00:00:00', 1, 1, '削除済み', '2026-09-13', '2026-09-13', '', 'green', 1, null, null, null, 'none', null],
    [7, '2026-09-01 00:00:00', '2026-09-01 00:00:00', 0, 1, '旧不明色', '2026-09-14', '2026-09-14', '', 'orange', 1, null, null, null, 'none', null],
    [8, '2026-09-01 00:00:00', '2026-09-01 00:00:00', 0, 1, '<b>data</b>', '2026-09-30', '2026-09-30', '<script>x</script>', 'green', 0, '23:00:00', null, null, 'daily', '2026-10-02'],
    [9, '2026-01-01 00:00:00', '2026-01-01 00:00:00', 0, 1, '壊れた旧繰り返し', '2026-01-01', '2026-01-01', '', 'blue', 1, null, null, null, 'hourly', null],
];
foreach ($events as $event) {
    $eventStmt->execute($event);
}

$taskStmt = $pdo->prepare('INSERT INTO task (
    task_date, task_updated_at, task_flag, task_owner, task_widget_id, task_title,
    task_due_date, task_priority, task_completed, task_completed_at, task_sort_order
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$taskStmt->execute(['2026-09-01', '2026-09-01', 0, 1, 30, '未完了', '2026-09-15', 'high', 0, null, 0]);
$taskStmt->execute(['2026-09-01', '2026-09-01', 0, 1, 30, '完了', '2026-09-16', 'normal', 1, '2026-09-16', 1]);
$taskStmt->execute(['2026-09-01', '2026-09-01', 0, 1, 31, '削除Widget', '2026-09-17', 'low', 0, null, 0]);
$taskStmt->execute(['2026-09-01', '2026-09-01', 0, 2, 40, '他人Task', '2026-09-18', 'high', 0, null, 0]);

c_assert(calendar_range_validate('2026-09-01', '2026-09-01') === ['start' => '2026-09-01', 'end' => '2026-09-01', 'days' => 1], 'one-day range is valid');
c_assert(calendar_range_validate('2026-09-01', '2026-10-12')['days'] === 42, '42-day inclusive range is valid');
c_assert(calendar_range_validate('2026-09-01', '2026-10-13') === null, '43-day range is rejected');
c_assert(calendar_range_validate('2026-02-30', '2026-03-01') === null, 'invalid date is rejected');
c_assert(calendar_range_validate('2026-09-02', '2026-09-01') === null, 'reverse range is rejected');
c_assert(calendar_event_occurrence_key(3, '2026-09-07') === 'event:3:2026-09-07', 'stable occurrence key uses parent id and original start');
$identityRejected = false;
try {
    calendar_event_occurrence_key(0, '2026-09-07');
} catch (InvalidArgumentException) {
    $identityRejected = true;
}
c_assert($identityRejected, 'invalid occurrence identity is rejected');

$rangeEvents = calendar_range_event_list(1, '2026-09-01', '2026-09-30');
$keys = array_column($rangeEvents, 'occurrence_key');
c_assert(count($keys) === count(array_unique($keys)), 'range response deduplicates occurrence identities');
c_assert(!in_array('event:5:2026-09-12', $keys, true), 'another owner is excluded');
c_assert(!in_array('event:6:2026-09-13', $keys, true), 'logically deleted event is excluded');
c_assert(!in_array('event:9:2026-01-01', $keys, true), 'invalid historical repeat value cannot bypass range overlap filtering');
c_assert(in_array('event:2:2026-08-31', $keys, true), 'event starting before range is included when it overlaps');
c_assert(in_array('event:8:2026-09-30', $keys, true), 'recurrence at month end is included');

$weekly = array_values(array_filter($rangeEvents, static fn(array $item): bool => (int) $item['event_id'] === 3));
c_assert(array_column($weekly, 'original_occurrence_start_date') === ['2026-08-31', '2026-09-07', '2026-09-14', '2026-09-21'], 'weekly multi-day occurrences expand across the month boundary');
c_assert(count(array_unique(array_column($weekly, 'occurrence_key'))) === 4, 'each weekly occurrence has an independent stable key');
c_assert(count(array_unique(array_column($weekly, 'source_start_date'))) === 1 && $weekly[0]['source_start_date'] === '2026-08-31', 'source dates remain separate from occurrence dates');
c_assert(count(array_filter($rangeEvents, static fn(array $item): bool => (int) $item['event_id'] === 4)) === 0, 'monthly 31st skips September');

$purple = array_values(array_filter($rangeEvents, static fn(array $item): bool => (int) $item['event_id'] === 2))[0] ?? [];
c_assert(($purple['color'] ?? '') === 'purple', 'new purple color survives unified range normalization');
c_assert(($purple['all_day'] ?? true) === false && ($purple['start_time'] ?? '') === '09:30', 'timed event metadata survives unified range normalization');
c_assert(($purple['url'] ?? '') === 'https://example.com/a', 'validated URL survives unified range normalization');
$fallback = array_values(array_filter($rangeEvents, static fn(array $item): bool => (int) $item['event_id'] === 7))[0] ?? [];
c_assert(($fallback['color'] ?? '') === 'blue', 'unknown historical color safely falls back to blue');
$htmlData = array_values(array_filter($rangeEvents, static fn(array $item): bool => (int) $item['event_id'] === 8))[0] ?? [];
c_assert(($htmlData['title'] ?? '') === '<b>data</b>' && ($htmlData['note'] ?? '') === '<script>x</script>', 'HTML-like text remains data for safe client text rendering');

$recurrenceLegacy = calendar_event_recurrence_month_list(1, 2026, 9);
$legacyKeys = array_column($recurrenceLegacy, 'occurrence_key');
$newRecurringKeys = array_values(array_map(
    static fn(array $item): string => $item['occurrence_key'],
    array_filter($rangeEvents, static fn(array $item): bool => ($item['repeat_type'] ?? 'none') !== 'none')
));
sort($legacyKeys);
sort($newRecurringKeys);
c_assert($legacyKeys === $newRecurringKeys, 'legacy recurrence list and unified range return the same September occurrences');

$legacyMonth = calendar_month_data(1, 2026, 9, false);
$legacyNormal = array_values(array_filter($legacyMonth['events'], static fn(array $item): bool => in_array((int) $item['event_id'], [1, 2, 7], true)));
$newNormal = array_values(array_filter($rangeEvents, static fn(array $item): bool => ($item['repeat_type'] ?? '') === 'none'));
c_assert(array_column($legacyNormal, 'event_id') === array_column($newNormal, 'event_id'), 'legacy and unified range keep non-recurring event ids and order');
c_assert(array_column($legacyNormal, 'start_date') === array_column($newNormal, 'occurrence_start_date'), 'legacy and unified range keep non-recurring start dates');

$data = calendar_range_data(1, 10, '2026-09-01', '2026-09-30');
c_assert($data['range_days'] === 30 && $data['range_start'] === '2026-09-01' && $data['range_end'] === '2026-09-30', 'range response reports its exact bounds');
c_assert(array_column($data['tasks'], 'title') === ['未完了'], 'owned active Task is included and completed Task follows Widget setting');
c_assert($data['holidays'] === ['2026-09-21' => '敬老の日', '2026-09-22' => '国民の休日'], 'holidays are restricted to the requested range');
c_assert($data['holiday_source'] === 'test-fixture' && $data['holiday_refresh_due'] === false, 'holiday state metadata remains compatible');
$allTasks = calendar_range_data(1, 11, '2026-09-01', '2026-09-30')['tasks'];
c_assert(array_column($allTasks, 'title') === ['未完了', '完了'], 'show-completed Widget setting is applied by the owned Widget');
$foreignWidgetRejected = false;
try {
    calendar_range_data(1, 20, '2026-09-01', '2026-09-30');
} catch (OutOfBoundsException) {
    $foreignWidgetRejected = true;
}
c_assert($foreignWidgetRejected, 'another owner Calendar Widget is hidden as not found');

$upcoming = calendar_event_upcoming_list(1, '2026-09-20');
$directUpcoming = calendar_range_event_list(1, '2026-09-20', '2026-10-03');
$expectedUpcoming = array_slice($directUpcoming, 0, CALENDAR_UPCOMING_LIMIT);
c_assert(array_column($upcoming, 'occurrence_key') === array_column($expectedUpcoming, 'occurrence_key'), 'upcoming list reuses the unified range occurrence set without duplicates');
c_assert(in_array('event:8:2026-10-01', array_column($upcoming, 'occurrence_key'), true), 'upcoming range crosses into the next month');

$pdo->beginTransaction();
for ($id = 1000; $id < 1501; $id++) {
    $eventStmt->execute([$id, '2026-09-01', '2026-09-01', 0, 99, 'bulk', '2026-09-01', '2026-09-01', '', 'blue', 1, null, null, null, 'none', null]);
}
$pdo->commit();
$sourceLimitRejected = false;
try {
    calendar_range_event_list(99, '2026-09-01', '2026-09-30');
} catch (LengthException) {
    $sourceLimitRejected = true;
}
c_assert($sourceLimitRejected, 'source event limit fails explicitly instead of truncating');

$pdo->beginTransaction();
for ($id = 2000; $id < 2049; $id++) {
    $eventStmt->execute([$id, '2026-09-01', '2026-09-01', 0, 98, 'daily', '2026-09-01', '2026-09-01', '', 'blue', 1, null, null, null, 'daily', null]);
}
$pdo->commit();
$occurrenceLimitRejected = false;
try {
    calendar_range_event_list(98, '2026-09-01', '2026-10-12');
} catch (LengthException) {
    $occurrenceLimitRejected = true;
}
c_assert($occurrenceLimitRejected, 'occurrence expansion limit fails explicitly');

echo "RESULT: PASS {$pass} / FAIL {$fail} / SKIP 0\n";
exit($fail === 0 ? 0 : 1);
