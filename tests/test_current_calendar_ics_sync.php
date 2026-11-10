<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Tokyo');

/** Test the 30-minute on-demand subscription cache without network access. */
function check_ics_sync(bool $ok, string $label): void {
    if (!$ok) { fwrite(STDERR, "FAIL: ".$label.PHP_EOL); exit(1); }
    echo "PASS: ".$label.PHP_EOL;
}
function app_validate_feed_url(mixed $v): ?string {
    return is_string($v) && filter_var($v, FILTER_VALIDATE_URL) ? $v : null;
}
function calendar_source_validate_name(mixed $v): ?string {
    return is_string($v) && trim($v) !== '' ? trim($v) : null;
}
function calendar_source_validate_color(mixed $v): ?string {
    return in_array($v, ['blue','green','purple','yellow','red'], true) ? $v : null;
}
function conn_db(): PDO { return $GLOBALS['ics_sync_pdo']; }
function db_table_identifier(string $logical): string {
    if ($logical !== 'calendar_ics_subscription') throw new LogicException('Wrong table');
    return '"ics_test_subscriptions"';
}
function app_now(): string { return date('Y-m-d H:i:s'); }
function app_curl_single_hop(array $request): array {
    return ['ok'=>true,'status'=>200,'body'=>$GLOBALS['ics_sync_body']];
}
function app_safe_http_fetch(
    string $url, ?callable $resolver = null, ?callable $transport = null,
    array $validators = [], array $requestOptions = []
): array {
    check_ics_sync(str_starts_with($url, 'https://'), 'HTTPS-only request');
    check_ics_sync(($requestOptions['max_bytes'] ?? 0) === 1048576, '1MiB bound is kept');
    if (($GLOBALS['ics_sync_fail'] ?? false) === true) {
        return ['ok'=>false,'error_code'=>'http_status','body'=>''];
    }
    return ['ok'=>true,'status'=>200,'body'=>$GLOBALS['ics_sync_body']];
}

require dirname(__DIR__) . '/app/calendar_ics.php';
require dirname(__DIR__) . '/app/calendar_ics_parser.php';

$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$db->exec("CREATE TABLE ics_test_subscriptions(
 ics_id INTEGER PRIMARY KEY AUTOINCREMENT,
 ics_owner INTEGER NOT NULL, ics_name TEXT NOT NULL, ics_color TEXT NOT NULL,
 ics_url TEXT NOT NULL, ics_raw TEXT,
 ics_status TEXT DEFAULT 'pending', ics_error_code TEXT DEFAULT '',
 ics_last_checked_at TEXT NULL, ics_last_success_at TEXT NULL,
 ics_created_at TEXT NOT NULL, ics_updated_at TEXT NOT NULL)");
$GLOBALS['ics_sync_pdo']=$db;
$old="BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:old-1\r\nDTSTART;VALUE=DATE:20261110\r\nSUMMARY:Deleted meeting\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
$empty="BEGIN:VCALENDAR\r\nVERSION:2.0\r\nEND:VCALENDAR\r\n";
$GLOBALS['ics_sync_body']=$old;
$owner=50;
$id=calendar_ics_create($owner,['name'=>'Google','color'=>'red','url'=>'https://calendar.example.com/private.ics?token=SECRET']);
$other=calendar_ics_create(51,['name'=>'Other','color'=>'blue','url'=>'https://calendar.example.net/other.ics?token=OTHER']);
check_ics_sync($id>0 && $other>0,'Owner-isolated subscriptions created');
check_ics_sync(CALENDAR_ICS_AUTO_REFRESH_SECONDS===1800 && CALENDAR_ICS_MIN_REFRESH_SECONDS===60,
    '30-minute auto TTL and 60-second manual cap');
$a=calendar_ics_sync_due($owner);
check_ics_sync($a['attempted']===1 && $a['changed']===1 && $a['failed']===0 && $a['remaining']===0,
    'New subscription fetch occurs only once');
$stored=calendar_ics_owned($db,$owner,$id);
check_ics_sync($stored['ics_raw']===$old && $stored['ics_status']==='ok','Initial payload stored');
check_ics_sync(count(calendar_ics_expand($stored['ics_raw'],$id,'Google','red','2026-11-01','2026-11-30'))===1,
    'Existing event can be rendered');
check_ics_sync(calendar_ics_sync_due($owner)['attempted']===0,
    'Second automatic poll within TTL avoids remote fetch');
check_ics_sync(calendar_ics_sync_due($owner,true)['attempted']===0,
    'Manual refresh within 60 seconds is throttled');
$recent=date('Y-m-d H:i:s', time()-300);
$db->prepare("UPDATE ics_test_subscriptions SET ics_last_checked_at=? WHERE ics_id=?")->execute([$recent,$id]);
check_ics_sync(calendar_ics_sync_due($owner)['attempted']===0,
    'Five-minute old feed is not due for automatic refresh');
$GLOBALS['ics_sync_body']=$empty;
$manual=calendar_ics_sync_due($owner,true);
check_ics_sync($manual['attempted']===1 && $manual['changed']===1,
    'Manual refresh bypasses 30-minute window');
$stored=calendar_ics_owned($db,$owner,$id);
check_ics_sync($stored['ics_raw']===$empty &&
    calendar_ics_expand($stored['ics_raw'],$id,'Google','red','2026-11-01','2026-11-30')===[],
    'Google deletion replaces cache with valid empty calendar');
$db->prepare("UPDATE ics_test_subscriptions SET ics_last_checked_at=? WHERE ics_id=?")
    ->execute([date('Y-m-d H:i:s', time()-1900),$id]);
$GLOBALS['ics_sync_fail']=true;
$failed=calendar_ics_sync_due($owner);
$stored=calendar_ics_owned($db,$owner,$id);
check_ics_sync($failed['attempted']===1 && $failed['failed']===1 &&
    $failed['changed']===0 && $stored['ics_raw']===$empty && $stored['ics_status']==='error',
    'HTTP error keeps last good data and reports status');
check_ics_sync(calendar_ics_sync_due($owner)['attempted']===0,
    'Error is throttled for 30 minutes rather than retried every render');
unset($GLOBALS['ics_sync_fail']);
check_ics_sync(calendar_ics_list(50)[0]['host']==='calendar.example.com'
    && !str_contains(json_encode(calendar_ics_list(50)),'SECRET'),
    'No bearer token appears in owner JSON');
check_ics_sync(calendar_ics_list(50)[0]['ics_id']===$id && calendar_ics_list(51)[0]['ics_id']===$other,
    'Subscriptions and cached bodies are owner-scoped');
$second=calendar_ics_create($owner,['name'=>'Second','color'=>'blue',
    'url'=>'https://calendar.example.org/second.ics']);
$third=calendar_ics_create($owner,['name'=>'Third','color'=>'purple',
    'url'=>'https://calendar.example.org/third.ics']);
check_ics_sync(calendar_ics_due_count($owner)===2,'Two new sources are queued');
$one=calendar_ics_sync_due($owner);
check_ics_sync($one['attempted']===1 && $one['remaining']===1,
    'Auto API bounds work to one feed per request');
$two=calendar_ics_sync_due($owner);
check_ics_sync($two['attempted']===1 && $two['remaining']===0,
    'A second request finishes the queue');
echo "RESULT: PASS ICS Dev4 sync tests".PHP_EOL;
