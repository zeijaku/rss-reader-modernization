<?php

declare(strict_types=1);

function calendar_validate_event_range(mixed $start, mixed $end): ?array
{
    if (!is_string($start) || !is_string($end)
        || preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $start) !== 1
        || preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $end) !== 1
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

function app_validate_text(mixed $value, int $maxLength, bool $allowEmpty): ?string
{
    if (!is_string($value) || (!$allowEmpty && $value === '')) {
        return null;
    }
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    return $length <= $maxLength ? $value : null;
}

function db_table_identifier(string $name): string
{
    return '`' . $name . '`';
}

require __DIR__ . '/../app/calendar_time.php';

function v144b_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$settings = calendar_event_time_settings(
    '1', '', '', 'https://example.com/', '2026-10-04', '2026-10-04', null, '  広島駅 南口 & 1F  '
);
v144b_assert(is_array($settings) && $settings['location'] === '広島駅 南口 & 1F',
    'Japanese location, spaces and symbols are preserved after outer trim');
v144b_assert(
    calendar_event_time_settings('1', '', '', '', '2026-10-04', '2026-10-04', null, '   ')['location'] === null,
    'blank location normalizes to NULL'
);
v144b_assert(
    calendar_event_time_settings('1', '', '', '', '2026-10-04', '2026-10-04', null, "line1\nline2") === null,
    'multi-line location is rejected'
);
v144b_assert(
    calendar_event_time_settings('1', '', '', '', '2026-10-04', '2026-10-04', null, str_repeat('a', 256)) === null,
    'location longer than 255 characters is rejected'
);

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "PASS: V1.44-B Calendar location validation\n";
    echo "RESULT: PASS 4 / FAIL 0 / SKIP 1 (pdo_sqlite unavailable for owner-scope persistence fixture)\n";
    exit(0);
}

$GLOBALS['v144b_db'] = new PDO('sqlite::memory:');
$GLOBALS['v144b_db']->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
function conn_db(): PDO
{
    return $GLOBALS['v144b_db'];
}

$db = conn_db();
$db->exec('CREATE TABLE calendar_event (
    calendar_event_id INTEGER PRIMARY KEY,
    calendar_event_owner INTEGER NOT NULL,
    calendar_event_flag INTEGER NOT NULL DEFAULT 0,
    calendar_event_start_date TEXT NOT NULL,
    calendar_event_end_date TEXT NOT NULL,
    calendar_event_all_day INTEGER NOT NULL DEFAULT 1,
    calendar_event_start_time TEXT NULL,
    calendar_event_end_time TEXT NULL,
    calendar_event_url TEXT NULL,
    calendar_event_location TEXT NULL,
    calendar_event_reminder TEXT NOT NULL DEFAULT "none",
    calendar_event_deadline_highlight INTEGER NOT NULL DEFAULT 0
)');
$db->exec("INSERT INTO calendar_event
    (calendar_event_id, calendar_event_owner, calendar_event_start_date, calendar_event_end_date)
    VALUES (1, 42, '2026-10-04', '2026-10-04'), (2, 99, '2026-10-04', '2026-10-04')");

calendar_event_time_apply($db, 42, 1, $settings);
calendar_event_time_apply($db, 42, 2, array_merge($settings, ['location' => '他人を変更しない']));
$rows = $db->query('SELECT calendar_event_id, calendar_event_location FROM calendar_event ORDER BY calendar_event_id')
    ->fetchAll(PDO::FETCH_ASSOC);
v144b_assert($rows[0]['calendar_event_location'] === '広島駅 南口 & 1F', 'owned event persists location');
v144b_assert($rows[1]['calendar_event_location'] === null, 'owner boundary prevents changing another owner event');

echo "PASS: V1.44-B Calendar location validation/persistence/owner scope\n";
