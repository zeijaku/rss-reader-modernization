<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('APP_HTTP_CONNECT_TIMEOUT_MS=1500');
putenv('APP_HTTP_TIMEOUT_MS=5000');
putenv('APP_HTTP_MAX_REDIRECTS=3');
putenv('APP_HTTP_MAX_BYTES=1048576');
putenv('APP_HTTP_USER_AGENT=iGuguru-Test/1.0');
require $root . '/app/common/common_conf.php';
require $root . '/app/validation.php';
require $root . '/app/http_fetch.php';

$checks = 0;
$failures = [];
function m1f_http_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) {
        $failures[] = $message;
    }
}

m1f_http_check(feed_clean_etag('"abc123"') === '"abc123"', 'strong ETag is accepted');
m1f_http_check(feed_clean_etag('W/"abc123"') === 'W/"abc123"', 'weak ETag is accepted');
m1f_http_check(feed_clean_etag("\"bad\r\nInjected: x\"") === null, 'ETag containing CR/LF is rejected');
m1f_http_check(feed_clean_etag('abc123') === null, 'unquoted ETag is rejected');
m1f_http_check(feed_clean_etag(str_repeat('x', 513)) === null, 'overlong ETag is rejected');
m1f_http_check(
    feed_clean_last_modified('Sat, 01 Aug 2026 06:00:00 GMT') === 'Sat, 01 Aug 2026 06:00:00 GMT',
    'Last-Modified is normalized to HTTP-date'
);
m1f_http_check(feed_clean_last_modified("bad\r\ndate") === null, 'Last-Modified containing CR/LF is rejected');
m1f_http_check(feed_clean_last_modified('not-a-date') === null, 'invalid Last-Modified is rejected');
m1f_http_check(feed_clean_last_modified('tomorrow') === null, 'non-HTTP date text is rejected even when strtotime can parse it');

$validators = [
    'resource_url' => 'https://feed.example/final',
    'etag' => 'W/"v1"',
    'last_modified' => 'Sat, 01 Aug 2026 06:00:00 GMT',
    'X-Unsafe' => 'not allowed',
];
$headers = feed_conditional_request_headers($validators, 'https://feed.example/final');
m1f_http_check($headers === [
    'If-None-Match: W/"v1"',
    'If-Modified-Since: Sat, 01 Aug 2026 06:00:00 GMT',
], 'only the two allowed conditional headers are created');
m1f_http_check(feed_conditional_request_headers($validators, 'https://feed.example/other') === [], 'validators are not sent to a different URL');

$resolver = static fn(string $host): array => ['93.184.216.34'];
$calls = [];
$notModifiedTransport = static function (array $request) use (&$calls): array {
    $calls[] = $request;
    return [
        'ok' => true,
        'status' => 304,
        'body' => '',
        'location' => null,
        'etag' => '"v1"',
        'last_modified' => 'Sat, 01 Aug 2026 06:00:00 GMT',
        'error_code' => '',
        'error_message' => '',
    ];
};
$result = app_safe_http_fetch('https://feed.example/final', $resolver, $notModifiedTransport, $validators);
m1f_http_check(($result['ok'] ?? false) === true && ($result['not_modified'] ?? false) === true && ($result['status'] ?? 0) === 304, 'HTTP 304 is accepted only for a conditional request');
m1f_http_check(($calls[0]['request_headers'] ?? []) === $headers, 'conditional headers reach the validated transport request');
m1f_http_check(($result['etag'] ?? null) === '"v1"', 'ETag from HTTP 304 is returned to cache layer');

$result = app_safe_http_fetch('https://feed.example/final', $resolver, $notModifiedTransport);
m1f_http_check(($result['ok'] ?? true) === false && ($result['error_code'] ?? '') === 'unexpected_not_modified', 'HTTP 304 without validators is rejected');

$redirectCalls = [];
$redirectTransport = static function (array $request) use (&$redirectCalls): array {
    $redirectCalls[] = $request;
    if ($request['url'] === 'https://feed.example/start') {
        return ['ok' => true, 'status' => 302, 'body' => '', 'location' => '/final', 'etag' => '"redirect-etag"', 'last_modified' => null, 'error_code' => '', 'error_message' => ''];
    }
    return ['ok' => true, 'status' => 304, 'body' => '', 'location' => null, 'etag' => '"v1"', 'last_modified' => null, 'error_code' => '', 'error_message' => ''];
};
$result = app_safe_http_fetch('https://feed.example/start', $resolver, $redirectTransport, $validators);
m1f_http_check(($result['ok'] ?? false) === true && ($result['url'] ?? '') === 'https://feed.example/final', 'conditional request follows a revalidated redirect safely');
m1f_http_check(($redirectCalls[0]['request_headers'] ?? []) === [], 'validator is not sent to the original URL when it belongs to the prior effective URL');
m1f_http_check(($redirectCalls[1]['request_headers'] ?? []) === $headers, 'validator is sent after redirect only to the exact prior effective URL');

$changedCalls = [];
$changedTransport = static function (array $request) use (&$changedCalls): array {
    $changedCalls[] = $request;
    if ($request['url'] === 'https://feed.example/start') {
        return ['ok' => true, 'status' => 302, 'body' => '', 'location' => '/new-final', 'etag' => null, 'last_modified' => null, 'error_code' => '', 'error_message' => ''];
    }
    return ['ok' => true, 'status' => 200, 'body' => '<rss>new</rss>', 'location' => null, 'etag' => '"v2"', 'last_modified' => null, 'error_code' => '', 'error_message' => ''];
};
$result = app_safe_http_fetch('https://feed.example/start', $resolver, $changedTransport, $validators);
m1f_http_check(($result['ok'] ?? false) === true && ($result['status'] ?? 0) === 200, 'changed redirect target falls back to a normal HTTP 200 fetch');
m1f_http_check(($changedCalls[0]['request_headers'] ?? []) === [] && ($changedCalls[1]['request_headers'] ?? []) === [], 'validator is not leaked to a changed redirect target');

$invalidHeaderTransport = static fn(array $request): array => [
    'ok' => true,
    'status' => 200,
    'body' => '<rss>ok</rss>',
    'location' => null,
    'etag' => "\"bad\r\nX: 1\"",
    'last_modified' => 'not-a-date',
    'error_code' => '',
    'error_message' => '',
];
$result = app_safe_http_fetch('https://feed.example/final', $resolver, $invalidHeaderTransport);
m1f_http_check(($result['ok'] ?? false) === true && array_key_exists('etag', $result) && $result['etag'] === null && array_key_exists('last_modified', $result) && $result['last_modified'] === null, 'invalid response validators are discarded without failing a valid Feed response');

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d/%d M1-F HTTP checks failed.\n", count($failures), $checks));
    exit(1);
}

echo "All {$checks} executable M1-F HTTP checks passed.\n";
