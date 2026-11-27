<?php
declare(strict_types=1);
require_once __DIR__.'/../../app/bootstrap.php';
$pdo = new PDO('sqlite:' . $argv[1], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
set_db_connection_for_testing($pdo);
$events = db_table_identifier('calendar_event');
$sources = db_table_identifier('calendar_source');
$exceptions = db_table_identifier('calendar_event_exception');
$marks = db_table_identifier('calendar_day_mark');
$pdo->exec("CREATE TABLE $events (calendar_event_id INTEGER PRIMARY KEY, calendar_event_owner INTEGER, calendar_event_flag INTEGER DEFAULT 0, calendar_event_source_id INTEGER, calendar_event_date TEXT, calendar_event_updated_at TEXT, calendar_event_title TEXT, calendar_event_start_date TEXT, calendar_event_end_date TEXT, calendar_event_note TEXT, calendar_event_all_day INTEGER DEFAULT 1, calendar_event_start_time TEXT, calendar_event_end_time TEXT, calendar_event_url TEXT, calendar_event_location TEXT, calendar_event_repeat_type TEXT DEFAULT 'none',calendar_event_repeat_until TEXT,calendar_event_color TEXT DEFAULT 'blue',calendar_event_deadline_highlight INTEGER DEFAULT 0,calendar_event_reminder TEXT DEFAULT 'none',calendar_event_ics_uid BLOB)");
$pdo->exec("CREATE TABLE $sources (calendar_source_id INTEGER PRIMARY KEY, calendar_source_owner INTEGER, calendar_source_flag INTEGER, calendar_source_default INTEGER,calendar_source_name TEXT,calendar_source_color TEXT)");
$pdo->exec("INSERT INTO $sources VALUES (1,42,0,1,'仕事','blue'),(2,42,0,0,'私用','green'),(3,99,0,1,'OTHER','red')");
$pdo->exec("CREATE TABLE $exceptions (calendar_event_exception_id INTEGER PRIMARY KEY,calendar_event_exception_owner INTEGER,calendar_event_exception_event_id INTEGER,calendar_event_exception_original_start_date TEXT,calendar_event_exception_kind TEXT,calendar_event_exception_revision INTEGER DEFAULT 1,calendar_event_exception_flag INTEGER DEFAULT 0,calendar_event_exception_start_date TEXT,calendar_event_exception_end_date TEXT,calendar_event_exception_title TEXT,calendar_event_exception_note TEXT,calendar_event_exception_color TEXT,calendar_event_exception_all_day INTEGER,calendar_event_exception_start_time TEXT,calendar_event_exception_end_time TEXT,calendar_event_exception_url TEXT,calendar_event_exception_location TEXT,calendar_event_exception_deadline_highlight INTEGER,calendar_event_exception_created_at TEXT,calendar_event_exception_updated_at TEXT)");
$pdo->exec("CREATE TABLE $marks (mark_owner INTEGER,mark_date TEXT,mark_label TEXT,mark_note TEXT, PRIMARY KEY(mark_owner,mark_date))");
$pdo->exec("INSERT INTO $marks VALUES (42,'2026-03-31','締切','メモ,;'),(42,'2026-01-01','別日',''),(99,'2026-03-31','OTHER','PRIVATE')");

$pdo->exec('CREATE TABLE ' . db_table_identifier('notification') . ' (
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

$pdo->exec("ALTER TABLE $sources ADD COLUMN calendar_source_date TEXT");
$pdo->exec("ALTER TABLE $sources ADD COLUMN calendar_source_updated_at TEXT");
$pdo->exec("ALTER TABLE $sources ADD COLUMN calendar_source_sort_order INTEGER DEFAULT 0");
