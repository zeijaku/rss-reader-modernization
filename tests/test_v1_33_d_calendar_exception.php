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

/** @param list<array<string,mixed>> $events */
function d_event(array $events, int $eventId, string $originalStart): array
{
    foreach ($events as $event) {
        if ((int) ($event['event_id'] ?? 0) === $eventId
            && ($event['original_occurrence_start_date'] ?? null) === $originalStart) {
            return $event;
        }
    }
    return [];
}

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
    calendar_event_exception_start_date TEXT NULL, calendar_event_exception_end_date TEXT NULL,
    calendar_event_exception_title TEXT NULL, calendar_event_exception_note TEXT NULL,
    calendar_event_exception_color TEXT NULL, calendar_event_exception_all_day INTEGER NULL,
    calendar_event_exception_start_time TEXT NULL, calendar_event_exception_end_time TEXT NULL,
    calendar_event_exception_url TEXT NULL, calendar_event_exception_created_at TEXT NOT NULL,
    calendar_event_exception_updated_at TEXT NOT NULL,
    UNIQUE (calendar_event_exception_owner, calendar_event_exception_event_id, calendar_event_exception_original_start_date)
)');

$insertEvent = $pdo->prepare('INSERT INTO calendar_event VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$rows = [
    [1, '2026-08-31', '2026-08-31 00:00:00', 0, 1, '毎週会議', '2026-08-31', '2026-09-01', 'series', 'blue', 1, null, null, null, 'weekly', '2026-12-31'],
    [2, '2026-01-31', '2026-01-31 00:00:00', 0, 1, '月末', '2026-01-31', '2026-01-31', '', 'red', 1, null, null, null, 'monthly', '2026-12-31'],
    [3, '2026-08-31', '2026-08-31 00:00:00', 0, 2, '他人', '2026-08-31', '2026-08-31', '', 'green', 1, null, null, null, 'weekly', '2026-12-31'],
    [4, '2026-09-01', '2026-09-01 00:00:00', 0, 4, '上限', '2026-09-01', '2026-09-01', '', 'yellow', 1, null, null, null, 'daily', '2026-12-31'],
];
foreach ($rows as $row) {
    $insertEvent->execute($row);
}

$september = calendar_range_event_state(1, '2026-09-01', '2026-09-30');
$source = d_event($september['events'], 1, '2026-09-07');
d_assert(preg_match('/^[a-f0-9]{64}$/', (string) ($source['occurrence_revision'] ?? '')) === 1, 'range supplies an opaque occurrence revision');
d_assert(($source['is_exception'] ?? null) === false
    && array_key_exists('exception_id', $source) && $source['exception_id'] === null,
    'unmodified occurrence is explicitly marked as base data');

$revision0 = (string) $source['occurrence_revision'];
$override = calendar_event_occurrence_update(
    1, 1, '2026-09-07', '<b>個別会議</b>', '2026-09-08', '2026-09-10',
    '<script>memo</script>', 'purple',
    ['all_day' => false, 'start_time' => '09:15', 'end_time' => '10:45', 'url' => 'https://example.com/meeting'],
    $revision0
);
d_assert(($override['occurrence_key'] ?? '') === 'event:1:2026-09-07', 'moved override preserves stable occurrence identity');
d_assert(($override['occurrence_start_date'] ?? '') === '2026-09-08' && ($override['occurrence_end_date'] ?? '') === '2026-09-10', 'override stores its effective multi-day range');
d_assert(($override['color'] ?? '') === 'purple' && ($override['all_day'] ?? true) === false, 'override preserves five-color and time metadata');
d_assert(($override['title'] ?? '') === '<b>個別会議</b>' && ($override['note'] ?? '') === '<script>memo</script>', 'HTML-like values remain plain response data');
d_assert(($override['occurrence_revision'] ?? '') !== $revision0, 'override changes the optimistic concurrency token');

$afterOverride = calendar_range_event_state(1, '2026-09-01', '2026-09-30');
$effective = d_event($afterOverride['events'], 1, '2026-09-07');
d_assert(($effective['is_exception'] ?? false) === true && ($effective['exception_kind'] ?? '') === 'override', 'range replaces base occurrence with override');
d_assert(count(array_filter($afterOverride['events'], static fn(array $e): bool => ($e['occurrence_key'] ?? '') === 'event:1:2026-09-07')) === 1, 'range never duplicates an overridden occurrence');
d_assert((int) $pdo->query('SELECT COUNT(*) FROM calendar_event_exception')->fetchColumn() === 1, 'one exception row represents one occurrence');

d_throws(CalendarOccurrenceConflictException::class, static function () use ($revision0): void {
    calendar_event_occurrence_cancel(1, 1, '2026-09-07', $revision0);
}, 'stale occurrence revision is rejected with a conflict');
d_assert((string) $pdo->query('SELECT calendar_event_exception_kind FROM calendar_event_exception')->fetchColumn() === 'override', 'stale mutation leaves stored exception unchanged');

d_throws(OutOfBoundsException::class, static function () use ($revision0): void {
    calendar_event_occurrence_update(1, 3, '2026-09-07', 'x', '2026-09-07', '2026-09-07', '', 'blue', ['all_day' => true, 'start_time' => null, 'end_time' => null, 'url' => null], $revision0);
}, 'another owner occurrence is hidden as not found');
d_throws(OutOfBoundsException::class, static function () use ($revision0): void {
    calendar_event_occurrence_cancel(1, 1, '2026-09-09', $revision0);
}, 'a date that is not a real series occurrence is rejected');
d_throws(OutOfBoundsException::class, static function () use ($revision0): void {
    calendar_event_occurrence_cancel(1, 2, '2026-09-30', $revision0);
}, 'monthly day absent from the month is not accepted as an occurrence');

$cancelSource = d_event($afterOverride['events'], 1, '2026-09-14');
$cancelled = calendar_event_occurrence_cancel(1, 1, '2026-09-14', (string) $cancelSource['occurrence_revision']);
d_assert(($cancelled['is_cancelled'] ?? false) === true && ($cancelled['exception_kind'] ?? '') === 'cancelled', 'individual occurrence can be cancelled');
$cancelledAgain = calendar_event_occurrence_cancel(1, 1, '2026-09-14', (string) $cancelled['occurrence_revision']);
d_assert(($cancelledAgain['occurrence_revision'] ?? '') === ($cancelled['occurrence_revision'] ?? ''), 'repeat cancel with current token is idempotent');
$afterCancel = calendar_range_event_state(1, '2026-09-01', '2026-09-30');
d_assert(d_event($afterCancel['events'], 1, '2026-09-14') === [], 'cancelled occurrence is omitted from visible events');
d_assert(($afterCancel['cancelled_occurrences'][0]['occurrence_key'] ?? '') === 'event:1:2026-09-14', 'cancelled occurrence is returned separately for future restore UI');

$restored = calendar_event_occurrence_restore(1, 1, '2026-09-14', (string) $cancelled['occurrence_revision']);
d_assert(($restored['is_exception'] ?? true) === false && ($restored['occurrence_revision'] ?? '') !== ($cancelled['occurrence_revision'] ?? ''), 'restore returns base data with a new token');
$afterRestore = calendar_range_event_state(1, '2026-09-01', '2026-09-30');
d_assert(d_event($afterRestore['events'], 1, '2026-09-14') !== [] && $afterRestore['cancelled_occurrences'] === [], 'restored occurrence is visible again');
d_throws(OutOfBoundsException::class, static function () use ($restored): void {
    calendar_event_occurrence_restore(1, 1, '2026-09-14', (string) $restored['occurrence_revision']);
}, 'already-restored exception cannot be restored again');

$moveOutSource = d_event($afterRestore['events'], 1, '2026-09-21');
$movedOut = calendar_event_occurrence_update(1, 1, '2026-09-21', '10月へ移動', '2026-10-05', '2026-10-05', '', 'yellow', ['all_day' => true, 'start_time' => null, 'end_time' => null, 'url' => null], (string) $moveOutSource['occurrence_revision']);
$septemberMoved = calendar_range_event_list(1, '2026-09-01', '2026-09-30');
$octoberMoved = calendar_range_event_list(1, '2026-10-01', '2026-10-31');
d_assert(d_event($septemberMoved, 1, '2026-09-21') === [], 'override moved out of a range is removed from that range');
d_assert(($found = d_event($octoberMoved, 1, '2026-09-21')) !== [] && ($found['occurrence_start_date'] ?? '') === '2026-10-05', 'moved-out override appears in its effective month');
d_assert(($found['occurrence_revision'] ?? '') === ($movedOut['occurrence_revision'] ?? ''), 'moved override keeps the same revision across ranges');

$moveInSource = d_event($octoberMoved, 1, '2026-10-12');
calendar_event_occurrence_update(1, 1, '2026-10-12', '9月へ移動', '2026-09-29', '2026-09-29', '', 'green', ['all_day' => true, 'start_time' => null, 'end_time' => null, 'url' => null], (string) $moveInSource['occurrence_revision']);
$septemberMovedIn = calendar_range_event_list(1, '2026-09-01', '2026-09-30');
d_assert(($movedIn = d_event($septemberMovedIn, 1, '2026-10-12')) !== [] && ($movedIn['occurrence_start_date'] ?? '') === '2026-09-29', 'override moved from a later month is included by effective range');

$overrideRevision = (string) d_event($septemberMovedIn, 1, '2026-09-07')['occurrence_revision'];
d_throws(CalendarOccurrenceConflictException::class, static function (): void {
    calendar_event_recurrence_time_color_update(1, 1, 'shifted', '2026-09-01', '2026-09-02', '', 'blue', ['all_day' => true, 'start_time' => null, 'end_time' => null, 'url' => null], ['repeat_type' => 'weekly', 'repeat_until' => '2026-12-31']);
}, 'series shift that invalidates active exceptions is rejected');
$parent = $pdo->query('SELECT * FROM calendar_event WHERE calendar_event_id = 1')->fetch();
d_assert(($parent['calendar_event_start_date'] ?? '') === '2026-08-31' && ($parent['calendar_event_title'] ?? '') === '毎週会議', 'rejected series mutation rolls back every parent field');
d_assert(calendar_update_event(1, 1, 'シリーズ名変更', '2026-08-31', '2026-09-01', 'series') === true, 'series text update is allowed when occurrence identities remain valid');
$afterParentEdit = d_event(calendar_range_event_list(1, '2026-09-01', '2026-09-30'), 1, '2026-09-07');
d_assert(($afterParentEdit['title'] ?? '') === '<b>個別会議</b>' && ($afterParentEdit['occurrence_revision'] ?? '') !== $overrideRevision, 'override survives compatible series edit and receives a new state token');

d_throws(InvalidArgumentException::class, static function () use ($afterParentEdit): void {
    calendar_event_occurrence_update(1, 1, '2026-09-07', 'bad', '2026-09-07', '2026-09-07', '', 'orange', ['all_day' => true, 'start_time' => null, 'end_time' => null, 'url' => null], (string) $afterParentEdit['occurrence_revision']);
}, 'unknown color is rejected before mutation');
d_throws(InvalidArgumentException::class, static function () use ($afterParentEdit): void {
    calendar_event_occurrence_update(1, 1, '2026-09-07', 'bad', '2026-09-07', '2026-09-07', '', 'blue', ['all_day' => false, 'start_time' => '18:00', 'end_time' => '09:00', 'url' => 'javascript:alert(1)'], (string) $afterParentEdit['occurrence_revision']);
}, 'invalid time and unsafe URL are rejected before mutation');

$bulkInsert = $pdo->prepare('INSERT INTO calendar_event_exception (
    calendar_event_exception_owner, calendar_event_exception_event_id, calendar_event_exception_original_start_date,
    calendar_event_exception_kind, calendar_event_exception_revision, calendar_event_exception_flag,
    calendar_event_exception_created_at, calendar_event_exception_updated_at
) VALUES (4, ?, ?, "cancelled", 1, 0, "2026-09-08", "2026-09-08")');
for ($index = 0; $index < CALENDAR_EXCEPTION_MAX_ACTIVE; $index++) {
    $bulkInsert->execute([10000 + $index, (new DateTimeImmutable('2025-01-01'))->modify('+' . $index . ' days')->format('Y-m-d')]);
}
$owner4Source = d_event(calendar_range_event_list(4, '2026-09-01', '2026-09-30'), 4, '2026-09-08');
d_throws(LengthException::class, static function () use ($owner4Source): void {
    calendar_event_occurrence_cancel(4, 4, '2026-09-08', (string) $owner4Source['occurrence_revision']);
}, 'active occurrence exception cap fails explicitly without truncation');
d_assert((int) $pdo->query('SELECT COUNT(*) FROM calendar_event_exception WHERE calendar_event_exception_owner = 4')->fetchColumn() === CALENDAR_EXCEPTION_MAX_ACTIVE, 'limit rejection does not insert a partial exception');

$pdo->exec('UPDATE calendar_event SET calendar_event_flag = 1 WHERE calendar_event_id = 1');
d_assert(d_event(calendar_range_event_list(1, '2026-09-01', '2026-09-30'), 1, '2026-10-12') === [], 'logical parent deletion hides moved-in overrides');

d_throws(PDOException::class, static function () use ($pdo): void {
    $pdo->exec('DROP TABLE calendar_event_exception');
    calendar_range_event_list(1, '2026-09-01', '2026-09-30');
}, 'missing exception migration fails closed instead of silently showing wrong data');

echo "RESULT: PASS {$pass} / FAIL {$fail} / SKIP 0\n";
exit($fail === 0 ? 0 : 1);
