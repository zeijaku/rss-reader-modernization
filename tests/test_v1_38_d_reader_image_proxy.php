<?php

declare(strict_types=1);

putenv('APP_HASH_KEY=' . str_repeat('a', 64));

if (!defined('APP_ENV')) {
    define('APP_ENV', 'testing');
}
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once APP_ROOT . '/app/common/common_conf.php';
require_once APP_ROOT . '/app/validation.php';
require_once APP_ROOT . '/app/url_normalizer.php';
require_once APP_ROOT . '/app/feed/feed_http_headers.php';
require_once APP_ROOT . '/app/http_fetch.php';
require_once APP_ROOT . '/app/reader/reader_image_proxy.php';

$failures = [];
$checks = 0;

function v138d_check(bool $condition, string $label): void
{
    global $failures, $checks;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $label . "\n";
    if (!$condition) {
        $failures[] = $label;
    }
}

function v138d_remove_tree(string $path): void
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
            v138d_remove_tree($path . DIRECTORY_SEPARATOR . $entry);
        }
    }
    @rmdir($path);
}

$png = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z9ZcAAAAASUVORK5CYII=',
    true
);
if (!is_string($png)) {
    throw new RuntimeException('Unable to build PNG fixture.');
}

v138d_check(reader_image_proxy_content_type('image/jpeg; charset=binary') === 'image/jpeg', 'JPEG Content-Type is accepted');
v138d_check(reader_image_proxy_content_type('image/png') === 'image/png', 'PNG Content-Type is accepted');
v138d_check(reader_image_proxy_content_type('image/svg+xml') === null, 'SVG is rejected as active-capable content');
v138d_check(reader_image_proxy_content_type('text/html') === null, 'HTML is rejected by image proxy');
v138d_check(reader_image_proxy_body_matches_type($png, 'image/png'), 'PNG magic is validated');
v138d_check(!reader_image_proxy_body_matches_type('<html>not image</html>', 'image/png'), 'mislabeled PNG body is rejected');

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rss-reader-v138d-' . bin2hex(random_bytes(6));
if (!mkdir($tmp, 0700, true) && !is_dir($tmp)) {
    throw new RuntimeException('Unable to create Reader Image test directory.');
}

$now = 1000;
$clock = static function () use (&$now): int {
    return $now;
};

$store = new ReaderImageProxyStore($tmp, 120, 5, 60, 524288, $clock);
$service = new ReaderImageProxyService($store, true, true, 524288);
$userId = 7;
$sourceUrl = 'https://images.example/article/photo.png';

$token = $service->register($userId, $sourceUrl);
v138d_check(is_string($token) && preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1, 'image registration returns opaque 64-hex token');
v138d_check(
    $token === $service->register($userId, $sourceUrl),
    'same user and source URL produce a stable token'
);

$otherToken = reader_image_proxy_token_for(8, $sourceUrl);
v138d_check(is_string($otherToken) && $otherToken !== $token, 'token is bound to authenticated user');

$publicSrc = is_string($token) ? reader_image_proxy_public_src($token) : null;
v138d_check(
    is_string($publicSrc)
        && str_starts_with($publicSrc, 'reader_image.php?id=')
        && !str_contains($publicSrc, 'images.example')
        && !str_contains($publicSrc, 'photo.png'),
    'browser image URL contains only local endpoint and opaque token'
);

$mapping = is_string($token) ? $store->resolve($userId, $token) : null;
v138d_check(
    is_array($mapping) && ($mapping['source_url'] ?? '') === $sourceUrl,
    'server-side registry resolves token to source URL for owner'
);
v138d_check(
    is_string($token) && $store->resolve(8, $token) === null,
    'another authenticated user cannot resolve the token'
);
v138d_check(
    $store->resolve($userId, str_repeat('f', 64)) === null,
    'unregistered or tampered token does not resolve'
);

$transportCalls = 0;
$lastRequest = null;
$GLOBALS['app_http_fetch_test_resolver'] = static fn (string $host): array => ['93.184.216.34'];
$GLOBALS['app_http_fetch_test_transport'] = static function (array $request) use (&$transportCalls, &$lastRequest, $png): array {
    $transportCalls++;
    $lastRequest = $request;
    return [
        'ok' => true,
        'status' => 200,
        'body' => $png,
        'location' => null,
        'etag' => null,
        'last_modified' => null,
        'retry_after' => null,
        'content_type' => 'image/png',
        'error_code' => '',
        'error_message' => '',
    ];
};

$first = is_string($token) ? $service->load($userId, $token) : [];
v138d_check(($first['ok'] ?? false) === true, 'registered public image is fetched successfully');
v138d_check(($first['content_type'] ?? '') === 'image/png', 'image response preserves allowlisted MIME type');
v138d_check(($first['body'] ?? '') === $png, 'image proxy returns fetched binary body');
v138d_check(($first['cache_status'] ?? '') === 'miss', 'first image request is a cache miss');
v138d_check($transportCalls === 1, 'first image request performs one outbound fetch');
v138d_check(
    ($lastRequest['user_agent'] ?? '') === (string) APP_READER_USER_AGENT,
    'image fetch uses dedicated Reader user agent'
);
v138d_check(
    str_starts_with((string) ($lastRequest['accept'] ?? ''), 'image/avif,image/webp'),
    'image fetch uses image-specific Accept negotiation'
);
v138d_check(
    ($lastRequest['max_bytes'] ?? 0) === 524288,
    'image fetch uses its bounded per-call response size'
);

$second = is_string($token) ? $service->load($userId, $token) : [];
v138d_check(
    ($second['ok'] ?? false) === true && ($second['cache_status'] ?? '') === 'hit',
    'fresh image is served from private server cache'
);
v138d_check($transportCalls === 1, 'fresh image cache hit performs no outbound fetch');

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
$stale = is_string($token) ? $service->load($userId, $token) : [];
v138d_check(
    ($stale['ok'] ?? false) === true
        && ($stale['cache_status'] ?? '') === 'stale'
        && ($stale['stale'] ?? false) === true
        && ($stale['stale_reason'] ?? '') === 'timeout',
    'stale cached image is served when refresh times out'
);

$badTypeUrl = 'https://images.example/article/not-image';
$badTypeToken = $service->register($userId, $badTypeUrl);
$GLOBALS['app_http_fetch_test_transport'] = static fn (array $request): array => [
    'ok' => true,
    'status' => 200,
    'body' => '<html>not an image</html>',
    'location' => null,
    'etag' => null,
    'last_modified' => null,
    'retry_after' => null,
    'content_type' => 'text/html',
    'error_code' => '',
    'error_message' => '',
];
$badType = is_string($badTypeToken) ? $service->load($userId, $badTypeToken) : [];
v138d_check(
    ($badType['ok'] ?? true) === false && ($badType['error_code'] ?? '') === 'unsupported_content_type',
    'HTML response is rejected even when requested through image endpoint'
);

$badMagicUrl = 'https://images.example/article/fake.png';
$badMagicToken = $service->register($userId, $badMagicUrl);
$GLOBALS['app_http_fetch_test_transport'] = static fn (array $request): array => [
    'ok' => true,
    'status' => 200,
    'body' => '<html>mislabeled</html>',
    'location' => null,
    'etag' => null,
    'last_modified' => null,
    'retry_after' => null,
    'content_type' => 'image/png',
    'error_code' => '',
    'error_message' => '',
];
$badMagic = is_string($badMagicToken) ? $service->load($userId, $badMagicToken) : [];
v138d_check(
    ($badMagic['ok'] ?? true) === false && ($badMagic['error_code'] ?? '') === 'invalid_image_body',
    'image MIME with invalid file signature is rejected'
);

$svgUrl = 'https://images.example/article/vector.svg';
$svgToken = $service->register($userId, $svgUrl);
$GLOBALS['app_http_fetch_test_transport'] = static fn (array $request): array => [
    'ok' => true,
    'status' => 200,
    'body' => '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
    'location' => null,
    'etag' => null,
    'last_modified' => null,
    'retry_after' => null,
    'content_type' => 'image/svg+xml',
    'error_code' => '',
    'error_message' => '',
];
$svg = is_string($svgToken) ? $service->load($userId, $svgToken) : [];
v138d_check(
    ($svg['ok'] ?? true) === false && ($svg['error_code'] ?? '') === 'unsupported_content_type',
    'SVG response is not proxied'
);

$privateRedirectUrl = 'https://images.example/article/redirect';
$privateRedirectToken = $service->register($userId, $privateRedirectUrl);
$GLOBALS['app_http_fetch_test_resolver'] = static fn (string $host): array => ['93.184.216.34'];
$GLOBALS['app_http_fetch_test_transport'] = static fn (array $request): array => [
    'ok' => true,
    'status' => 302,
    'body' => '',
    'location' => 'http://169.254.169.254/latest/meta-data/',
    'etag' => null,
    'last_modified' => null,
    'retry_after' => null,
    'content_type' => 'image/png',
    'error_code' => '',
    'error_message' => '',
];
$privateRedirect = is_string($privateRedirectToken)
    ? $service->load($userId, $privateRedirectToken)
    : [];
v138d_check(
    ($privateRedirect['ok'] ?? true) === false
        && ($privateRedirect['error_code'] ?? '') === 'non_public_address',
    'image redirect to private/link-local address is rejected by shared SSRF boundary'
);

$tooLargeUrl = 'https://images.example/article/large.png';
$tooLargeToken = $service->register($userId, $tooLargeUrl);
$GLOBALS['app_http_fetch_test_transport'] = static fn (array $request): array => [
    'ok' => false,
    'status' => 200,
    'body' => '',
    'location' => null,
    'error_code' => 'response_too_large',
    'error_message' => 'too large',
];
$tooLarge = is_string($tooLargeToken) ? $service->load($userId, $tooLargeToken) : [];
v138d_check(
    ($tooLarge['ok'] ?? true) === false && ($tooLarge['error_code'] ?? '') === 'response_too_large',
    'shared response-size protection is preserved for images'
);

v138d_check(
    ($service->load($userId, 'bad-token')['error_code'] ?? '') === 'invalid_token',
    'malformed browser token is rejected before any source lookup'
);
v138d_check(
    is_string($token) && ($service->load(8, $token)['error_code'] ?? '') === 'not_found',
    'cross-user image request fails without exposing the source'
);

// Direct cache integrity checks: checksum tamper and symlink body must not be served.
if (is_string($token)) {
    $cacheBody = $tmp . DIRECTORY_SEPARATOR . 'reader-image-cache-v1-' . $token . '.bin';
    $cacheMeta = $tmp . DIRECTORY_SEPARATOR . 'reader-image-cache-v1-' . $token . '.json';

    // Recreate a valid cache entry at the current clock.
    $store->writeCache($userId, $token, $sourceUrl, ['body' => $png, 'content_type' => 'image/png']);
    @file_put_contents($cacheBody, $png . 'tampered');
    v138d_check(
        $store->readCache($userId, $token, $sourceUrl) === null,
        'cache checksum mismatch is rejected'
    );

    $store->writeCache($userId, $token, $sourceUrl, ['body' => $png, 'content_type' => 'image/png']);
    $target = $tmp . DIRECTORY_SEPARATOR . 'external-body.bin';
    @file_put_contents($target, $png);
    @unlink($cacheBody);
    $linked = function_exists('symlink') ? @symlink($target, $cacheBody) : false;
    v138d_check(
        $linked !== true || $store->readCache($userId, $token, $sourceUrl) === null,
        'symlinked image cache body is never served'
    );
    @unlink($cacheBody);
    @unlink($target);
    @unlink($cacheMeta);
}

// Mapping TTL prevents indefinitely reusable tokens if an old Reader page remains open.
$expiringDir = $tmp . DIRECTORY_SEPARATOR . 'expiry';
$expiryNow = 2000;
$expiryClock = static function () use (&$expiryNow): int {
    return $expiryNow;
};
$expiryStore = new ReaderImageProxyStore($expiringDir, 10, 5, 60, 524288, $expiryClock);
$expiryToken = $expiryStore->register($userId, 'https://images.example/expiring.png');
$expiryNow = 2011;
v138d_check(
    is_string($expiryToken) && $expiryStore->resolve($userId, $expiryToken) === null,
    'expired image mapping token no longer resolves'
);

unset($GLOBALS['app_http_fetch_test_resolver'], $GLOBALS['app_http_fetch_test_transport']);
v138d_remove_tree($tmp);

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d/%d V1.38-D Reader Image Proxy checks failed.\n", count($failures), $checks));
    exit(1);
}

echo "V1.38-D Reader Image Proxy checks: {$checks} passed.\n";
