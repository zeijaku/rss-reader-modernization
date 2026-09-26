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

function occurrence_reminder_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$pdo = conn_db();
$pdo->exec('CREATE TABLE rss_dashboard_widget (
    widget_id INTEGER PRIMARY KEY AUTOINCREMENT,
    widget_owner INTEGER NOT NULL,
    widget_location INTEGER NOT NULL,
    widget_type TEXT NOT NULL,
    widget_sort_order INTEGER NOT NULL,
    widget_flag INTEGER NOT NULL
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
$pdo->exec("INSERT INTO rss_dashboard_widget
    (widget_owner, widget_location, widget_type, widget_sort_order, widget_flag)
    VALUES (1, 1, 'calendar', 0, 0)");

$base = [
    'event_id' => 7,
    'title' => 'Weekly meeting',
    'original_occurrence_start_date' => '2099-06-08',
    'occurrence_start_date' => '2099-06-08',
    'occurrence_end_date' => '2099-06-08',
    'all_day' => false,
    'start_time' => '15:00',
];
$key = calendar_event_reminder_upsert_occurrence($pdo, 1, 7, $base, '30m');
occurrence_reminder_assert($key === 'event:7:occurrence:2099-06-08:reminder', 'source key mismatch');
$row = $pdo->query("SELECT * FROM rss_notification WHERE notification_source_id = '7'")->fetch();
occurrence_reminder_assert(is_array($row), 'occurrence notification was not created');
occurrence_reminder_assert($row['notification_due_at'] === '2099-06-08 14:30:00', 'base occurrence due time mismatch');
occurrence_reminder_assert($row['notification_target_url'] === './?tab=1&calendar_date=2099-06-08&calendar_event_id=7', 'base target mismatch');
$firstId = (int) $row['notification_id'];

$moved = $base;
$moved['occurrence_start_date'] = '2099-06-09';
$moved['occurrence_end_date'] = '2099-06-09';
$moved['start_time'] = '17:00';
calendar_event_reminder_upsert_occurrence($pdo, 1, 7, $moved, '30m');
$row = $pdo->query("SELECT * FROM rss_notification WHERE notification_source_id = '7'")->fetch();
occurrence_reminder_assert((int) $row['notification_id'] === $firstId, 'moved occurrence duplicated notification');
occurrence_reminder_assert($row['notification_due_at'] === '2099-06-09 16:30:00', 'moved occurrence was not rescheduled');
occurrence_reminder_assert($row['notification_target_url'] === './?tab=1&calendar_date=2099-06-09&calendar_event_id=7', 'moved target was not updated');

calendar_event_reminder_upsert_occurrence($pdo, 1, 7, $moved, '1h');
$row = $pdo->query("SELECT * FROM rss_notification WHERE notification_source_id = '7'")->fetch();
occurrence_reminder_assert((int) $row['notification_id'] === $firstId, 'series reminder change duplicated notification');
occurrence_reminder_assert($row['notification_due_at'] === '2099-06-09 16:00:00', 'series reminder change was not applied');

$other = $base;
$other['original_occurrence_start_date'] = '2099-06-15';
$other['occurrence_start_date'] = '2099-06-15';
$other['occurrence_end_date'] = '2099-06-15';
calendar_event_reminder_upsert_occurrence($pdo, 1, 7, $other, '30m');
occurrence_reminder_assert((int) $pdo->query("SELECT COUNT(*) FROM rss_notification WHERE notification_source_id = '7'")->fetchColumn() === 2, 'distinct occurrences did not receive distinct reminders');

calendar_event_reminder_cancel_pending($pdo, 1, 7);
occurrence_reminder_assert((int) $pdo->query("SELECT COUNT(*) FROM rss_notification WHERE notification_source_id = '7'")->fetchColumn() === 0, 'pending occurrence reminders were not cancelled');

occurrence_reminder_assert(calendar_event_reminder_occurrence_due_at([
    'occurrence_start_date' => '2099-01-01',
    'all_day' => false,
    'start_time' => '00:05',
], '10m') === '2098-12-31 23:55:00', 'occurrence year-boundary calculation failed');

occurrence_reminder_assert(calendar_event_reminder_occurrence_due_at([
    'occurrence_start_date' => '2099-01-01',
    'all_day' => true,
    'start_time' => null,
], '1d') === '2098-12-31 09:00:00', 'all-day occurrence previous-day calculation failed');

echo "calendar occurrence reminder backend tests passed\n";
