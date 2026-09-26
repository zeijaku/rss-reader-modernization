<?php

declare(strict_types=1);

if (!defined('APP_ENV')) {
    define('APP_ENV', 'testing');
}
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once APP_ROOT . '/app/common/common_conf.php';
require_once APP_ROOT . '/app/validation.php';
require_once APP_ROOT . '/app/url_normalizer.php';
require_once APP_ROOT . '/app/reader/reader_full_text.php';

$failures = [];
$checks = 0;

function v138b_check(bool $condition, string $label): void
{
    global $failures, $checks;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $label . "\n";
    if (!$condition) {
        $failures[] = $label;
    }
}

/** @param string $path */
function v138b_remove_tree(string $path): void
{
    if (!is_dir($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    $entries = scandir($path);
    if (is_array($entries)) {
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            v138b_remove_tree($path . DIRECTORY_SEPARATOR . $entry);
        }
    }
    @rmdir($path);
}

v138b_check(
    reader_full_text_fetch_url('https://article.example/read?id=7&utm_source=rss#section')
        === 'https://article.example/read?id=7',
    'Full Text target removes tracking parameters and browser fragment'
);
v138b_check(
    reader_full_text_fetch_url('https://user:pass@article.example/read') === null,
    'Full Text target rejects userinfo'
);
v138b_check(
    reader_full_text_fetch_url('javascript:alert(1)') === null,
    'Full Text target rejects non-HTTP schemes'
);
v138b_check(
    reader_full_text_content_type_allowed('text/html; charset=UTF-8'),
    'HTML Content-Type with charset is accepted'
);
v138b_check(
    reader_full_text_content_type_allowed('application/xhtml+xml'),
    'XHTML Content-Type is accepted'
);
v138b_check(
    !reader_full_text_content_type_allowed('application/json'),
    'non-HTML Content-Type is rejected'
);

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rss-reader-v138b-' . bin2hex(random_bytes(6));
if (!mkdir($tmp, 0700, true) && !is_dir($tmp)) {
    throw new RuntimeException('Unable to create temporary Reader cache directory.');
}

$now = 1000;
$clock = static function () use (&$now): int {
    return $now;
};
$cache = new ReaderFullTextCache($tmp, 5, 60, 4096, $clock);
$service = new ReaderFullTextService($cache, true);

$transportCalls = 0;
$GLOBALS['app_http_fetch_test_resolver'] = static fn (string $host): array => ['93.184.216.34'];
$GLOBALS['app_http_fetch_test_transport'] = static function (array $request) use (&$transportCalls): array {
    $transportCalls++;
    return [
        'ok' => true,
        'status' => 200,
        'body' => '<!doctype html><html><body><main>Article body</main></body></html>',
        'location' => null,
        'etag' => null,
        'last_modified' => null,
        'retry_after' => null,
        'content_type' => 'text/html; charset=UTF-8',
        'error_code' => '',
        'error_message' => '',
    ];
};

$first = $service->load('https://article.example/read?id=1&utm_medium=rss#top');
v138b_check(($first['ok'] ?? false) === true, 'Full Text service accepts safe public HTML');
v138b_check(($first['cache_status'] ?? '') === 'miss', 'first Full Text request is a cache miss');
v138b_check(($first['content_type'] ?? '') === 'text/html', 'Full Text normalizes HTML Content-Type');
v138b_check($transportCalls === 1, 'first Full Text request performs one outbound fetch');

$second = $service->load('https://article.example/read?id=1');
v138b_check(($second['ok'] ?? false) === true && ($second['cache_status'] ?? '') === 'hit', 'fresh article is served from cache');
v138b_check($transportCalls === 1, 'fresh cache hit does not perform another outbound fetch');

$now = 1010;
$GLOBALS['app_http_fetch_test_transport'] = static function (array $request) use (&$transportCalls): array {
    $transportCalls++;
    return [
        'ok' => false,
        'status' => 0,
        'body' => '',
        'location' => null,
        'error_code' => 'timeout',
        'error_message' => 'timeout',
    ];
};
$stale = $service->load('https://article.example/read?id=1');
v138b_check(
    ($stale['ok'] ?? false) === true && ($stale['cache_status'] ?? '') === 'stale' && ($stale['stale'] ?? false) === true,
    'stale article is used when refresh fails'
);
v138b_check(
    ($stale['stale_reason'] ?? '') === 'timeout',
    'stale cache records the internal refresh failure category'
);

$GLOBALS['app_http_fetch_test_transport'] = static fn (array $request): array => [
    'ok' => true,
    'status' => 200,
    'body' => '{"not":"html"}',
    'location' => null,
    'etag' => null,
    'last_modified' => null,
    'retry_after' => null,
    'content_type' => 'application/json',
    'error_code' => '',
    'error_message' => '',
];
$unsupported = $service->load('https://article.example/json');
v138b_check(
    ($unsupported['ok'] ?? true) === false && ($unsupported['error_code'] ?? '') === 'unsupported_content_type',
    'Full Text rejects non-HTML response types'
);

$GLOBALS['app_http_fetch_test_transport'] = static fn (array $request): array => [
    'ok' => true,
    'status' => 200,
    'body' => '<html><body>No header</body></html>',
    'location' => null,
    'etag' => null,
    'last_modified' => null,
    'retry_after' => null,
    'error_code' => '',
    'error_message' => '',
];
$missingType = $service->load('https://article.example/no-content-type');
v138b_check(
    ($missingType['ok'] ?? true) === false && ($missingType['error_code'] ?? '') === 'unsupported_content_type',
    'Full Text fails closed when Content-Type is missing'
);

$GLOBALS['app_http_fetch_test_resolver'] = static fn (string $host): array => ['93.184.216.34'];
$GLOBALS['app_http_fetch_test_transport'] = static fn (array $request): array => [
    'ok' => true,
    'status' => 302,
    'body' => '',
    'location' => 'http://169.254.169.254/latest/meta-data/',
    'etag' => null,
    'last_modified' => null,
    'retry_after' => null,
    'content_type' => 'text/html',
    'error_code' => '',
    'error_message' => '',
];
$privateRedirect = $service->load('https://article.example/redirect-private');
v138b_check(
    ($privateRedirect['ok'] ?? true) === false && ($privateRedirect['error_code'] ?? '') === 'non_public_address',
    'redirect to private/link-local address is rejected before the next fetch'
);

$GLOBALS['app_http_fetch_test_resolver'] = static fn (string $host): array => ['93.184.216.34'];
$GLOBALS['app_http_fetch_test_transport'] = static fn (array $request): array => [
    'ok' => true,
    'status' => 200,
    'body' => '<html>metadata</html>',
    'location' => null,
    'etag' => null,
    'last_modified' => null,
    'retry_after' => null,
    'content_type' => 'text/html; charset=utf-8',
    'error_code' => '',
    'error_message' => '',
];
$sharedFetch = app_safe_http_fetch('https://article.example/content-type');
v138b_check(
    ($sharedFetch['ok'] ?? false) === true && ($sharedFetch['content_type'] ?? '') === 'text/html; charset=utf-8',
    'shared safe HTTP fetch exposes Content-Type without weakening its boundary'
);

$GLOBALS['app_http_fetch_test_transport'] = static fn (array $request): array => [
    'ok' => false,
    'status' => 200,
    'body' => '',
    'location' => null,
    'error_code' => 'response_too_large',
    'error_message' => 'too large',
];
$tooLarge = $service->load('https://article.example/large');
v138b_check(
    ($tooLarge['ok'] ?? true) === false && ($tooLarge['error_code'] ?? '') === 'response_too_large',
    'Full Text preserves the shared response-size failure'
);

unset($GLOBALS['app_http_fetch_test_resolver'], $GLOBALS['app_http_fetch_test_transport']);
v138b_remove_tree($tmp);

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d/%d V1.38-B Reader Full Text checks failed.\n", count($failures), $checks));
    exit(1);
}

echo "V1.38-B Reader Full Text checks: {$checks} passed.\n";
