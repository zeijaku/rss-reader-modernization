<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/calendar_ics_export.php';

function transfer_check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS: $label\n";
}

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
set_db_connection_for_testing($pdo);
$events = db_table_identifier('calendar_event');
$sources = db_table_identifier('calendar_source');
$pdo->exec("CREATE TABLE $events (calendar_event_id INTEGER PRIMARY KEY, calendar_event_owner INTEGER, calendar_event_flag INTEGER, calendar_event_source_id INTEGER, calendar_event_date TEXT, calendar_event_updated_at TEXT, calendar_event_title TEXT, calendar_event_start_date TEXT, calendar_event_end_date TEXT, calendar_event_note TEXT, calendar_event_all_day INTEGER DEFAULT 1, calendar_event_start_time TEXT, calendar_event_end_time TEXT, calendar_event_url TEXT, calendar_event_location TEXT, calendar_event_repeat_type TEXT DEFAULT 'none')");
transfer_check(!calendar_ics_uid_schema_ready($pdo), 'pre-migration detected');
$old = calendar_create_event(7,'旧予定','2026-11-27','2026-11-29','old');
transfer_check($old > 0, 'pre-migration create remains available');
$pdo->exec("ALTER TABLE $events ADD COLUMN calendar_event_ics_uid BLOB");
$pdo->exec("DELETE FROM $events");
$pdo->exec("CREATE TABLE $sources (calendar_source_id INTEGER PRIMARY KEY, calendar_source_owner INTEGER, calendar_source_flag INTEGER, calendar_source_default INTEGER)");
$pdo->exec("INSERT INTO $sources VALUES (1,7,0,1),(2,7,0,0),(3,8,0,1),(4,7,0,0)");
$id = calendar_create_event(7,'日本語予定,;'.str_repeat('あ',30),'2026-11-27','2026-11-29',"行1\n行2\\末尾");
$uid = $pdo->query("SELECT calendar_event_ics_uid FROM $events WHERE calendar_event_id=$id")->fetchColumn();
transfer_check(calendar_ics_uid_valid($uid) && str_starts_with($uid,'urn:uuid:'),'UID generated on create');
$id2 = calendar_create_event(7,'夜間','2026-11-27','2026-11-28','');
$pdo->exec("UPDATE $events SET calendar_event_source_id=1, calendar_event_all_day=0, calendar_event_start_time='23:00:00', calendar_event_end_time='01:00:00' WHERE calendar_event_id=$id2");
$id3 = calendar_create_event(8,'OTHER OWNER','2026-11-27','2026-11-27','');
$id4 = calendar_create_event(7,'OTHER SOURCE','2026-11-27','2026-11-27','');
$pdo->exec("UPDATE $events SET calendar_event_source_id=2 WHERE calendar_event_id=$id4");
$id5 = calendar_create_event(7,'DELETED','2026-11-27','2026-11-27','');
$pdo->exec("UPDATE $events SET calendar_event_flag=1 WHERE calendar_event_id=$id5");
$before = $pdo->query("SELECT * FROM $events ORDER BY calendar_event_id")->fetchAll();
$ics = calendar_transfer_export_basic($pdo,7,1);
$after = $pdo->query("SELECT * FROM $events ORDER BY calendar_event_id")->fetchAll();
transfer_check($before === $after, 'export does not mutate data');
transfer_check($ics === calendar_transfer_export_basic($pdo,7,1), 'stable export and UID');
transfer_check(substr_count($ics,'BEGIN:VEVENT')===2 && !str_contains($ics,'OTHER') && !str_contains($ics,'DELETED'), 'owner/source/flag isolation');
transfer_check(str_contains($ics,"DTEND;VALUE=DATE:20261130\r\n"),'all-day exclusive end');
transfer_check(str_contains($ics,"DTSTART:20261127T140000Z\r\n") && str_contains($ics,"DTEND:20261127T160000Z\r\n"), 'Tokyo UTC conversion across midnight');
transfer_check(str_contains($ics,'DESCRIPTION:行1\n行2\\'.'\\'.'末尾'), 'text escape');
foreach (explode("\r\n",trim($ics)) as $line) {
    if (strlen($line)>75 || preg_match('//u',$line)!==1) throw new RuntimeException('Invalid fold');
}
transfer_check(true,'UTF-8 octet folding');
transfer_check(substr_count(calendar_transfer_export_basic($pdo,7,4),'BEGIN:VEVENT')===0,'empty calendar exports valid envelope');
try { calendar_transfer_export_basic($pdo,7,3); throw new RuntimeException('foreign source allowed'); }
catch (OutOfBoundsException $e) { transfer_check(true,'foreign source refused'); }
$pdo->exec("UPDATE $events SET calendar_event_repeat_type='daily' WHERE calendar_event_id=$id");
try { calendar_transfer_export_basic($pdo,7,1); throw new RuntimeException('series silently exported'); }
catch (DomainException $e) { transfer_check(true,'D1 refuses incomplete recurrence export'); }
