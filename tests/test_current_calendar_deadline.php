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

function app_validate_external_link(mixed $value, int $maxLength): ?string
{
    if (!is_string($value) || strlen($value) > $maxLength) {
        return null;
    }
    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $value : null;
}

function app_now(): string
{
    return '2026-09-08 12:00:00';
}

require __DIR__ . '/../app/calendar.php';
require __DIR__ . '/../app/calendar_color.php';
require __DIR__ . '/../app/calendar_time.php';
require __DIR__ . '/../app/calendar_recurrence.php';
require __DIR__ . '/../app/calendar_exception.php';
require __DIR__ . '/../app/calendar_range.php';
require __DIR__ . '/../app/calendar_upcoming.php';

$pass = 0;
$fail = 0;
function d_assert(bool $condition, string $message): void
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

/** @param class-string<Throwable> $class */
function d_throws(string $class, callable $callback, string $message): void
{
    try {
        $callback();
        d_assert(false, $message);
    } catch (Throwable $exception) {
        d_assert($exception instanceof $class, $message);
    }
}

$pdo->exec('CREATE TABLE calendar_event (
    calendar_event_id INTEGER PRIMARY KEY AUTOINCREMENT,
    calendar_event_date TEXT NOT NULL, calendar_event_updated_at TEXT NOT NULL,
    calendar_event_flag INTEGER NOT NULL DEFAULT 0, calendar_event_owner INTEGER NOT NULL,
    calendar_event_title TEXT NOT NULL, calendar_event_start_date TEXT NOT NULL,
    calendar_event_end_date TEXT NOT NULL, calendar_event_note TEXT NOT NULL,
    calendar_event_color TEXT NOT NULL DEFAULT "blue", calendar_event_all_day INTEGER NOT NULL DEFAULT 1,
    calendar_event_start_time TEXT NULL, calendar_event_end_time TEXT NULL, calendar_event_url TEXT NULL, calendar_event_location TEXT NULL,
    calendar_event_repeat_type TEXT NOT NULL DEFAULT "none", calendar_event_repeat_until TEXT NULL,
    calendar_event_reminder TEXT NOT NULL DEFAULT "none",
    calendar_event_deadline_highlight INTEGER NOT NULL DEFAULT 0
)');
$pdo->exec('CREATE TABLE calendar_event_exception (
    calendar_event_exception_id INTEGER PRIMARY KEY AUTOINCREMENT,
    calendar_event_exception_owner INTEGER NOT NULL,
    calendar_event_exception_event_id INTEGER NOT NULL,
    calendar_event_exception_original_start_date TEXT NOT NULL,
    calendar_event_exception_kind TEXT NOT NULL,
    calendar_event_exception_revision INTEGER NOT NULL DEFAULT 1,
    calendar_event_exception_flag INTEGER NOT NULL DEFAULT 0,
    calendar_event_exception_start_date TEXT NULL, calendar_event_exception_end_date TEXT NULL,
    calendar_event_exception_title TEXT NULL, calendar_event_exception_note TEXT NULL,
    calendar_event_exception_color TEXT NULL, calendar_event_exception_all_day INTEGER NULL,
    calendar_event_exception_start_time TEXT NULL, calendar_event_exception_end_time TEXT NULL,
    calendar_event_exception_url TEXT NULL, calendar_event_exception_location TEXT NULL, calendar_event_exception_created_at TEXT NOT NULL,
    calendar_event_exception_deadline_highlight INTEGER DEFAULT NULL,
    calendar_event_exception_updated_at TEXT NOT NULL,
    UNIQUE (calendar_event_exception_owner, calendar_event_exception_event_id, calendar_event_exception_original_start_date)
)');

$settings = calendar_event_time_settings('1', '', '', '', '2026-10-10', '2026-10-10', '1');
d_assert(is_array($settings) && $settings['deadline_highlight'] === true, 'strict opt-in flag accepts one');
d_assert(calendar_event_time_settings('1', '', '', '', '2026-10-10', '2026-10-10', 'yes') === null, 'invalid flag rejected');
$id = calendar_event_recurrence_time_color_create(1, '振り込み', '2026-10-10', '2026-10-10', '', 'blue', $settings, ['repeat_type'=>'monthly','repeat_until'=>'2026-12-31']);
$range = calendar_range_event_list(1, '2026-10-01', '2026-10-31');
$item = $range[0];
d_assert($item['deadline_highlight'] === true && $item['source_deadline_highlight'] === true, 'recurrence carries effective and series flags');
d_assert(calendar_event_time_color_update(2, $id, '他人', '2026-10-10', '2026-10-10', '', 'red', $settings) === false, 'other owner cannot change deadline');
$off = calendar_event_time_settings('1', '', '', '', '2026-10-10', '2026-10-10', '0');
$override = calendar_event_occurrence_update(1, $id, '2026-10-10', '今月だけ解除', '2026-10-10', '2026-10-10', '', 'blue', $off, $item['occurrence_revision']);
d_assert($override['deadline_highlight'] === false && $override['source_deadline_highlight'] === true, 'occurrence flag can differ from series');
$legacy = calendar_event_time_settings('1', '', '', '', '2026-10-10', '2026-10-10');
$updated = calendar_event_occurrence_update(1, $id, '2026-10-10', '旧クライアントで編集', '2026-10-10', '2026-10-10', '', 'blue', $legacy, $override['occurrence_revision']);
d_assert($updated['deadline_highlight'] === false, 'old client preserves occurrence flag');
$restored = calendar_event_occurrence_restore(1, $id, '2026-10-10', $updated['occurrence_revision']);
d_assert($restored['deadline_highlight'] === true, 'restoring occurrence returns series flag');
$legacyId = calendar_event_time_color_create(1, '散歩', '2026-10-10', '2026-10-10', '', 'blue', $legacy);
d_assert((int)$pdo->query('SELECT calendar_event_deadline_highlight FROM calendar_event WHERE calendar_event_id = '.$legacyId)->fetchColumn() === 0, 'new ordinary event defaults to opt-out');
echo "RESULT: PASS {$pass} / FAIL {$fail} / SKIP 0\n";
exit($fail ? 1 : 0);
