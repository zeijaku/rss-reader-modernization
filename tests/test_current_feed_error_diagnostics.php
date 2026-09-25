<?php

declare(strict_types=1);

function api_error(string $code, string $message, int $status): array
{
    return ['status' => $status, 'body' => ['ok' => false, 'error' => ['code' => $code, 'message' => $message]]];
}

require_once dirname(__DIR__) . '/app/feed/feed_error.php';

$results = [];
$check = static function (bool $condition, string $message) use (&$results): void {
    $results[] = [$condition, $message];
};

$cases = [
    ['fetch', 'invalid_url', 0, 'upstream_blocked'],
    ['fetch', 'non_public_address', 0, 'upstream_blocked'],
    ['fetch', 'response_too_large', 200, 'upstream_blocked'],
    ['fetch', 'dns_failed', 0, 'rss_connection_failed'],
    ['fetch', 'tls_error', 0, 'rss_connection_failed'],
    ['fetch', 'transport_error', 0, 'rss_connection_failed'],
    ['fetch', 'timeout', 0, 'rss_temporarily_unavailable'],
    ['fetch', 'empty_response', 200, 'rss_temporarily_unavailable'],
    ['fetch', 'retry_backoff', 503, 'rss_temporarily_unavailable'],
    ['fetch', 'http_status', 403, 'rss_http_error'],
    ['fetch', 'http_status', 404, 'rss_http_error'],
    ['fetch', 'http_status', 429, 'rss_http_error'],
    ['fetch', 'http_status', 503, 'rss_http_error'],
    ['parse', 'parse_error', 200, 'invalid_feed'],
    ['fetch', 'curl_unavailable', 0, 'rss_server_unavailable'],
    ['server', 'database_error', 0, 'rss_server_unavailable'],
];

$categories = [];
foreach ($cases as [$type, $internal, $status, $expected]) {
    $details = feed_public_error_details($type, $internal, $status);
    $categories[$details['code']] = true;
    $check($details['code'] === $expected, "{$internal} maps to {$expected}");
    $check($details['message'] !== '', "{$internal} has an actionable public message");
    $check($details['status'] >= 400 && $details['status'] <= 599, "{$internal} has a bounded HTTP status");
}

$expectedCategories = [
    'invalid_feed',
    'rss_connection_failed',
    'rss_http_error',
    'rss_server_unavailable',
    'rss_temporarily_unavailable',
    'upstream_blocked',
];
$actualCategories = array_keys($categories);
sort($actualCategories);
$check($actualCategories === $expectedCategories, 'RSS public diagnostics use exactly six categories');

$forbidden = 'raw-curl-provider-message';
$unknown = feed_public_error_details('fetch', 'unknown_' . $forbidden, 0);
$check(!str_contains($unknown['message'], $forbidden), 'unknown transport text is never copied into the public message');
$check(str_contains(feed_public_error_details('fetch', 'http_status', 429)['message'], '429'), 'rate limiting is identified safely');
$check(str_contains(feed_public_error_details('fetch', 'http_status', 404)['message'], '404'), 'missing Feed HTTP status is identified safely');

$passed = 0;
foreach ($results as [$ok, $message]) {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if ($ok) {
        $passed++;
    }
}
$failed = count($results) - $passed;
echo "RESULT: PASS {$passed} / FAIL {$failed} / SKIP 0" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
