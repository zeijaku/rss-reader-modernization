<?php
declare(strict_types=1);

/** Dev1 regression: validates subscription CRUD, isolation, URL secrecy, fetch limits. */
function check(bool $ok, string $message): void
{
    if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
    echo "PASS: $message\n";
}
function app_validate_feed_url(mixed $value): ?string
{
    return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false ? $value : null;
}
function calendar_source_validate_name(mixed $value): ?string
{
    return is_string($value) && trim($value) !== '' && strlen($value) <= 40 ? trim($value) : null;
}
function calendar_source_validate_color(mixed $value): ?string
{
    return is_string($value) && in_array($value, ['blue','green','purple','yellow','red'], true) ? $value : null;
}
function db_table_identifier(string $logical): string
{
    check($logical === 'calendar_ics_subscription', 'ICS DB table identifier only');
    return '"test_calendar_ics_subscription"';
}
function app_now(): string { return date('Y-m-d H:i:s'); }
function conn_db(): PDO { return $GLOBALS['test_ics_pdo']; }
function app_curl_single_hop(array $request): array
{
    return ['ok' => true, 'status' => 200, 'body' => $GLOBALS['test_ics_body'], 'error_code' => ''];
}
function app_safe_http_fetch(
    string $url, ?callable $resolver = null, ?callable $transport = null,
    array $validators = [], array $requestOptions = []
): array {
    check(($requestOptions['max_bytes'] ?? 0) === 1048576, 'ICS fetch imposes 1MiB limit');
    check(str_starts_with($url, 'https://'), 'ICS fetch starts with HTTPS');
    return $transport(['url' => $GLOBALS['test_ics_hop'] ?? $url]);
}
require dirname(__DIR__) . '/app/calendar_ics.php';

check(calendar_ics_validate_url('http://example.com/calendar.ics') === null, 'HTTP rejected');
check(calendar_ics_validate_url('https://u:p@example.com/a.ics') === null, 'URL credentials rejected');
check(calendar_ics_validate_url('https://example.com/a.ics#fragment') === null, 'URL fragment rejected');
check(calendar_ics_validate_url('https://example.com:8443/a.ics') === null, 'nonstandard ports rejected');
check(calendar_ics_validate_url('https://example.com/private.ics?secret=abc') !== null, 'opaque query preserved');
check(!calendar_ics_envelope_valid('<html>not calendar</html>'), 'HTML response rejected');
check(calendar_ics_envelope_valid("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nEND:VCALENDAR\r\n"), 'iCalendar envelope accepted');

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(
    'CREATE TABLE test_calendar_ics_subscription (ics_id INTEGER PRIMARY KEY AUTOINCREMENT, ics_owner INTEGER NOT NULL,'
    . ' ics_name TEXT NOT NULL, ics_color TEXT NOT NULL, ics_url TEXT NOT NULL, ics_raw TEXT NULL,'
    . ' ics_status TEXT NOT NULL, ics_error_code TEXT DEFAULT \'\', ics_last_checked_at TEXT NULL,'
    . ' ics_last_success_at TEXT NULL, ics_created_at TEXT NOT NULL, ics_updated_at TEXT NOT NULL)'
);
$GLOBALS['test_ics_pdo'] = $pdo;
$one = ['name'=>'仕事','color'=>'green','url'=>'https://calendar.example.com/private.ics?secret=TOKEN123'];
$id = calendar_ics_create(123, $one);
check($id > 0, 'create owner-scoped ICS subscription');
check(calendar_ics_list(124) === [], 'other owner cannot list subscription');
$public = json_encode(calendar_ics_list(123));
check(!str_contains((string)$public, 'TOKEN123') && !str_contains((string)$public, '/private.ics'),
    'API metadata never exposes private path or bearer URL token');
check(calendar_ics_owned($pdo, 124, $id) === null, 'other owner cannot access URL');
check(!calendar_ics_delete(124, $id), 'other owner cannot delete');
$GLOBALS['test_ics_body'] = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nEND:VCALENDAR\r\n";
check(calendar_ics_refresh(123, $id), 'manual refresh completes');
$after = calendar_ics_list(123)[0];
check($after['status'] === 'ok' && $after['cached_bytes'] > 0, 'successful ICS body cached privately');
$pdo->exec("UPDATE test_calendar_ics_subscription SET ics_last_checked_at='1970-01-01 00:00:00'");
$GLOBALS['test_ics_hop'] = 'http://internal.local/redirect';
check(calendar_ics_refresh(123, $id), 'unsafe redirected request records failure');
$after = calendar_ics_list(123)[0];
check($after['status'] === 'error' && $after['cached_bytes'] > 0, 'failed fetch retains previous safe cached body');
unset($GLOBALS['test_ics_hop']);
check(calendar_ics_update(123, $id, '仕事2', 'blue', 'https://example.com/new.ics?token=NEWSECRET'),
    'updating name, color and private URL succeeds');
$after = calendar_ics_list(123)[0];
check($after['status'] === 'pending' && $after['cached_bytes'] === 0, 'URL change invalidates old cached body');
check(!str_contains((string)json_encode($after), 'NEWSECRET'), 'updated token not disclosed');
check(calendar_ics_delete(123, $id) && calendar_ics_list(123) === [], 'delete removes URL and cache');
echo "RESULT: PASS Dev1 ICS subscription checks\n";
