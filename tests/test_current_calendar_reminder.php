<?php
declare(strict_types=1);

define('DB_DRIVER', 'sqlite');
define('DB_SQLITE_PATH', ':memory:');
define('DB_TABLE_PREFIX', 'rss_');

require dirname(__DIR__) . '/app/common/common_conf.php';
require dirname(__DIR__) . '/app/common/common_db.php';
require dirname(__DIR__) . '/app/validation.php';
require dirname(__DIR__) . '/app/calendar.php';
require dirname(__DIR__) . '/app/calendar_time.php';
require dirname(__DIR__) . '/app/notification.php';
require dirname(__DIR__) . '/app/calendar_reminder.php';

function reminder_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$timed = [
    'calendar_event_start_date' => '2099-06-01',
    'calendar_event_all_day' => 0,
    'calendar_event_start_time' => '15:00:00',
    'calendar_event_reminder' => '30m',
];
reminder_assert(calendar_event_reminder_due_at($timed) === '2099-06-01 14:30:00', '30 minute reminder calculation failed');

$allDay = [
    'calendar_event_start_date' => '2099-06-01',
    'calendar_event_all_day' => 1,
    'calendar_event_start_time' => null,
    'calendar_event_reminder' => '1d',
];
reminder_assert(calendar_event_reminder_due_at($allDay) === '2099-05-31 09:00:00', 'all-day previous-day calculation failed');

$boundary = [
    'calendar_event_start_date' => '2099-01-01',
    'calendar_event_all_day' => 0,
    'calendar_event_start_time' => '00:05:00',
    'calendar_event_reminder' => '10m',
];
reminder_assert(calendar_event_reminder_due_at($boundary) === '2098-12-31 23:55:00', 'date-boundary calculation failed');
reminder_assert(calendar_event_reminder_validate('bogus') === null, 'invalid reminder accepted');

$pdo = conn_db();
$pdo->exec('CREATE TABLE rss_dashboard_widget (
    widget_id INTEGER PRIMARY KEY AUTOINCREMENT,
    widget_owner INTEGER NOT NULL,
    widget_location INTEGER NOT NULL,
    widget_type TEXT NOT NULL,
    widget_sort_order INTEGER NOT NULL,
    widget_flag INTEGER NOT NULL
)');
$pdo->exec('CREATE TABLE rss_calendar_event (
    calendar_event_id INTEGER PRIMARY KEY AUTOINCREMENT,
    calendar_event_updated_at TEXT NOT NULL,
    calendar_event_flag INTEGER NOT NULL,
    calendar_event_owner INTEGER NOT NULL,
    calendar_event_title TEXT NOT NULL,
    calendar_event_start_date TEXT NOT NULL,
    calendar_event_all_day INTEGER NOT NULL,
    calendar_event_start_time TEXT NULL,
    calendar_event_repeat_type TEXT NOT NULL,
    calendar_event_reminder TEXT NOT NULL
)');
$pdo->exec('CREATE TABLE rss_notification (
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

$pdo->exec("INSERT INTO rss_dashboard_widget (widget_owner, widget_location, widget_type, widget_sort_order, widget_flag) VALUES (1, 2, 'calendar', 0, 0)");
$pdo->exec("INSERT INTO rss_calendar_event (calendar_event_id, calendar_event_updated_at, calendar_event_flag, calendar_event_owner, calendar_event_title, calendar_event_start_date, calendar_event_all_day, calendar_event_start_time, calendar_event_repeat_type, calendar_event_reminder) VALUES (1, '2099-01-01 00:00:00', 0, 1, 'Hospital', '2099-06-01', 0, '15:00:00', 'none', '30m')");

calendar_event_reminder_reconcile($pdo, 1, 1);
$row = $pdo->query('SELECT * FROM rss_notification WHERE notification_owner = 1')->fetch();
reminder_assert(is_array($row), 'pending notification was not created');
reminder_assert($row['notification_due_at'] === '2099-06-01 14:30:00', 'pending due time is wrong');
reminder_assert($row['notification_target_url'] === './?tab=2&calendar_date=2099-06-01&calendar_event_id=1', 'Calendar target URL is wrong');
$firstId = (int) $row['notification_id'];

$pdo->exec("UPDATE rss_calendar_event SET calendar_event_start_time = '16:00:00' WHERE calendar_event_id = 1");
calendar_event_reminder_reconcile($pdo, 1, 1);
$row = $pdo->query('SELECT * FROM rss_notification WHERE notification_owner = 1')->fetch();
reminder_assert((int) $row['notification_id'] === $firstId, 'pending reminder was duplicated on reschedule');
reminder_assert($row['notification_due_at'] === '2099-06-01 15:30:00', 'pending reminder was not rescheduled');

$pdo->exec("UPDATE rss_calendar_event SET calendar_event_reminder = 'none' WHERE calendar_event_id = 1");
calendar_event_reminder_reconcile($pdo, 1, 1);
reminder_assert((int) $pdo->query('SELECT COUNT(*) FROM rss_notification')->fetchColumn() === 0, 'disabled pending reminder was not removed');

$pdo->exec("UPDATE rss_calendar_event SET calendar_event_reminder = '10m' WHERE calendar_event_id = 1");
calendar_event_reminder_reconcile($pdo, 1, 1);
reminder_assert((int) $pdo->query('SELECT COUNT(*) FROM rss_notification')->fetchColumn() === 1, 're-enabled reminder was not recreated');

$occurrence = [
    'occurrence_start_date' => '2099-06-08',
    'original_occurrence_start_date' => '2099-06-08',
    'all_day' => false,
    'start_time' => '15:00',
    'title' => 'Weekly meeting',
];
reminder_assert(
    calendar_event_reminder_occurrence_source_key(1, '2099-06-08') === 'event:1:occurrence:2099-06-08:reminder',
    'occurrence reminder source key is unstable'
);
reminder_assert(
    calendar_event_reminder_occurrence_due_at($occurrence, '30m') === '2099-06-08 14:30:00',
    'occurrence reminder due calculation failed'
);
calendar_event_reminder_apply($pdo, 1, 1, '30m');
reminder_assert(
    (string) $pdo->query('SELECT calendar_event_reminder FROM rss_calendar_event WHERE calendar_event_id = 1')->fetchColumn() === '30m',
    'recurring-compatible reminder apply failed'
);

$pdo->exec("INSERT INTO rss_calendar_event (calendar_event_id, calendar_event_updated_at, calendar_event_flag, calendar_event_owner, calendar_event_title, calendar_event_start_date, calendar_event_all_day, calendar_event_start_time, calendar_event_repeat_type, calendar_event_reminder) VALUES (2, '2020-01-01 00:00:00', 0, 1, 'Past event', '2020-01-02', 0, '15:00:00', 'none', '30m')");
calendar_event_reminder_reconcile($pdo, 1, 2);
$pastDue = (string) $pdo->query('SELECT notification_due_at FROM rss_notification WHERE notification_source_id = "2"')->fetchColumn();
reminder_assert($pastDue === '2020-01-02 14:30:00', 'past-due reminder was not created');
$pdo->exec("UPDATE rss_calendar_event SET calendar_event_start_date = '2099-07-01', calendar_event_start_time = '18:00:00', calendar_event_reminder = '1h' WHERE calendar_event_id = 2");
calendar_event_reminder_reconcile($pdo, 1, 2);
$preserved = (string) $pdo->query('SELECT notification_due_at FROM rss_notification WHERE notification_source_id = "2"')->fetchColumn();
reminder_assert($preserved === $pastDue, 'already-due reminder history was rewritten');

$pdo->exec("INSERT INTO rss_calendar_event (calendar_event_id, calendar_event_updated_at, calendar_event_flag, calendar_event_owner, calendar_event_title, calendar_event_start_date, calendar_event_all_day, calendar_event_start_time, calendar_event_repeat_type, calendar_event_reminder) VALUES (3, '2099-01-01 00:00:00', 0, 1, 'Delete me', '2099-08-01', 0, '12:00:00', 'none', '1h')");
calendar_event_reminder_reconcile($pdo, 1, 3);
reminder_assert((int) $pdo->query('SELECT COUNT(*) FROM rss_notification WHERE notification_source_id = "3"')->fetchColumn() === 1, 'delete test reminder missing');
reminder_assert(calendar_delete_event(1, 3), 'Calendar delete failed');
reminder_assert((int) $pdo->query('SELECT COUNT(*) FROM rss_notification WHERE notification_source_id = "3"')->fetchColumn() === 0, 'future reminder survived Calendar delete');

echo "calendar reminder backend tests passed\n";
