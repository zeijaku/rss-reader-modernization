<?php

declare(strict_types=1);

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$tz = new DateTimeZone('Asia/Tokyo');
$today = new DateTimeImmutable('today', $tz);
$testNow = $today->setTime(12, 0, 0)->format('Y-m-d H:i:s');

function conn_db(): PDO
{
    global $pdo;
    return $pdo;
}

function db_table_identifier(string $name): string
{
    return '"' . str_replace('"', '""', $name) . '"';
}

function app_now(): string
{
    global $testNow;
    return $testNow;
}

require __DIR__ . '/../app/validation.php';
require __DIR__ . '/../app/calendar.php';
require __DIR__ . '/../app/calendar_color.php';
require __DIR__ . '/../app/calendar_time.php';
require __DIR__ . '/../app/calendar_recurrence.php';
require __DIR__ . '/../app/calendar_exception.php';
require __DIR__ . '/../app/notification.php';
require __DIR__ . '/../app/calendar_reminder.php';
require __DIR__ . '/../app/calendar_range.php';

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

/** @param list<array<string,mixed>> $events */
function c_occurrence(array $events, int $eventId, string $originalStart): array
{
    foreach ($events as $event) {
        if ((int) ($event['event_id'] ?? 0) === $eventId
            && (string) ($event['original_occurrence_start_date'] ?? '') === $originalStart) {
            return $event;
        }
    }
    return [];
}

function c_date(DateTimeImmutable $today, int $offset): string
{
    return $today->modify(($offset >= 0 ? '+' : '') . $offset . ' days')->format('Y-m-d');
}

$pdo->exec('CREATE TABLE dashboard_widget (
    widget_id INTEGER PRIMARY KEY AUTOINCREMENT,
    widget_owner INTEGER NOT NULL,
    widget_location INTEGER NOT NULL,
    widget_type TEXT NOT NULL,
    widget_sort_order INTEGER NOT NULL,
    widget_flag INTEGER NOT NULL
)');
$pdo->exec('CREATE TABLE calendar_event (
    calendar_event_id INTEGER PRIMARY KEY AUTOINCREMENT,
    calendar_event_date TEXT NOT NULL,
    calendar_event_updated_at TEXT NOT NULL,
    calendar_event_flag INTEGER NOT NULL DEFAULT 0,
    calendar_event_owner INTEGER NOT NULL,
    calendar_event_title TEXT NOT NULL,
    calendar_event_start_date TEXT NOT NULL,
    calendar_event_end_date TEXT NOT NULL,
    calendar_event_note TEXT NOT NULL,
    calendar_event_color TEXT NOT NULL DEFAULT "blue",
    calendar_event_all_day INTEGER NOT NULL DEFAULT 1,
    calendar_event_start_time TEXT NULL,
    calendar_event_end_time TEXT NULL,
    calendar_event_url TEXT NULL,
    calendar_event_repeat_type TEXT NOT NULL DEFAULT "none",
    calendar_event_repeat_until TEXT NULL,
    calendar_event_reminder TEXT NOT NULL DEFAULT "none"
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
$pdo->exec('CREATE TABLE notification (
    notification_id INTEGER PRIMARY KEY AUTOINCREMENT,
    notification_owner INTEGER NOT NULL,
    notification_type TEXT NOT NULL,
    notification_source_type TEXT NOT NULL,
    notification_source_id TEXT NULL,
    notification_source_key TEXT NOT NULL,
    notification_title TEXT NOT NULL,
    notification_body TEXT NOT NULL,
    notification_target_url TEXT NULL,
    notification_due_at TEXT NOT NULL,
    notification_read_at TEXT NULL,
    notification_hidden_at TEXT NULL,
    notification_created_at TEXT NOT NULL,
    notification_updated_at TEXT NOT NULL,
    UNIQUE(notification_owner, notification_source_type, notification_source_key, notification_type)
)');

$pdo->exec("INSERT INTO dashboard_widget (widget_owner, widget_location, widget_type, widget_sort_order, widget_flag)
    VALUES (1, 2, 'calendar', 0, 0), (2, 1, 'calendar', 0, 0)");

$start = c_date($today, -2);
$until = c_date($today, 20);
$insert = $pdo->prepare('INSERT INTO calendar_event (
    calendar_event_id, calendar_event_date, calendar_event_updated_at, calendar_event_flag, calendar_event_owner,
    calendar_event_title, calendar_event_start_date, calendar_event_end_date, calendar_event_note,
    calendar_event_color, calendar_event_all_day, calendar_event_start_time, calendar_event_end_time,
    calendar_event_url, calendar_event_repeat_type, calendar_event_repeat_until, calendar_event_reminder
) VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$insert->execute([
    1, $start, $testNow, 1, 'Daily standup', $start, $start, 'series', 'blue', 0,
    '15:00:00', '15:30:00', 'https://example.com/standup', 'daily', $until, '30m'
]);
$insert->execute([
    2, $start, $testNow, 2, 'Other owner', $start, $start, '', 'green', 0,
    '10:00:00', null, null, 'daily', $until, '1h'
]);

calendar_event_reminder_sync_owner(1);
$rows = $pdo->query("SELECT * FROM notification WHERE notification_owner = 1 ORDER BY notification_source_key")->fetchAll();
c_assert(count($rows) >= 10, 'rolling sync materializes multiple recurring occurrence reminders');
c_assert(count($rows) === count(array_unique(array_column($rows, 'notification_source_key'))), 'each occurrence uses a unique notification source key');
$olderKey = calendar_event_reminder_source_key(1, c_date($today, -2));
$olderStmt = $pdo->prepare('SELECT COUNT(*) FROM notification WHERE notification_owner = 1 AND notification_source_key = ?');
$olderStmt->execute([$olderKey]);
c_assert((int) $olderStmt->fetchColumn() === 0, 'rolling sync does not backfill recurring reminders older than yesterday');
c_assert((int) $pdo->query("SELECT COUNT(*) FROM notification WHERE notification_owner = 2")->fetchColumn() === 0, 'owner sync does not materialize another owner notifications');

$futureOriginal = c_date($today, 2);
$sourceKey = calendar_event_reminder_source_key(1, $futureOriginal);
$stmt = $pdo->prepare('SELECT * FROM notification WHERE notification_owner = 1 AND notification_source_key = ?');
$stmt->execute([$sourceKey]);
$beforeMove = $stmt->fetch();
c_assert(is_array($beforeMove), 'future recurring occurrence has a pending reminder');
c_assert(($beforeMove['notification_due_at'] ?? '') === $futureOriginal . ' 14:30:00', 'recurring occurrence uses the series 30-minute reminder');

$countBefore = (int) $pdo->query("SELECT COUNT(*) FROM notification WHERE notification_owner = 1")->fetchColumn();
$unchangedUpdatedAt = (string) ($beforeMove['notification_updated_at'] ?? '');
$testNow = $today->setTime(12, 1, 0)->format('Y-m-d H:i:s');
calendar_event_reminder_sync_owner(1);
$countAfter = (int) $pdo->query("SELECT COUNT(*) FROM notification WHERE notification_owner = 1")->fetchColumn();
c_assert($countBefore === $countAfter, 'repeated recurring sync does not duplicate reminders');
$stmt->execute([$sourceKey]);
$afterNoopSync = $stmt->fetch();
c_assert(is_array($afterNoopSync) && ($afterNoopSync['notification_updated_at'] ?? '') === $unchangedUpdatedAt, 'unchanged recurring reminder sync performs no database rewrite');

$state = calendar_range_event_state(1, c_date($today, -2), c_date($today, 8));
$source = c_occurrence($state['events'], 1, $futureOriginal);
c_assert($source !== [] && ($source['reminder'] ?? '') === '30m', 'recurrence projection exposes inherited reminder state');

$movedDate = c_date($today, 3);
$moved = calendar_event_occurrence_update(
    1,
    1,
    $futureOriginal,
    'Moved standup',
    $movedDate,
    $movedDate,
    'moved',
    'purple',
    ['all_day' => false, 'start_time' => '17:00', 'end_time' => '17:30', 'url' => 'https://example.com/moved'],
    (string) $source['occurrence_revision']
);
$stmt->execute([$sourceKey]);
$afterMove = $stmt->fetch();
c_assert(is_array($afterMove) && ($afterMove['notification_due_at'] ?? '') === $movedDate . ' 16:30:00', 'individual occurrence move reschedules the pending reminder');
c_assert(($afterMove['notification_target_url'] ?? '') === './?tab=2&calendar_date=' . $movedDate . '&calendar_event_id=1&calendar_occurrence_start=' . $futureOriginal, 'moved occurrence notification opens the exact effective occurrence');
c_assert(($moved['original_occurrence_start_date'] ?? '') === $futureOriginal, 'moved occurrence keeps stable original identity');

$cancelOriginal = c_date($today, 4);
$cancelState = calendar_range_event_state(1, c_date($today, -2), c_date($today, 8));
$cancelSource = c_occurrence($cancelState['events'], 1, $cancelOriginal);
$cancelKey = calendar_event_reminder_source_key(1, $cancelOriginal);
calendar_event_occurrence_cancel(1, 1, $cancelOriginal, (string) $cancelSource['occurrence_revision']);
$stmt->execute([$cancelKey]);
c_assert($stmt->fetch() === false, 'cancelling one occurrence removes only its pending reminder');
$stmt->execute([$sourceKey]);
c_assert($stmt->fetch() !== false, 'cancelling another occurrence does not remove the moved occurrence reminder');

$cancelledState = calendar_range_event_state(1, c_date($today, -2), c_date($today, 8));
$cancelled = [];
foreach ($cancelledState['cancelled_occurrences'] as $item) {
    if (($item['original_occurrence_start_date'] ?? '') === $cancelOriginal) {
        $cancelled = $item;
        break;
    }
}
c_assert($cancelled !== [], 'cancelled occurrence remains available for restore');
calendar_event_occurrence_restore(1, 1, $cancelOriginal, (string) $cancelled['occurrence_revision']);
$stmt->execute([$cancelKey]);
$restoredNotification = $stmt->fetch();
c_assert(is_array($restoredNotification) && ($restoredNotification['notification_due_at'] ?? '') === $cancelOriginal . ' 14:30:00', 'restoring an occurrence recreates its pending reminder');

$pastOriginal = c_date($today, -1);
$pastKey = calendar_event_reminder_source_key(1, $pastOriginal);
$stmt->execute([$pastKey]);
$pastReminder = $stmt->fetch();
c_assert(is_array($pastReminder) && (string) $pastReminder['notification_due_at'] < $testNow, 'past occurrence reminder exists as due history');
$pastState = calendar_range_event_state(1, c_date($today, -2), c_date($today, 1));
$pastSource = c_occurrence($pastState['events'], 1, $pastOriginal);
calendar_event_occurrence_cancel(1, 1, $pastOriginal, (string) $pastSource['occurrence_revision']);
$stmt->execute([$pastKey]);
c_assert($stmt->fetch() !== false, 'cancelling after reminder due preserves notification history');

$seriesRow = $pdo->query('SELECT * FROM calendar_event WHERE calendar_event_id = 1')->fetch();
c_assert(is_array($seriesRow), 'series row is available for update test');
calendar_event_recurrence_time_color_update(
    1,
    1,
    (string) $seriesRow['calendar_event_title'],
    (string) $seriesRow['calendar_event_start_date'],
    (string) $seriesRow['calendar_event_end_date'],
    (string) $seriesRow['calendar_event_note'],
    (string) $seriesRow['calendar_event_color'],
    [
        'all_day' => false,
        'start_time' => '15:00:00',
        'end_time' => '15:30:00',
        'url' => 'https://example.com/standup',
    ],
    ['repeat_type' => 'daily', 'repeat_until' => $until],
    '1h'
);
$futurePendingAfterSeriesEdit = (int) $pdo->query("SELECT COUNT(*) FROM notification WHERE notification_owner = 1 AND notification_source_id = '1' AND notification_due_at > '" . $testNow . "'")->fetchColumn();
c_assert($futurePendingAfterSeriesEdit === 0, 'series reminder edit clears stale future occurrence materialization before resync');
calendar_event_reminder_sync_owner(1);
$regularOriginal = c_date($today, 5);
$regularKey = calendar_event_reminder_source_key(1, $regularOriginal);
$stmt->execute([$regularKey]);
$afterSeriesEdit = $stmt->fetch();
c_assert(is_array($afterSeriesEdit) && ($afterSeriesEdit['notification_due_at'] ?? '') === $regularOriginal . ' 14:00:00', 'series reminder edit is inherited by future occurrences after sync');
$stmt->execute([$sourceKey]);
$movedAfterSeriesEdit = $stmt->fetch();
c_assert(is_array($movedAfterSeriesEdit) && ($movedAfterSeriesEdit['notification_due_at'] ?? '') === $movedDate . ' 16:00:00', 'series reminder edit also updates a moved occurrence using its effective time');

$seriesRow = $pdo->query('SELECT * FROM calendar_event WHERE calendar_event_id = 1')->fetch();
calendar_event_recurrence_time_color_update(
    1,
    1,
    (string) $seriesRow['calendar_event_title'],
    (string) $seriesRow['calendar_event_start_date'],
    (string) $seriesRow['calendar_event_end_date'],
    (string) $seriesRow['calendar_event_note'],
    (string) $seriesRow['calendar_event_color'],
    [
        'all_day' => false,
        'start_time' => '15:00:00',
        'end_time' => '15:30:00',
        'url' => 'https://example.com/standup',
    ],
    ['repeat_type' => 'daily', 'repeat_until' => $until],
    'none'
);
calendar_event_reminder_sync_owner(1);
$futureCount = (int) $pdo->query("SELECT COUNT(*) FROM notification WHERE notification_owner = 1 AND notification_source_id = '1' AND notification_due_at > '" . $testNow . "'")->fetchColumn();
c_assert($futureCount === 0, 'disabling a series reminder removes future occurrence reminders');
$historyCount = (int) $pdo->query("SELECT COUNT(*) FROM notification WHERE notification_owner = 1 AND notification_source_id = '1' AND notification_due_at <= '" . $testNow . "'")->fetchColumn();
c_assert($historyCount > 0, 'disabling a series reminder preserves already-due history');

calendar_event_reminder_sync_owner(2);
$owner2Count = (int) $pdo->query("SELECT COUNT(*) FROM notification WHERE notification_owner = 2")->fetchColumn();
c_assert($owner2Count > 0, 'another owner can independently materialize their own recurring reminders');
$crossOwner = (int) $pdo->query("SELECT COUNT(*) FROM notification WHERE notification_owner = 2 AND notification_source_id = '1'")->fetchColumn();
c_assert($crossOwner === 0, 'recurring notification ownership never crosses event owners');

echo "RESULT: PASS {$pass} / FAIL {$fail} / SKIP 0\n";
exit($fail === 0 ? 0 : 1);
