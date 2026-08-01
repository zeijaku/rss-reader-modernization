<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/validation.php';
require_once $root . '/app/feed/feed_retry.php';
require_once $root . '/app/http_fetch.php';

$checks = 0;
$failures = [];
function m1g_http_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) {
        $failures[] = $message;
    }
}

m1g_http_check(feed_clean_retry_after('300') === '300', 'Retry-After delta-seconds is accepted');
m1g_http_check(feed_clean_retry_after(' 300 ') === '300', 'Retry-After whitespace is trimmed');
m1g_http_check(feed_clean_retry_after('Sat, 01 Aug 2026 10:30:00 GMT') === 'Sat, 01 Aug 2026 10:30:00 GMT', 'Retry-After HTTP-date is accepted');
m1g_http_check(feed_clean_retry_after('-1') === null, 'negative Retry-After is rejected');
m1g_http_check(feed_clean_retry_after('1.5') === null, 'fractional Retry-After is rejected');
m1g_http_check(feed_clean_retry_after("300\r\nX-Test: yes") === null, 'Retry-After CR/LF is rejected');
m1g_http_check(feed_clean_retry_after("3\0") === null, 'Retry-After NUL is rejected');
m1g_http_check(feed_clean_retry_after(str_repeat('9', 129)) === null, 'overlong Retry-After is rejected');
m1g_http_check(feed_clean_retry_after('tomorrow') === null, 'non-HTTP date text is rejected');

$now = strtotime('Sat, 01 Aug 2026 10:00:00 GMT');
m1g_http_check(feed_retry_after_seconds('300', $now, 3600) === 300, 'delta-seconds converts to seconds');
m1g_http_check(feed_retry_after_seconds('Sat, 01 Aug 2026 10:30:00 GMT', $now, 3600) === 1800, 'HTTP-date converts to future delay');
m1g_http_check(feed_retry_after_seconds('Sat, 01 Aug 2026 09:30:00 GMT', $now, 3600) === null, 'past Retry-After date is ignored');
m1g_http_check(feed_retry_after_seconds('999999', $now, 3600) === 3600, 'Retry-After is capped by configured maximum');

m1g_http_check(feed_failure_kind('fetch', ['error_code' => 'timeout', 'status' => 0]) === 'transient', 'timeout is transient');
m1g_http_check(feed_failure_kind('fetch', ['error_code' => 'dns_failed', 'status' => 0]) === 'transient', 'DNS failure is transient');
m1g_http_check(feed_failure_kind('fetch', ['error_code' => 'http_status', 'status' => 429]) === 'transient', 'HTTP 429 is transient');
m1g_http_check(feed_failure_kind('fetch', ['error_code' => 'http_status', 'status' => 503]) === 'transient', 'HTTP 503 is transient');
m1g_http_check(feed_failure_kind('fetch', ['error_code' => 'http_status', 'status' => 404]) === 'permanent', 'HTTP 404 is permanent');
m1g_http_check(feed_failure_kind('fetch', ['error_code' => 'tls_error', 'status' => 0]) === 'security', 'TLS failure is security-sensitive');
m1g_http_check(feed_failure_kind('fetch', ['error_code' => 'non_public_address', 'status' => 0]) === 'security', 'private address rejection is security-sensitive');
m1g_http_check(feed_failure_kind('fetch', ['error_code' => 'response_too_large', 'status' => 200]) === 'security', 'oversized response is not hidden by stale data');
m1g_http_check(feed_failure_kind('parse', []) === 'transient', 'temporary parse failure can use bounded stale data');

m1g_http_check(feed_retry_delay_seconds(1, 'transient', [], 1000, 3600) === 60, 'first transient failure waits 60 seconds');
m1g_http_check(feed_retry_delay_seconds(2, 'transient', [], 1000, 3600) === 300, 'second transient failure waits 5 minutes');
m1g_http_check(feed_retry_delay_seconds(3, 'transient', [], 1000, 3600) === 900, 'third transient failure waits 15 minutes');
m1g_http_check(feed_retry_delay_seconds(4, 'transient', [], 1000, 3600) === 3600, 'fourth transient failure reaches one-hour cap');
m1g_http_check(feed_retry_delay_seconds(9, 'transient', [], 1000, 600) === 600, 'backoff respects a smaller configured cap');
m1g_http_check(feed_retry_delay_seconds(1, 'permanent', [], 1000, 3600) === 900, 'permanent failure uses a longer fixed wait');
m1g_http_check(feed_retry_delay_seconds(1, 'security', [], 1000, 3600) === 0, 'security failure is not hidden behind backoff');
m1g_http_check(feed_retry_delay_seconds(2, 'transient', ['status' => 503, 'retry_after' => '120'], 1000, 3600) === 120, 'HTTP 503 Retry-After overrides local backoff');
m1g_http_check(feed_retry_delay_seconds(2, 'transient', ['status' => 429, 'retry_after' => '99999'], 1000, 3600) === 3600, 'HTTP 429 Retry-After is capped');
m1g_http_check(feed_retry_delay_seconds(2, 'transient', ['status' => 500, 'retry_after' => '120'], 1000, 3600) === 300, 'Retry-After is ignored for unrelated HTTP status');

$resolver = static fn (string $host): array => ['93.184.216.34'];
$seen = [];
$transport = static function (array $request) use (&$seen): array {
    $seen[] = $request;
    return [
        'ok' => true,
        'status' => 503,
        'body' => '',
        'location' => null,
        'etag' => null,
        'last_modified' => null,
        'retry_after' => '300',
        'error_code' => '',
        'error_message' => '',
    ];
};
$result = app_safe_http_fetch('https://feed.example.test/rss.xml', $resolver, $transport);
m1g_http_check(($result['ok'] ?? true) === false && ($result['status'] ?? 0) === 503, 'HTTP 503 remains a controlled fetch failure');
m1g_http_check(($result['retry_after'] ?? null) === '300', 'validated Retry-After reaches the retry layer');
m1g_http_check(count($seen) === 1, 'Retry-After handling does not perform an immediate retry');

$redirectCalls = 0;
$redirectTransport = static function (array $request) use (&$redirectCalls): array {
    $redirectCalls++;
    if ($redirectCalls === 1) {
        return [
            'ok' => true, 'status' => 302, 'body' => '', 'location' => 'https://cdn.example.test/feed.xml',
            'etag' => null, 'last_modified' => null, 'retry_after' => '600', 'error_code' => '', 'error_message' => '',
        ];
    }
    return [
        'ok' => true, 'status' => 503, 'body' => '', 'location' => null,
        'etag' => null, 'last_modified' => null, 'retry_after' => null, 'error_code' => '', 'error_message' => '',
    ];
};
$redirectResult = app_safe_http_fetch('https://feed.example.test/rss.xml', $resolver, $redirectTransport);
m1g_http_check(array_key_exists('retry_after', $redirectResult) && $redirectResult['retry_after'] === null, 'Retry-After from an intermediate redirect is not reused');
m1g_http_check($redirectCalls === 2, 'redirect remains manually followed and revalidated');

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d of %d M1-G HTTP/retry checks failed.\n", count($failures), $checks));
    exit(1);
}
echo sprintf("All %d executable M1-G HTTP/retry checks passed.\n", $checks);
