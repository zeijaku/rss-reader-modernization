<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/feed/feed_fetch_service.php';

$checks = 0;
$failures = [];
function m1f_cache_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) {
        $failures[] = $message;
    }
}

function m1f_cache_remove(string $path): void
{
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            m1f_cache_remove($path . DIRECTORY_SEPARATOR . $entry);
        }
    }
    @rmdir($path);
}

function m1f_cache_source(string $url = 'https://feed.example.test/rss.xml'): FeedSource
{
    return FeedSource::fromValidatedValues(10, 20, $url);
}

/** @return array<string,mixed> */
function m1f_cache_200(string $body, string $url, ?string $etag = null, ?string $lastModified = null): array
{
    return [
        'ok' => true,
        'url' => $url,
        'status' => 200,
        'body' => $body,
        'etag' => $etag,
        'last_modified' => $lastModified,
        'not_modified' => false,
        'error_code' => '',
        'error_message' => '',
    ];
}

/** @return array<string,mixed> */
function m1f_cache_304(string $url, ?string $etag = null, ?string $lastModified = null): array
{
    return [
        'ok' => true,
        'url' => $url,
        'status' => 304,
        'body' => '',
        'etag' => $etag,
        'last_modified' => $lastModified,
        'not_modified' => true,
        'error_code' => '',
        'error_message' => '',
    ];
}

final class M1fCacheTransport implements FeedTransportInterface
{
    public int $calls = 0;
    /** @var list<array<string,mixed>> */
    public array $responses;
    /** @var list<array<string,mixed>> */
    public array $validators = [];

    /** @param list<array<string,mixed>> $responses */
    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function fetch(FeedSource $source, array $validators = []): array
    {
        $this->calls++;
        $this->validators[] = $validators;
        return array_shift($this->responses) ?? [
            'ok' => false,
            'url' => $source->url,
            'status' => 0,
            'body' => '',
            'error_code' => 'no_response',
            'error_message' => 'No synthetic response.',
        ];
    }
}

class M1fCacheParser extends FeedParser
{
    public int $calls = 0;

    public function parse_start(mixed $contents, ?string $sourceUrl = null): array
    {
        $this->calls++;
        if (!is_string($contents) || !str_starts_with($contents, 'VALID:')) {
            $this->last_error = 'Synthetic invalid Feed.';
            return [];
        }
        $name = substr($contents, 6);
        return [
            'type' => 'rss2',
            'channel' => ['title' => $name, 'link' => $sourceUrl, 'description' => 'Synthetic'],
            'item' => [[
                'title' => 'Item ' . $name,
                'link' => 'https://article.example.test/' . rawurlencode($name),
                'description' => 'Description',
                'content' => 'Content',
                'date' => '2026-08-01 16:00:00',
            ]],
        ];
    }
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rss-m1f-' . bin2hex(random_bytes(6));
@mkdir($tmp, 0700, true);
register_shutdown_function(static fn () => m1f_cache_remove($tmp));

$source = m1f_cache_source();
$clock = 1000;
$clockFn = static function () use (&$clock): int { return $clock; };
$cache = new FeedCache($tmp . '/main', 60, 4096, $clockFn);
$transport = new M1fCacheTransport([
    m1f_cache_200('VALID:one', 'https://cdn.example.test/final.xml', '"v1"', 'Sat, 01 Aug 2026 06:00:00 GMT'),
    m1f_cache_304('https://cdn.example.test/final.xml', null, null),
    m1f_cache_200('VALID:two', 'https://cdn.example.test/final.xml'),
]);
$parser = new M1fCacheParser();
$service = new FeedFetchService($transport, $parser, $cache, true, 500, true);

$first = $service->load($source);
m1f_cache_check(($first['ok'] ?? false) === true && ($first['cache_status'] ?? '') === FeedFetchService::CACHE_MISS, 'first request stores an HTTP 200 Feed');
$entry = $cache->read($source);
m1f_cache_check($entry instanceof FeedCacheEntry && $entry->etag === '"v1"' && $entry->lastModified === 'Sat, 01 Aug 2026 06:00:00 GMT', 'HTTP validators are stored with the Feed body');
m1f_cache_check($entry?->bodyFetchedAt === 1000 && $entry?->validatedAt === 1000, 'initial body and validation timestamps are equal');

$clock = 1059;
$fresh = $service->load($source);
m1f_cache_check(($fresh['cache_status'] ?? '') === FeedFetchService::CACHE_HIT && $transport->calls === 1, 'fresh cache avoids all HTTP communication');

$clock = 1060;
$revalidated = $service->load($source);
m1f_cache_check(($revalidated['ok'] ?? false) === true && ($revalidated['cache_status'] ?? '') === FeedFetchService::CACHE_REVALIDATED, 'stale cache accepts HTTP 304 and reuses its body');
m1f_cache_check(($revalidated['result_feed']['item'][0]['title'] ?? '') === 'Item one', 'HTTP 304 returns the previously parsed Feed content');
m1f_cache_check(($transport->validators[1]['resource_url'] ?? '') === 'https://cdn.example.test/final.xml', 'conditional request is scoped to the previous effective URL');
m1f_cache_check(($transport->validators[1]['etag'] ?? '') === '"v1"', 'stored ETag is sent during revalidation');
$after304 = $cache->read($source);
m1f_cache_check($after304?->bodyFetchedAt === 1000 && $after304?->validatedAt === 1060, 'HTTP 304 updates validation time without changing body fetch time');
m1f_cache_check($after304?->etag === '"v1"' && $after304?->body === 'VALID:one', 'HTTP 304 keeps the old validator and body when response omits them');

$clock = 1061;
$afterFresh = $service->load($source);
m1f_cache_check(($afterFresh['cache_status'] ?? '') === FeedFetchService::CACHE_HIT && $transport->calls === 2, 'HTTP 304 makes the cache fresh again');

$clock = 1120;
$updated = $service->load($source);
m1f_cache_check(($updated['ok'] ?? false) === true && ($updated['result_feed']['item'][0]['title'] ?? '') === 'Item two', 'HTTP 200 after revalidation replaces the Feed body');
$after200 = $cache->read($source);
m1f_cache_check($after200?->bodyFetchedAt === 1120 && $after200?->validatedAt === 1120, 'HTTP 200 updates both timestamps');
m1f_cache_check($after200?->etag === null && $after200?->lastModified === null, 'HTTP 200 without validators clears old validator values');

// M1-E schema 1 is read as a valid cache entry without validators.
$legacyDir = $tmp . '/legacy';
$legacyCache = new FeedCache($legacyDir, 10, 4096, $clockFn);
@mkdir($legacyDir, 0700, true);
$legacyBody = 'VALID:legacy';
$legacyPayload = [
    'schema' => 1,
    'source_url' => $source->url,
    'effective_url' => $source->url,
    'status' => 200,
    'fetched_at' => 1000,
    'body_base64' => base64_encode($legacyBody),
    'body_sha256' => hash('sha256', $legacyBody),
];
file_put_contents($legacyCache->cachePath($source), json_encode($legacyPayload, JSON_THROW_ON_ERROR));
$legacyTransport = new M1fCacheTransport([m1f_cache_200('VALID:legacy-new', $source->url, '"legacy-v2"')]);
$legacyService = new FeedFetchService($legacyTransport, new M1fCacheParser(), $legacyCache, true, 500, true);
$legacyResult = $legacyService->load($source);
m1f_cache_check(($legacyResult['ok'] ?? false) === true && $legacyTransport->validators[0] === [], 'M1-E schema is accepted and refreshed normally because it has no validators');
$legacyUpdated = $legacyCache->read($source);
m1f_cache_check($legacyUpdated?->etag === '"legacy-v2"', 'legacy cache is upgraded to M1-F schema on the next HTTP 200');

// Conditional request can be disabled without disabling normal cache use.
$disabledClock = 3000;
$disabledFn = static function () use (&$disabledClock): int { return $disabledClock; };
$disabledCache = new FeedCache($tmp . '/disabled', 10, 4096, $disabledFn);
$disabledCache->writeSuccessfulFetch($source, m1f_cache_200('VALID:old', $source->url, '"old"'));
$disabledClock = 3010;
$disabledTransport = new M1fCacheTransport([m1f_cache_200('VALID:normal-fetch', $source->url, '"new"')]);
$disabledResult = (new FeedFetchService($disabledTransport, new M1fCacheParser(), $disabledCache, true, 500, false))->load($source);
m1f_cache_check(($disabledResult['ok'] ?? false) === true && $disabledTransport->validators[0] === [], 'conditional requests can be disabled while cache remains enabled');

// Fetch failure still does not serve stale content.
$errorClock = 4000;
$errorFn = static function () use (&$errorClock): int { return $errorClock; };
$errorCache = new FeedCache($tmp . '/error', 10, 4096, $errorFn);
$errorCache->writeSuccessfulFetch($source, m1f_cache_200('VALID:stale', $source->url, '"stale"'));
$errorClock = 4010;
$errorTransport = new M1fCacheTransport([[
    'ok' => false,
    'url' => $source->url,
    'status' => 500,
    'body' => '',
    'error_code' => 'http_status',
    'error_message' => 'failed',
]]);
$errorResult = (new FeedFetchService($errorTransport, new M1fCacheParser(), $errorCache, true, 500, true))->load($source);
m1f_cache_check(($errorResult['ok'] ?? true) === false && ($errorResult['error_type'] ?? '') === 'fetch', 'M1-F does not add stale-if-error behavior');

// A stale body that no longer parses is not extended by HTTP 304.
$badClock = 5000;
$badFn = static function () use (&$badClock): int { return $badClock; };
$badCache = new FeedCache($tmp . '/bad-body', 10, 4096, $badFn);
$badCache->writeSuccessfulFetch($source, m1f_cache_200('BROKEN', $source->url, '"broken"'));
$badClock = 5010;
$badTransport = new M1fCacheTransport([m1f_cache_200('VALID:recovered', $source->url, '"recovered"')]);
$badParser = new M1fCacheParser();
$badResult = (new FeedFetchService($badTransport, $badParser, $badCache, true, 500, true))->load($source);
m1f_cache_check(($badResult['ok'] ?? false) === true && $badTransport->validators[0] === [], 'unparsable stale body is deleted and refreshed without validators');
m1f_cache_check($badParser->calls === 2, 'unparsable stale body and replacement body are each parsed once');

// Invalid validator data in schema 2 invalidates the cache envelope.
$invalidClock = 6000;
$invalidFn = static function () use (&$invalidClock): int { return $invalidClock; };
$invalidCache = new FeedCache($tmp . '/invalid-validator', 60, 4096, $invalidFn);
$invalidCache->writeSuccessfulFetch($source, m1f_cache_200('VALID:validator', $source->url, '"valid"'));
$payload = json_decode((string) file_get_contents($invalidCache->cachePath($source)), true, 32, JSON_THROW_ON_ERROR);
$payload['etag'] = "\"bad\r\nX: 1\"";
file_put_contents($invalidCache->cachePath($source), json_encode($payload, JSON_THROW_ON_ERROR));
$invalidTransport = new M1fCacheTransport([m1f_cache_200('VALID:validator-recovered', $source->url)]);
$invalidResult = (new FeedFetchService($invalidTransport, new M1fCacheParser(), $invalidCache, true, 500, true))->load($source);
m1f_cache_check(($invalidResult['ok'] ?? false) === true && $invalidTransport->validators[0] === [], 'invalid cached validator is rejected and cannot become a request header');

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d/%d M1-F cache checks failed.\n", count($failures), $checks));
    exit(1);
}

echo "All {$checks} executable M1-F cache checks passed.\n";
