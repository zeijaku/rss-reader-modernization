<?php
declare(strict_types=1);
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
require_once __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/api.php';

function import_check(bool $ok,string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS: $label\n";
}
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
set_db_connection_for_testing($pdo);
$events = db_table_identifier('calendar_event');
$sources = db_table_identifier('calendar_source');
$exceptions = db_table_identifier('calendar_event_exception');
$marks = db_table_identifier('calendar_day_mark');
$pdo->exec("CREATE TABLE $events (calendar_event_id INTEGER PRIMARY KEY, calendar_event_owner INTEGER, calendar_event_flag INTEGER DEFAULT 0, calendar_event_source_id INTEGER, calendar_event_date TEXT, calendar_event_updated_at TEXT, calendar_event_title TEXT, calendar_event_start_date TEXT, calendar_event_end_date TEXT, calendar_event_note TEXT, calendar_event_all_day INTEGER DEFAULT 1, calendar_event_start_time TEXT, calendar_event_end_time TEXT, calendar_event_url TEXT, calendar_event_location TEXT, calendar_event_repeat_type TEXT DEFAULT 'none',calendar_event_repeat_until TEXT,calendar_event_color TEXT DEFAULT 'blue',calendar_event_deadline_highlight INTEGER DEFAULT 0,calendar_event_reminder TEXT DEFAULT 'none',calendar_event_ics_uid BLOB)");
$pdo->exec("CREATE TABLE $sources (calendar_source_id INTEGER PRIMARY KEY, calendar_source_owner INTEGER, calendar_source_flag INTEGER, calendar_source_default INTEGER,calendar_source_name TEXT,calendar_source_color TEXT)");
$pdo->exec("INSERT INTO $sources VALUES (1,7,0,1,'仕事','blue'),(2,7,0,0,'私用','green'),(3,8,0,1,'OTHER','red')");
$pdo->exec("CREATE TABLE $exceptions (calendar_event_exception_id INTEGER PRIMARY KEY,calendar_event_exception_owner INTEGER,calendar_event_exception_event_id INTEGER,calendar_event_exception_original_start_date TEXT,calendar_event_exception_kind TEXT,calendar_event_exception_revision INTEGER DEFAULT 1,calendar_event_exception_flag INTEGER DEFAULT 0,calendar_event_exception_start_date TEXT,calendar_event_exception_end_date TEXT,calendar_event_exception_title TEXT,calendar_event_exception_note TEXT,calendar_event_exception_color TEXT,calendar_event_exception_all_day INTEGER,calendar_event_exception_start_time TEXT,calendar_event_exception_end_time TEXT,calendar_event_exception_url TEXT,calendar_event_exception_location TEXT,calendar_event_exception_deadline_highlight INTEGER,calendar_event_exception_created_at TEXT,calendar_event_exception_updated_at TEXT)");
$pdo->exec("CREATE TABLE $marks (mark_owner INTEGER,mark_date TEXT,mark_label TEXT,mark_note TEXT, PRIMARY KEY(mark_owner,mark_date))");
$pdo->exec("INSERT INTO $marks VALUES (7,'2026-03-31','締切','メモ,;'),(7,'2026-01-01','別日',''),(8,'2026-03-31','OTHER','PRIVATE')");

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
function import_ics(string $body): string {return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n".$body."END:VCALENDAR\r\n";}
function import_event(string $uid,string $summary,string $extra=''): string {
    return "BEGIN:VEVENT\r\nUID:$uid\r\nDTSTART;VALUE=DATE:20261127\r\nDTEND;VALUE=DATE:20261129\r\nSUMMARY:$summary\r\n".$extra."END:VEVENT\r\n";
}
function import_preview(array $input): array {
    $r=api_dispatch('calendar.transfer.preview',7,$input);
    import_check($r['status']===200,'preview API succeeds');
    return $r['body']['data'];
}
function import_execute(array $input,array $p): array {
    return api_dispatch('calendar.transfer.import',7,$input+['preview_token'=>$p['preview_token']]);
}
$input=['source_id'=>'1','ics'=>import_ics(import_event('a','予定',"DESCRIPTION:literal\\\\n\\nnext\\,\\;\r\n"))];
$p=import_preview($input);
import_check($p['counts']['add']===1&&$p['can_apply']&&calendar_active_event_count($pdo,7)===0,'preview read-only');
import_check(import_execute($input,$p)['status']===200,'add import transaction');
$row=$pdo->query("SELECT * FROM $events WHERE calendar_event_owner=7")->fetch();
import_check($row['calendar_event_ics_uid']==='a'&&$row['calendar_event_end_date']==='2026-11-28'&&$row['calendar_event_note']==="literal\\n\nnext,;",'UID, exclusive end and exact text unescaping');
$p=import_preview($input);
import_check($p['counts']['skip']===1&&!$p['can_apply'],'duplicate add skips');
$changed=['source_id'=>'1','mode'=>'update','ics'=>import_ics(import_event('a','変更'))];
$p=import_preview($changed);
$pdo->exec("UPDATE $events SET calendar_event_title='local edit' WHERE calendar_event_ics_uid='a'");
import_check(import_execute($changed,$p)['status']===409,'stale preview rejects changed DB');
$p=import_preview($changed);
import_check($p['counts']['update']===1&&import_execute($changed,$p)['status']===200,'explicit update');
import_check($pdo->query("SELECT calendar_event_title FROM $events WHERE calendar_event_ics_uid='a'")->fetchColumn()==='変更','update changes correct UID');
$other=['source_id'=>'3','ics'=>$input['ics']];
import_check(api_dispatch('calendar.transfer.preview',7,$other)['status']===404,'foreign target denied');
$series=import_ics("BEGIN:VEVENT\r\nUID:series\r\nDTSTART;TZID=Asia/Tokyo:20261127T083045\r\nDTEND;TZID=Asia/Tokyo:20261127T093050\r\nSUMMARY:連日\r\nRRULE:FREQ=DAILY;COUNT=4\r\nEXDATE;TZID=Asia/Tokyo:20261128T083045\r\nCOLOR:red\r\nX-RSSREADER-DEADLINE:1\r\nX-RSSREADER-REMINDER:10m\r\nBEGIN:VALARM\r\nACTION:DISPLAY\r\nTRIGGER:-PT10M\r\nDESCRIPTION:連日\r\nEND:VALARM\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nUID:series\r\nRECURRENCE-ID;TZID=Asia/Tokyo:20261129T083045\r\nDTSTART;TZID=Asia/Tokyo:20261202T111213\r\nDTEND;TZID=Asia/Tokyo:20261202T121314\r\nSUMMARY:移動\r\nCOLOR:green\r\nEND:VEVENT\r\n");
$input=['source_id'=>'1','ics'=>$series];
$p=import_preview($input); import_check($p['counts']['add']===1&&import_execute($input,$p)['status']===200,'series and exceptions import');
$row=$pdo->query("SELECT * FROM $events WHERE calendar_event_ics_uid='series'")->fetch();
import_check($row['calendar_event_repeat_until']==='2026-11-30'&&$row['calendar_event_start_time']==='08:30:45'&&$row['calendar_event_reminder']==='10m'&&(int)$row['calendar_event_deadline_highlight']===1,'COUNT, seconds, reminder and metadata restored');
import_check((int)$pdo->query("SELECT COUNT(*) FROM $exceptions WHERE calendar_event_exception_event_id=".(int)$row['calendar_event_id'])->fetchColumn()===2,'cancel and override stored');
$bad=['source_id'=>'1','ics'=>import_ics(import_event('bad','bad',"RRULE:FREQ=WEEKLY;INTERVAL=2\r\n").import_event('valid','valid'))];
$p=import_preview($bad); import_check($p['counts']['unsupported']===1&&$p['counts']['add']===1,'unsupported series reported, valid event remains');
$conflict=['source_id'=>'1','mode'=>'update','ics'=>import_ics(import_event('series','not recurring'))];
$p=import_preview($conflict); import_check($p['counts']['conflict']===1&&!$p['can_apply'],'existing exceptions protect series from incompatible update');
$full=calendar_transfer_export($pdo,7,1,null,true)['calendar-1.ics'];
$new=['target'=>'new','name'=>'復元','ics'=>$full];
$p=import_preview($new); import_check($p['counts']['add']===2&&import_execute($new,$p)['status']===200,'full export imports into new calendar');
$copyId=(int)$pdo->query("SELECT calendar_source_id FROM $sources WHERE calendar_source_name='復元'")->fetchColumn();
$round=calendar_transfer_export($pdo,7,$copyId,null,true)['calendar-'.$copyId.'.ics'];
import_check(str_contains($round,'RECURRENCE-ID')&&str_contains($round,'EXDATE')&&str_contains($round,'X-RSSREADER-DAY-MARK:'),'round trip exceptions and marks');
$repeat=['source_id'=>(string)$copyId,'mode'=>'update','ics'=>$round];
$p=import_preview($repeat); import_check($p['counts']['skip']===2&&!$p['can_apply'],'identical full import skips without rewriting');
$standardOnly=['source_id'=>'1','include_extra'=>'0','ics'=>import_ics(import_event('standard-only','standard',"COLOR:green\r\nX-RSSREADER-COLOR:red\r\nX-RSSREADER-DEADLINE:1\r\n"))];
$p=import_preview($standardOnly); import_check($p['counts']['add']===1&&import_execute($standardOnly,$p)['status']===200,'standard-only import');
$r=$pdo->query("SELECT calendar_event_color,calendar_event_deadline_highlight FROM $events WHERE calendar_event_ics_uid='standard-only'")->fetch();
import_check($r['calendar_event_color']==='green'&&(int)$r['calendar_event_deadline_highlight']===0,'custom attributes disabled without suppressing standard COLOR');
$badInterval=['source_id'=>'1','ics'=>import_ics(import_event('badinterval','bad',"RRULE:FREQ=WEEKLY;INTERVAL=1oops\r\n"))];
$p=import_preview($badInterval); import_check($p['counts']['unsupported']===1&&!$p['can_apply'],'malformed interval rejected');
$badSyntax=['source_id'=>'1','ics'=>"BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nEND:VCALENDAR\r\n"];
import_check(api_dispatch('calendar.transfer.preview',7,$badSyntax)['status']===422,'invalid component rejected');
$rollback=['source_id'=>'1','ics'=>import_ics(import_event('rollback1','first').import_event('rollback2','second'))];
$p=import_preview($rollback); $before=calendar_active_event_count($pdo,7);
$pdo->exec("CREATE TRIGGER force_import_failure BEFORE INSERT ON $events WHEN NEW.calendar_event_title='second' BEGIN SELECT RAISE(ABORT,'forced'); END");
try {import_execute($rollback,$p); throw new RuntimeException('expected DB failure');} catch (PDOException $e) {}
import_check(calendar_active_event_count($pdo,7)===$before&&!$pdo->inTransaction(),'DB failure rolls back whole import');
