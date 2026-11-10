<?php
declare(strict_types=1);

/** Realistic ICS fixtures and source isolation, no network or production DB. */
const CALENDAR_ICS_MAX_ACTIVE = 10;
function conn_db(): PDO { return $GLOBALS['ics_test_db']; }
function db_table_identifier(string $name): string {
    if ($name !== 'calendar_ics_subscription') throw new LogicException('Wrong table');
    return '"ics_test_subscriptions"';
}
function calendar_ics_schema_ready(PDO $pdo): bool { return true; }
require dirname(__DIR__).'/app/calendar_ics_parser.php';

$checks = 0;
function check_ics(bool $valid,string $title): void {
    global $checks;
    ++$checks;
    echo ($valid?'PASS':'FAIL').': '.$title.PHP_EOL;
    if (!$valid) exit(1);
}
function test_ics(string $body): string {
    return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n".$body."END:VCALENDAR\r\n";
}
$event= test_ics(
    "BEGIN:VEVENT\r\nUID:utc-1\r\nDTSTART:20261110T230000Z\r\nDTEND:20261111T000000Z\r\n"
    ."SUMMARY:UTC meeting\r\nLOCATION:Tokyo\r\nDESCRIPTION:First\\nSecond\r\nURL:https://example.com/meeting\r\nEND:VEVENT\r\n"
);
$events=calendar_ics_expand($event,5,'Google','green','2026-11-10','2026-11-12');
check_ics(count($events)===1,'one-time Google ICS event');
$first=$events[0]??[];
check_ics(($first['start_date']??'')==='2026-11-11' && ($first['start_time']??'')==='08:00'
    && ($first['end_time']??'')==='09:00','UTC date and clock convert to Tokyo');
check_ics(($first['calendar_source_id']??'')==='ics:5' && ($first['external_read_only']??false)===true
    && ($first['calendar_source_name']??'')==='Google','ICS source and read-only flags');
check_ics(($first['note']??'')==="First\nSecond" && ($first['url']??'')==='https://example.com/meeting',
    'escaped description and URL decoded correctly');

$allday=test_ics(
    "BEGIN:VEVENT\r\nUID:all-1\r\nDTSTART;VALUE=DATE:20261120\r\nDTEND;VALUE=DATE:20261122\r\n"
    ."SUMMARY:Weekend\r\nEND:VEVENT\r\n"
);
$a=calendar_ics_expand($allday,5,'Holiday','red','2026-11-20','2026-11-22');
check_ics(count($a)===1 && $a[0]['all_day']===true && $a[0]['start_date']==='2026-11-20'
    && $a[0]['end_date']==='2026-11-21','all-day exclusive DTEND becomes inclusive Calendar end');

$series=test_ics(
    "BEGIN:VEVENT\r\nUID:weekly-1\r\nDTSTART;TZID=Asia/Tokyo:20261102T090000\r\n"
    ."DTEND;TZID=Asia/Tokyo:20261102T100000\r\nRRULE:FREQ=WEEKLY;BYDAY=MO,WE;COUNT=6\r\n"
    ."EXDATE;TZID=Asia/Tokyo:20261104T090000\r\nSUMMARY:Weekly\r\nEND:VEVENT\r\n"
    ."BEGIN:VEVENT\r\nUID:weekly-1\r\nRECURRENCE-ID;TZID=Asia/Tokyo:20261109T090000\r\n"
    ."STATUS:CANCELLED\r\nEND:VEVENT\r\n"
    ."BEGIN:VEVENT\r\nUID:weekly-1\r\nRECURRENCE-ID;TZID=Asia/Tokyo:20261111T090000\r\n"
    ."DTSTART;TZID=Asia/Tokyo:20261112T100000\r\nDTEND;TZID=Asia/Tokyo:20261112T110000\r\n"
    ."SUMMARY:Weekly moved\r\nEND:VEVENT\r\n"
);
$w=calendar_ics_expand($series,7,'Work','blue','2026-11-01','2026-11-15');
$dates=array_column($w,'start_date');
sort($dates);
check_ics($dates===['2026-11-02','2026-11-12'], 'RRULE COUNT/BYDAY + EXDATE + cancelled and moved RECURRENCE-ID');
check_ics(count(array_filter($w,static fn($e)=>$e['title']==='Weekly moved'))===1,
    'overridden occurrence shows amended title only once');

$monthly=test_ics(
    "BEGIN:VEVENT\r\nUID:last-weekday\r\nDTSTART;TZID=Asia/Tokyo:20261030T120000\r\n"
    ."DTEND;TZID=Asia/Tokyo:20261030T130000\r\n"
    ."RRULE:FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1;COUNT=3\r\n"
    ."SUMMARY:Last weekday\r\nEND:VEVENT\r\n"
);
$m=calendar_ics_expand($monthly,3,'Team','purple','2026-11-01','2026-12-31');
check_ics(array_column($m,'start_date')===['2026-11-30','2026-12-31'],
    'monthly BYDAY and BYSETPOS last weekday works');
$unsupported=test_ics(
    "BEGIN:VEVENT\r\nUID:unsupported\r\nDTSTART:20261110T110000Z\r\n"
    ."DTEND:20261110T120000Z\r\nRRULE:FREQ=DAILY;BYHOUR=10\r\nSUMMARY:Unsupported\r\nEND:VEVENT\r\n"
);
check_ics(count(calendar_ics_expand($unsupported,2,'Test','blue','2026-11-01','2026-11-30'))===1,
    'unsupported RRULE does not invent repeat instances');
$unsafe=test_ics("BEGIN:VEVENT\r\nUID:unsafe\r\nDTSTART;VALUE=DATE:20261120\r\n"
    ."SUMMARY:<script>alert(1)</script>\r\nURL:javascript:alert(1)\r\nEND:VEVENT\r\n");
$bad=calendar_ics_expand($unsafe,4,'Test','blue','2026-11-20','2026-11-20');
check_ics(count($bad)===1 && $bad[0]['url']===null,
    'unsafe javascript URL is not passed to client');
check_ics(calendar_ics_component_events(str_repeat('x',1048577))===[],
    'excessive ICS bodies rejected');

$pdo=new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE ics_test_subscriptions(ics_id INTEGER PRIMARY KEY,ics_owner INTEGER,ics_name TEXT,ics_color TEXT,ics_raw TEXT)');
$stmt=$pdo->prepare('INSERT INTO ics_test_subscriptions VALUES(?,?,?,?,?)');
$stmt->execute([5,11,'Owner11','green',$event]);
$stmt->execute([7,22,'Owner22','red',$allday]);
$GLOBALS['ics_test_db']=$pdo;
$r=calendar_ics_range(11,'2026-11-01','2026-11-30');
check_ics(count($r['sources'])===1 && $r['sources'][0]['source_id']==='ics:5'
    && count($r['events'])===1 && $r['events'][0]['calendar_source_id']==='ics:5',
    'owner-scoped ICS range never exposes another user');
check_ics(strpos(json_encode($r),'Owner22')===false, 'other owner is absent from response');
echo "RESULT: PASS $checks / FAIL 0".PHP_EOL;
