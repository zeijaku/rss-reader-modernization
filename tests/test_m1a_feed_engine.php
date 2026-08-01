<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');

require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/validation.php';
require_once $root . '/app/http_fetch.php';
require_once $root . '/app/feed/feed_fetcher.php';
require_once $root . '/app/feed/feed_parser.php';
require_once $root . '/app/api.php';

$checks = 0;
$failures = [];

function m1a_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) {
        $failures[] = $message;
    }
}

// M1-02: normalized item is source-agnostic, typed, lossless and immutable.
$item = new NormalizedItem(
    'Title <kept internally>',
    'https://example.test/article?id=1&x=2',
    '<p>Description</p>',
    '<div>Content</div>',
    '2026-07-30 01:02:03'
);
$expectedItem = [
    'title' => 'Title <kept internally>',
    'link' => 'https://example.test/article?id=1&x=2',
    'description' => '<p>Description</p>',
    'content' => '<div>Content</div>',
    'date' => '2026-07-30 01:02:03',
];
m1a_check($item->toArray() === $expectedItem, 'NormalizedItem preserves the existing five-field item contract exactly');
m1a_check(array_keys($item->toArray()) === ['title', 'link', 'description', 'content', 'date'], 'NormalizedItem exposes a stable field order/shape');

$nullable = new NormalizedItem('', null, null, null, null);
m1a_check($nullable->toArray() === [
    'title' => '',
    'link' => null,
    'description' => null,
    'content' => null,
    'date' => null,
], 'NormalizedItem preserves empty title and nullable optional fields');

$readonlyBlocked = false;
try {
    $item->title = 'mutated';
} catch (Error) {
    $readonlyBlocked = true;
}
m1a_check($readonlyBlocked && $item->title === 'Title <kept internally>', 'NormalizedItem fields are immutable after construction');

$safeModelPayload = api_safe_feed_payload([
    'channel' => [
        'title' => '<b>Channel</b>',
        'link' => 'https://example.test/',
        'description' => '<script>bad()</script>Channel description',
    ],
    'item' => [$item->toArray()],
], 'https://example.test/feed.xml');
m1a_check(($safeModelPayload['item'][0]['title'] ?? null) === 'Title', 'normalized item still passes through SB-10 text sanitization at API boundary');
m1a_check(($safeModelPayload['item'][0]['description'] ?? null) === 'Description', 'normalized item HTML remains non-renderable after API normalization');
m1a_check(($safeModelPayload['item'][0]['link'] ?? null) === 'https://example.test/article?id=1&x=2', 'normalized item valid external URL survives API validation');

// M1-01: Fetcher delegates to the existing hardened SB-09 transport boundary.
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
$fetched = $fetcher->fetch('https://feed.example.test/rss.xml');
m1a_check(($fetched['ok'] ?? false) === true, 'FeedFetcher returns successful hardened transport result');
m1a_check(($fetched['status'] ?? null) === 200 && ($fetched['body'] ?? null) === '<rss>fixture</rss>', 'FeedFetcher preserves status/body from safe transport');
m1a_check(($fetched['url'] ?? null) === 'https://feed.example.test/rss.xml', 'FeedFetcher preserves the effective validated URL');
m1a_check(count($transportCalls) === 1, 'FeedFetcher performs one transport call for a direct 200 response');
$request = $transportCalls[0] ?? [];
m1a_check(($request['ip'] ?? null) === '93.184.216.34', 'FeedFetcher still uses validated/pinned DNS result');
m1a_check(($request['max_bytes'] ?? null) === APP_HTTP_MAX_BYTES, 'FeedFetcher still applies configured response-size limit');
m1a_check(($request['connect_timeout_ms'] ?? null) === APP_HTTP_CONNECT_TIMEOUT_MS, 'FeedFetcher still applies configured connect timeout');
m1a_check(($request['total_timeout_ms'] ?? null) === APP_HTTP_TIMEOUT_MS, 'FeedFetcher still applies configured total timeout');

$blockedTransportCalled = false;
$GLOBALS['app_http_fetch_test_resolver'] = static fn(string $host): array => ['127.0.0.1'];
$GLOBALS['app_http_fetch_test_transport'] = static function (array $request) use (&$blockedTransportCalled): array {
    $blockedTransportCalled = true;
    return [
        'ok' => true,
        'status' => 200,
        'body' => 'should-not-run',
        'location' => null,
        'error_code' => '',
        'error_message' => '',
    ];
};
$blocked = $fetcher->fetch('https://internal.example.test/feed.xml');
m1a_check(($blocked['ok'] ?? true) === false && ($blocked['error_code'] ?? '') === 'non_public_address', 'FeedFetcher preserves SSRF blocking for resolved loopback addresses');
m1a_check($blockedTransportCalled === false, 'blocked Feed target never reaches transport through FeedFetcher');

unset($GLOBALS['app_http_fetch_test_resolver'], $GLOBALS['app_http_fetch_test_transport']);

// Parser compatibility behavior remains deterministic without optional extensions.
$parser = new FeedParser();
m1a_check($parser->parse_normalized(null) === [] && $parser->last_error === 'Feed body is empty.', 'FeedParser normalized path rejects null body deterministically');
m1a_check($parser->parse_start('') === [] && $parser->last_error === 'Feed body is empty.', 'FeedParser compatibility path rejects empty body deterministically');
m1a_check(is_subclass_of('rss_parse', FeedParser::class), 'Legacy rss_parse name remains a compatibility subclass of FeedParser');

$liveParserAvailable = function_exists('simplexml_load_string')
    && function_exists('mb_detect_encoding')
    && function_exists('mb_convert_encoding');

if ($liveParserAvailable) {
    $body = file_get_contents($root . '/tests/fixtures/rss2_four.xml');
    $normalized = $parser->parse_normalized($body);
    m1a_check(($normalized['type'] ?? null) === 'rss2', 'normalized parser recognizes RSS 2.0 fixture');
    m1a_check(count($normalized['item'] ?? []) === 4, 'normalized parser preserves RSS fixture item count');
    m1a_check(($normalized['item'][0] ?? null) instanceof NormalizedItem, 'parser emits NormalizedItem objects before API compatibility conversion');
    $firstNormalized = $normalized['item'][0] ?? null;
    m1a_check($firstNormalized instanceof NormalizedItem && $firstNormalized->date === '2026-07-29 10:00:00', 'normalized item preserves existing date normalization behavior');

    $legacy = $parser->parse_start($body);
    m1a_check(isset($legacy['item'][0]) && is_array($legacy['item'][0]), 'compatibility parser converts normalized item objects back to arrays');
    m1a_check(($legacy['item'][0]['title'] ?? null) === ($firstNormalized instanceof NormalizedItem ? $firstNormalized->title : null), 'compatibility array title matches normalized model');
    m1a_check(($legacy['item'][0]['link'] ?? null) === ($firstNormalized instanceof NormalizedItem ? $firstNormalized->link : null), 'compatibility array link matches normalized model');

    $zero = $parser->parse_normalized(file_get_contents($root . '/tests/fixtures/rss2_zero.xml'));
    m1a_check(($zero['type'] ?? null) === 'rss2' && ($zero['item'] ?? null) === [], 'zero-item RSS remains a valid normalized feed');

    $atom = $parser->parse_normalized(file_get_contents($root . '/tests/fixtures/atom_no_declaration.xml'));
    m1a_check(($atom['type'] ?? null) === 'atom' && ($atom['item'][0] ?? null) instanceof NormalizedItem, 'Atom fixture also emits normalized items');

    $rss1 = $parser->parse_normalized(file_get_contents($root . '/tests/fixtures/rss1_basic.xml'));
    m1a_check(($rss1['type'] ?? null) === 'rss1' && ($rss1['item'][0] ?? null) instanceof NormalizedItem, 'RSS 1.0 fixture also emits normalized items');

    $malformed = $parser->parse_normalized(file_get_contents($root . '/tests/fixtures/malformed.xml'));
    m1a_check($malformed === [] && is_string($parser->last_error) && $parser->last_error !== '', 'malformed XML still fails with a controlled parser error');
} else {
    echo "SKIP: M1-A live normalized parser checks require SimpleXML and mbstring.\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d/%d M1-A feed engine checks failed.\n", count($failures), $checks));
    exit(1);
}

echo "All {$checks} executable M1-A feed engine checks passed.\n";
