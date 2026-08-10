<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$tempDir = sys_get_temp_dir() . '/rss-v17h-r4-' . bin2hex(random_bytes(5));
if (!mkdir($tempDir, 0700, true) && !is_dir($tempDir)) {
    fwrite(STDERR, "Could not create temp directory.\n");
    exit(1);
}

define('APP_HOLIDAY_CACHE_PATH', $tempDir . '/japanese_holidays.json');
define('APP_HOLIDAY_LOCK_PATH', $tempDir . '/japanese_holidays.lock');
define('APP_HOLIDAY_CSV_URL', 'https://www8.cao.go.jp/chosei/shukujitsu/syukujitsu.csv');
define('APP_HOLIDAY_CACHE_DAYS', 60);
define('APP_HOLIDAY_TIMEOUT_MS', 5000);
define('APP_HOLIDAY_CSV_MAX_BYTES', 524288);
define('APP_HOLIDAY_CACHE_MAX_BYTES', 1048576);
putenv('APP_ENV=testing');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/validation.php';
require_once $root . '/app/feed/feed_http_headers.php';
require_once $root . '/app/http_fetch.php';
require_once $root . '/app/holiday.php';

$checks = 0;
$failures = [];
function v17h_r4_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) {
        $failures[] = $message;
    }
}

function v17h_r4_csv(): string
{
    $rows = [
        ['国民の祝日・休日月日', '国民の祝日・休日名称'],
        ['2026/1/1', '元日'], ['2026/1/12', '成人の日'], ['2026/2/11', '建国記念の日'],
        ['2026/2/23', '天皇誕生日'], ['2026/3/20', '春分の日'], ['2026/4/29', '昭和の日'],
        ['2026/5/3', '憲法記念日'], ['2026/5/4', 'みどりの日'], ['2026/5/5', 'こどもの日'],
        ['2026/5/6', '休日'], ['2026/7/20', '海の日'], ['2026/8/11', '山の日'],
        ['2026/9/21', '敬老の日'], ['2026/9/22', '休日'], ['2026/9/23', '秋分の日'],
        ['2026/10/12', 'スポーツの日'], ['2026/11/3', '文化の日'], ['2026/11/23', '勤労感謝の日'],
        ['2027/1/1', '元日'], ['2027/1/11', '成人の日'], ['2027/2/11', '建国記念の日'],
        ['2027/2/23', '天皇誕生日'], ['2027/3/21', '春分の日'], ['2027/3/22', '休日'],
    ];
    $stream = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($stream, $row);
    }
    rewind($stream);
    $csv = stream_get_contents($stream);
    fclose($stream);
    return is_string($csv) ? $csv : '';
}

$snapshot = japanese_holiday_snapshot();
v17h_r4_check(($snapshot['2026-08-11'] ?? '') === '山の日', 'snapshot contains 2026 Mountain Day');
v17h_r4_check(($snapshot['2026-09-22'] ?? '') === '休日', 'snapshot contains 2026 holiday-law bridging day');
v17h_r4_check(($snapshot['2027-03-22'] ?? '') === '休日', 'snapshot contains 2027 substitute holiday');

$initial = japanese_holiday_current_data();
v17h_r4_check($initial['source'] === 'snapshot' && $initial['refresh_due'] === true, 'snapshot is used immediately when cache is absent');

$parsed = japanese_holiday_parse_csv(v17h_r4_csv());
v17h_r4_check(is_array($parsed) && count($parsed) >= 20, 'official-style CSV is parsed into a bounded holiday map');
v17h_r4_check(($parsed['2026-08-11'] ?? '') === '山の日', 'CSV parser normalizes date keys');
v17h_r4_check(japanese_holiday_parse_csv("date,name\n2026/1/1,元日\n") === null, 'too-small CSV is rejected before cache replacement');

if (function_exists('mb_convert_encoding')) {
    $sjis = mb_convert_encoding(v17h_r4_csv(), 'SJIS-win', 'UTF-8');
    $parsedSjis = japanese_holiday_parse_csv($sjis);
    v17h_r4_check(is_array($parsedSjis) && ($parsedSjis['2026-08-11'] ?? '') === '山の日', 'Shift_JIS CSV is converted safely to UTF-8');
}

$fetchCalls = 0;
$refresh = japanese_holiday_refresh(static function (string $url) use (&$fetchCalls): array {
    $fetchCalls++;
    return ['ok' => true, 'url' => $url, 'status' => 200, 'body' => v17h_r4_csv(), 'error_code' => ''];
});
v17h_r4_check($refresh['refreshed'] === true && $fetchCalls === 1, 'stale/missing cache is refreshed once');
v17h_r4_check(is_file(APP_HOLIDAY_CACHE_PATH), 'holiday refresh writes a local JSON cache');

$cache = japanese_holiday_cache_read();
v17h_r4_check(is_array($cache) && japanese_holiday_cache_is_fresh($cache), 'new cache is considered fresh for 60 days');
v17h_r4_check(($cache['source'] ?? '') === APP_HOLIDAY_CSV_URL, 'cache records the configured source URL');
$current = japanese_holiday_current_data();
v17h_r4_check($current['source'] === 'cache' && $current['refresh_due'] === false, 'fresh cache is preferred over snapshot without network access');

$fetchCalls = 0;
$second = japanese_holiday_refresh(static function (string $url) use (&$fetchCalls): array {
    $fetchCalls++;
    return ['ok' => false, 'url' => $url, 'status' => 500, 'body' => '', 'error_code' => 'test'];
});
v17h_r4_check($second['reason'] === 'fresh' && $fetchCalls === 0, 'fresh cache prevents unnecessary outbound refresh');

$raw = json_decode((string) file_get_contents(APP_HOLIDAY_CACHE_PATH), true);
$raw['updated_at'] = '2020-01-01T00:00:00+00:00';
file_put_contents(APP_HOLIDAY_CACHE_PATH, json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$beforeFailure = (string) file_get_contents(APP_HOLIDAY_CACHE_PATH);
$failed = japanese_holiday_refresh(static function (string $url): array {
    return ['ok' => true, 'url' => $url, 'status' => 200, 'body' => "broken,csv\n", 'error_code' => ''];
});
$afterFailure = (string) file_get_contents(APP_HOLIDAY_CACHE_PATH);
v17h_r4_check($failed['reason'] === 'invalid_csv', 'malformed refresh payload is rejected');
v17h_r4_check($beforeFailure === $afterFailure, 'failed refresh never destroys the previous usable cache');

$resolver = static fn(string $host): array => ['93.184.216.34'];
$transportCalled = false;
$insecure = japanese_holiday_safe_fetch('http://example.com/holidays.csv', $resolver, static function (array $request) use (&$transportCalled): array {
    $transportCalled = true;
    return ['ok' => true, 'status' => 200, 'body' => v17h_r4_csv(), 'location' => '', 'etag' => null, 'last_modified' => null, 'retry_after' => null, 'error_code' => '', 'error_message' => ''];
});
v17h_r4_check($insecure['ok'] === false && $insecure['error_code'] === 'https_required' && $transportCalled === false, 'holiday fetch refuses non-HTTPS configured targets before transport');

$redirectCalls = 0;
$redirect = japanese_holiday_safe_fetch('https://example.com/holidays.csv', $resolver, static function (array $request) use (&$redirectCalls): array {
    $redirectCalls++;
    return ['ok' => true, 'status' => 302, 'body' => '', 'location' => 'http://example.com/down.csv', 'etag' => null, 'last_modified' => null, 'retry_after' => null, 'error_code' => '', 'error_message' => ''];
});
v17h_r4_check($redirect['ok'] === false && $redirect['error_code'] === 'https_required' && $redirectCalls === 1, 'holiday fetch refuses redirects that downgrade HTTPS');

@unlink(APP_HOLIDAY_CACHE_PATH);
@unlink(APP_HOLIDAY_LOCK_PATH);
@rmdir($tempDir);

if ($failures !== []) {
    echo count($failures) . "/{$checks} V1.7-H/R4 checks failed.\n";
    exit(1);
}
echo "All {$checks} V1.7-H/R4 Holiday checks passed.\n";
