<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');

require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/validation.php';
require_once $root . '/app/http_fetch.php';
require_once $root . '/app/feed/feed_source.php';
require_once $root . '/app/feed/feed_source_mapper.php';
require_once $root . '/app/feed/feed_fetcher.php';

$checks = 0;
$failures = [];

function m1b_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) {
        $failures[] = $message;
    }
}

function m1b_throws(callable $operation, string $expectedClass): bool
{
    try {
        $operation();
    } catch (Throwable $error) {
        return $error instanceof $expectedClass;
    }
    return false;
}

// FeedSource is a minimal immutable runtime model, not a DB/UI record copy.
$source = FeedSource::fromValidatedValues(12, 34, 'https://feed.example.test/rss.xml');
m1b_check($source->sourceId === 12, 'FeedSource preserves positive source ID');
m1b_check($source->ownerId === 34, 'FeedSource preserves positive owner ID');
m1b_check($source->url === 'https://feed.example.test/rss.xml', 'FeedSource preserves prevalidated URL exactly');
m1b_check(count(get_object_vars($source)) === 3, 'FeedSource contains only sourceId, ownerId, and url');
m1b_check((new ReflectionClass(FeedSource::class))->getConstructor()?->isPrivate() === true, 'FeedSource construction is restricted to the validated-value factory');
m1b_check(!property_exists($source, 'contentStyle') && !property_exists($source, 'contentLocation'), 'FeedSource excludes UI-only content fields');

$readonlyBlocked = false;
try {
    $source->url = 'https://attacker.example/';
} catch (Error) {
    $readonlyBlocked = true;
}
m1b_check($readonlyBlocked && $source->url === 'https://feed.example.test/rss.xml', 'FeedSource fields are immutable');

m1b_check(m1b_throws(static fn() => FeedSource::fromValidatedValues(0, 34, 'https://feed.example.test/rss.xml'), InvalidArgumentException::class), 'FeedSource rejects zero source ID');
m1b_check(m1b_throws(static fn() => FeedSource::fromValidatedValues(-1, 34, 'https://feed.example.test/rss.xml'), InvalidArgumentException::class), 'FeedSource rejects negative source ID');
m1b_check(m1b_throws(static fn() => FeedSource::fromValidatedValues(12, 0, 'https://feed.example.test/rss.xml'), InvalidArgumentException::class), 'FeedSource rejects zero owner ID');
m1b_check(m1b_throws(static fn() => FeedSource::fromValidatedValues(12, -1, 'https://feed.example.test/rss.xml'), InvalidArgumentException::class), 'FeedSource rejects negative owner ID');
m1b_check(m1b_throws(static fn() => FeedSource::fromValidatedValues(12, 34, ''), InvalidArgumentException::class), 'FeedSource rejects empty URL');
m1b_check(m1b_throws(static fn() => FeedSource::fromValidatedValues(12, 34, ' https://feed.example.test/rss.xml '), InvalidArgumentException::class), 'FeedSource rejects non-canonical surrounding whitespace');

// The mapper accepts PDO-like integer strings and verifies authenticated ownership.
$mapper = new FeedSourceMapper();
$row = [
    'content_id' => '0012',
    'content_owner' => '34',
    'content_value' => 'https://untrusted.example/raw',
    'content_style' => 'danger',
    'content_location' => 3,
    'content_flag' => 0,
    'unexpected_secret' => 'must-not-copy',
];
$mapped = $mapper->fromOwnedContent($row, 34, 'https://feed.example.test/validated.xml');
m1b_check($mapped instanceof FeedSource, 'FeedSourceMapper accepts owner-scoped PDO-style numeric values');
m1b_check($mapped instanceof FeedSource && $mapped->sourceId === 12 && $mapped->ownerId === 34, 'FeedSourceMapper maps only content identity fields');
m1b_check($mapped instanceof FeedSource && $mapped->url === 'https://feed.example.test/validated.xml', 'FeedSourceMapper uses the separately validated URL, not raw content_value');
m1b_check($mapped instanceof FeedSource && count(get_object_vars($mapped)) === 3, 'FeedSourceMapper does not leak extra DB columns into the model');

m1b_check($mapper->fromOwnedContent($row, 99, 'https://feed.example.test/validated.xml') === null, 'FeedSourceMapper rejects owner mismatch');
m1b_check($mapper->fromOwnedContent(['content_owner' => 34], 34, 'https://feed.example.test/validated.xml') === null, 'FeedSourceMapper rejects missing content_id');
m1b_check($mapper->fromOwnedContent(['content_id' => 12], 34, 'https://feed.example.test/validated.xml') === null, 'FeedSourceMapper rejects missing content_owner');
m1b_check($mapper->fromOwnedContent(['content_id' => true, 'content_owner' => 34], 34, 'https://feed.example.test/validated.xml') === null, 'FeedSourceMapper rejects boolean identifiers');
m1b_check($mapper->fromOwnedContent(['content_id' => 12.0, 'content_owner' => 34], 34, 'https://feed.example.test/validated.xml') === null, 'FeedSourceMapper rejects floating-point identifiers');
m1b_check($mapper->fromOwnedContent(['content_id' => '12x', 'content_owner' => 34], 34, 'https://feed.example.test/validated.xml') === null, 'FeedSourceMapper rejects partially numeric identifiers');
m1b_check($mapper->fromOwnedContent(['content_id' => str_repeat('9', 100), 'content_owner' => 34], 34, 'https://feed.example.test/validated.xml') === null, 'FeedSourceMapper rejects integer overflow input');
m1b_check($mapper->fromOwnedContent(['content_id' => 12, 'content_owner' => 34], 0, 'https://feed.example.test/validated.xml') === null, 'FeedSourceMapper rejects unauthenticated owner context');
m1b_check($mapper->fromOwnedContent(['content_id' => 12, 'content_owner' => 34], 34, '') === null, 'FeedSourceMapper fails closed for invalid validated URL input');

// Fetcher must consume the model and continue using the hardened SB-09 transport.
$transportCalls = [];
$GLOBALS['app_http_fetch_test_resolver'] = static fn(string $host): array => ['93.184.216.34'];
$GLOBALS['app_http_fetch_test_transport'] = static function (array $request) use (&$transportCalls): array {
    $transportCalls[] = $request;
    return [
        'ok' => true,
        'status' => 200,
        'body' => '<rss>fixture</rss>',
        'location' => null,
        'error_code' => '',
        'error_message' => '',
    ];
};

$fetcher = new FeedFetcher();
$result = $fetcher->fetch($source);
m1b_check(($result['ok'] ?? false) === true, 'FeedFetcher accepts FeedSource and returns safe-fetch result');
m1b_check(($transportCalls[0]['url'] ?? null) === $source->url, 'FeedFetcher delegates only FeedSource URL to safe transport');
m1b_check(($transportCalls[0]['ip'] ?? null) === '93.184.216.34', 'FeedFetcher preserves DNS pinning through FeedSource boundary');
m1b_check(($transportCalls[0]['max_bytes'] ?? null) === APP_HTTP_MAX_BYTES, 'FeedFetcher preserves response-size limit through FeedSource boundary');
m1b_check(($transportCalls[0]['connect_timeout_ms'] ?? null) === APP_HTTP_CONNECT_TIMEOUT_MS, 'FeedFetcher preserves connect timeout through FeedSource boundary');
m1b_check(($transportCalls[0]['total_timeout_ms'] ?? null) === APP_HTTP_TIMEOUT_MS, 'FeedFetcher preserves total timeout through FeedSource boundary');

$blockedTransportCalled = false;
$GLOBALS['app_http_fetch_test_resolver'] = static fn(string $host): array => ['127.0.0.1'];
$GLOBALS['app_http_fetch_test_transport'] = static function (array $request) use (&$blockedTransportCalled): array {
    $blockedTransportCalled = true;
    return [
        'ok' => true,
        'status' => 200,
        'body' => 'must-not-run',
        'location' => null,
        'error_code' => '',
        'error_message' => '',
    ];
};
$privateSource = FeedSource::fromValidatedValues(13, 34, 'https://internal.example.test/feed.xml');
$blocked = $fetcher->fetch($privateSource);
m1b_check(($blocked['ok'] ?? true) === false && ($blocked['error_code'] ?? '') === 'non_public_address', 'FeedSource boundary preserves private-address blocking');
m1b_check($blockedTransportCalled === false, 'blocked FeedSource never reaches outbound transport');

unset($GLOBALS['app_http_fetch_test_resolver'], $GLOBALS['app_http_fetch_test_transport']);

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d/%d M1-B FeedSource checks failed.\n", count($failures), $checks));
    exit(1);
}

echo "All {$checks} executable M1-B FeedSource checks passed.\n";
