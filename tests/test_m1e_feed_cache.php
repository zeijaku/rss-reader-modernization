<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/feed/feed_fetch_service.php';

$checks = 0;
$failures = [];

function m1e_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) {
        $failures[] = $message;
    }
}

function m1e_remove_tree(string $path): void
{
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        m1e_remove_tree($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}

function m1e_source(int $id, int $owner, string $url): FeedSource
{
    return FeedSource::fromValidatedValues($id, $owner, $url);
}

/** @return array<string,mixed> */
function m1e_success_fetch(string $body = 'VALID:one', string $url = 'https://feed.example.test/rss.xml'): array
{
    return [
        'ok' => true,
        'url' => $url,
        'status' => 200,
        'body' => $body,
        'error_code' => null,
        'error_message' => null,
    ];
}

final class M1eFakeTransport implements FeedTransportInterface
{
    public int $calls = 0;
    /** @var list<array<string,mixed>> */
    public array $responses;

    /** @param list<array<string,mixed>> $responses */
    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
    }

    public function fetch(FeedSource $source, array $validators = []): array
    {
        $this->calls++;
        if ($this->responses !== []) {
            return array_shift($this->responses);
        }
        return m1e_success_fetch('VALID:default', $source->url);
    }
}

class M1eFakeParser extends FeedParser
{
    public int $calls = 0;

    public function parse_start(mixed $contents, ?string $sourceUrl = null, bool $includeIdentity = false): array
    {
        $this->calls++;
        if (!is_string($contents) || !str_starts_with($contents, 'VALID:')) {
            $this->last_error = 'Synthetic invalid Feed.';
            return [];
        }
        $suffix = substr($contents, 6);
        return [
            'type' => 'rss2',
            'channel' => [
                'title' => 'Cached ' . $suffix,
                'link' => $sourceUrl,
                'description' => 'Synthetic Feed',
            ],
            'item' => [[
                'title' => 'Item ' . $suffix,
                'link' => 'https://article.example.test/' . rawurlencode($suffix),
                'description' => 'Description',
                'content' => 'Content',
                'date' => '2026-08-01 14:00:00',
            ]],
        ];
    }
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rss-m1e-' . bin2hex(random_bytes(6));
@mkdir($tmp, 0700, true);
register_shutdown_function(static fn () => m1e_remove_tree($tmp));

$clock = 1000;
$clockFn = static function () use (&$clock): int { return $clock; };
$cacheDir = $tmp . '/cache-basic';
$cache = new FeedCache($cacheDir, 60, 1024, $clockFn);
$source = m1e_source(10, 20, 'https://feed.example.test/rss.xml');
$sameUrlOtherRecord = m1e_source(999, 777, 'https://feed.example.test/rss.xml');
$querySource = m1e_source(11, 20, 'https://feed.example.test/rss.xml?view=full');

m1e_check($cache->cacheKey($source) === $cache->cacheKey($sameUrlOtherRecord), 'cache key is shared by equal validated URLs regardless of content/owner ID');
m1e_check($cache->cacheKey($source) !== $cache->cacheKey($querySource), 'query-string differences produce distinct cache keys');
m1e_check(!str_contains($cache->cachePath($source), 'feed.example.test'), 'cache filename does not expose the Feed host or URL');
m1e_check((bool) preg_match('/feed-v1-[a-f0-9]{64}\.json$/', $cache->cachePath($source)), 'cache filename uses a versioned SHA-256 key');

$transport = new M1eFakeTransport([m1e_success_fetch('VALID:first', 'https://cdn.example.test/final.xml')]);
$parser = new M1eFakeParser();
$service = new FeedFetchService($transport, $parser, $cache, true, 500);
$first = $service->load($source);
m1e_check(($first['ok'] ?? false) === true && ($first['cache_status'] ?? '') === FeedFetchService::CACHE_MISS, 'cache miss performs transport and returns success');
m1e_check($transport->calls === 1 && $parser->calls === 1, 'cache miss invokes transport and parser once');
m1e_check(($first['effective_url'] ?? '') === 'https://cdn.example.test/final.xml', 'network result preserves safe effective URL');
m1e_check(is_file($cache->cachePath($source)), 'successful transport plus parse stores one cache document');

$second = $service->load($sameUrlOtherRecord);
m1e_check(($second['ok'] ?? false) === true && ($second['cache_status'] ?? '') === FeedFetchService::CACHE_HIT, 'fresh cache is shared across records with the same URL');
m1e_check($transport->calls === 1 && $parser->calls === 2, 'fresh cache skips transport but still reparses the Feed body');
m1e_check(($second['effective_url'] ?? '') === 'https://cdn.example.test/final.xml', 'cache hit restores effective URL metadata');
m1e_check(($second['result_feed']['item'][0]['title'] ?? '') === 'Item first', 'cache hit preserves the parsed public Feed result');

$clock = 1059;
$beforeBoundary = $service->load($source);
m1e_check(($beforeBoundary['cache_status'] ?? '') === FeedFetchService::CACHE_HIT && $transport->calls === 1, 'cache remains fresh immediately before TTL boundary');
$clock = 1060;
$transport->responses[] = m1e_success_fetch('VALID:refreshed', $source->url);
$atBoundary = $service->load($source);
m1e_check(($atBoundary['cache_status'] ?? '') === FeedFetchService::CACHE_MISS && $transport->calls === 2, 'cache becomes stale exactly at the TTL boundary');
m1e_check(($atBoundary['result_feed']['item'][0]['title'] ?? '') === 'Item refreshed', 'stale cache is replaced by newly fetched Feed data');

// Cache disabled: existing cache must not be consulted or updated.
$disabledTransport = new M1eFakeTransport([
    m1e_success_fetch('VALID:disabled-one', $source->url),
    m1e_success_fetch('VALID:disabled-two', $source->url),
]);
$disabled = new FeedFetchService($disabledTransport, new M1eFakeParser(), $cache, false, 500);
$d1 = $disabled->load($source);
$d2 = $disabled->load($source);
m1e_check(($d1['cache_status'] ?? '') === FeedFetchService::CACHE_DISABLED && ($d2['cache_status'] ?? '') === FeedFetchService::CACHE_DISABLED, 'cache-disabled mode is explicit in internal result');
m1e_check($disabledTransport->calls === 2, 'cache-disabled mode performs transport for every request');

// Unsupported/malformed content must never be persisted.
$invalidDir = $tmp . '/cache-invalid';
$invalidCache = new FeedCache($invalidDir, 60, 1024, $clockFn);
$invalidTransport = new M1eFakeTransport([m1e_success_fetch('NOT-A-FEED', $source->url)]);
$invalidParser = new M1eFakeParser();
$invalidService = new FeedFetchService($invalidTransport, $invalidParser, $invalidCache, true, 500);
$invalid = $invalidService->load($source);
m1e_check(($invalid['ok'] ?? true) === false && ($invalid['error_type'] ?? '') === 'parse', 'unsupported upstream body returns a controlled parse failure');
m1e_check(!is_file($invalidCache->cachePath($source)), 'parse failure is not cached');
m1e_check(($invalid['parse_error'] ?? '') === 'Synthetic invalid Feed.', 'parse failure reason remains available to the API boundary');

$fetchFailDir = $tmp . '/cache-fetch-fail';
$fetchFailCache = new FeedCache($fetchFailDir, 60, 1024, $clockFn);
$fetchFailTransport = new M1eFakeTransport([[
    'ok' => false,
    'url' => $source->url,
    'status' => 502,
    'body' => '',
    'error_code' => 'timeout',
    'error_message' => 'timed out',
]]);
$fetchFailService = new FeedFetchService($fetchFailTransport, new M1eFakeParser(), $fetchFailCache, true, 500);
$fetchFail = $fetchFailService->load($source);
m1e_check(($fetchFail['ok'] ?? true) === false && ($fetchFail['error_type'] ?? '') === 'fetch', 'transport failure remains distinguishable from parse failure');
m1e_check(!is_file($fetchFailCache->cachePath($source)), 'HTTP/transport failure is not cached');

// Stale-if-error is intentionally deferred: stale content must not mask failure.
$staleDir = $tmp . '/cache-stale-error';
$staleClock = 2000;
$staleCache = new FeedCache($staleDir, 10, 1024, static function () use (&$staleClock): int { return $staleClock; });
$staleCache->writeSuccessfulFetch($source, m1e_success_fetch('VALID:stale', $source->url));
$staleClock = 2010;
$staleTransport = new M1eFakeTransport([['ok' => false, 'error_code' => 'timeout', 'status' => 0, 'body' => '', 'url' => $source->url]]);
$staleResult = (new FeedFetchService($staleTransport, new M1eFakeParser(), $staleCache, true, 500))->load($source);
m1e_check(($staleResult['ok'] ?? true) === false && $staleTransport->calls === 1, 'expired cache is not served as stale-if-error in M1-E');
m1e_check(is_file($staleCache->cachePath($source)), 'stale cache file is retained for future conditional-request metadata');

// Corrupt documents are rejected and refreshed.
$corruptDir = $tmp . '/cache-corrupt';
$corruptCache = new FeedCache($corruptDir, 60, 1024, $clockFn);
@mkdir($corruptDir, 0700, true);
file_put_contents($corruptCache->cachePath($source), '{broken json');
$corruptTransport = new M1eFakeTransport([m1e_success_fetch('VALID:recovered', $source->url)]);
$corruptResult = (new FeedFetchService($corruptTransport, new M1eFakeParser(), $corruptCache, true, 500))->load($source);
m1e_check(($corruptResult['ok'] ?? false) === true && $corruptTransport->calls === 1, 'invalid JSON cache is ignored and safely refetched');

$payload = json_decode((string) file_get_contents($corruptCache->cachePath($source)), true, 32, JSON_THROW_ON_ERROR);
$payload['body_sha256'] = str_repeat('0', 64);
file_put_contents($corruptCache->cachePath($source), json_encode($payload, JSON_THROW_ON_ERROR));
$checksumTransport = new M1eFakeTransport([m1e_success_fetch('VALID:checksum-recovery', $source->url)]);
$checksumResult = (new FeedFetchService($checksumTransport, new M1eFakeParser(), $corruptCache, true, 500))->load($source);
m1e_check(($checksumResult['ok'] ?? false) === true && $checksumTransport->calls === 1, 'body checksum mismatch is rejected and refetched');

$payload = json_decode((string) file_get_contents($corruptCache->cachePath($source)), true, 32, JSON_THROW_ON_ERROR);
$payload['source_url'] = 'https://other.example.test/feed.xml';
file_put_contents($corruptCache->cachePath($source), json_encode($payload, JSON_THROW_ON_ERROR));
$scopeTransport = new M1eFakeTransport([m1e_success_fetch('VALID:scope-recovery', $source->url)]);
$scopeResult = (new FeedFetchService($scopeTransport, new M1eFakeParser(), $corruptCache, true, 500))->load($source);
m1e_check(($scopeResult['ok'] ?? false) === true && $scopeTransport->calls === 1, 'cache payload cannot be swapped between different source URLs');

$payload = json_decode((string) file_get_contents($corruptCache->cachePath($source)), true, 32, JSON_THROW_ON_ERROR);
$payload['fetched_at'] = $clock + 1000;
file_put_contents($corruptCache->cachePath($source), json_encode($payload, JSON_THROW_ON_ERROR));
$futureTransport = new M1eFakeTransport([m1e_success_fetch('VALID:future-recovery', $source->url)]);
$futureResult = (new FeedFetchService($futureTransport, new M1eFakeParser(), $corruptCache, true, 500))->load($source);
m1e_check(($futureResult['ok'] ?? false) === true && $futureTransport->calls === 1, 'far-future cache timestamps cannot remain fresh indefinitely');

// A valid cache envelope whose body no longer parses is invalidated and refreshed.
$semanticDir = $tmp . '/cache-semantic';
$semanticCache = new FeedCache($semanticDir, 60, 1024, $clockFn);
$semanticCache->writeSuccessfulFetch($source, m1e_success_fetch('NOT-A-FEED', $source->url));
$semanticTransport = new M1eFakeTransport([m1e_success_fetch('VALID:semantic-recovery', $source->url)]);
$semanticParser = new M1eFakeParser();
$semanticResult = (new FeedFetchService($semanticTransport, $semanticParser, $semanticCache, true, 500))->load($source);
m1e_check(($semanticResult['ok'] ?? false) === true && $semanticTransport->calls === 1, 'cached body that fails current parser is invalidated and refreshed');
m1e_check($semanticParser->calls === 2, 'semantic cache failure is parsed once before one network refresh');

// Maximum body size is enforced at persistence boundary.
$smallCache = new FeedCache($tmp . '/cache-small', 60, 8, $clockFn);
m1e_check(!$smallCache->writeSuccessfulFetch($source, m1e_success_fetch('VALID:body-too-long', $source->url)), 'oversized body is rejected by cache persistence');

// Lock timeout must fail open without concurrent cache writes.
$lockDir = $tmp . '/cache-lock';
$lockCache = new FeedCache($lockDir, 60, 1024, $clockFn);
$held = $lockCache->acquireLock($source, 100);
m1e_check($held instanceof FeedCacheLock, 'URL-specific lock can be acquired');
$lockTransport = new M1eFakeTransport([m1e_success_fetch('VALID:bypass', $source->url)]);
$lockResult = (new FeedFetchService($lockTransport, new M1eFakeParser(), $lockCache, true, 0))->load($source);
m1e_check(($lockResult['ok'] ?? false) === true && ($lockResult['cache_status'] ?? '') === FeedFetchService::CACHE_BYPASS, 'lock timeout fails open through hardened transport');
m1e_check(!is_file($lockCache->cachePath($source)), 'lock-timeout bypass does not race a concurrent cache writer');
$held?->release();

// All failure paths release their lock.
$releaseDir = $tmp . '/cache-release';
$releaseCache = new FeedCache($releaseDir, 60, 1024, $clockFn);
$releaseService = new FeedFetchService(
    new M1eFakeTransport([m1e_success_fetch('NOT-A-FEED', $source->url)]),
    new M1eFakeParser(),
    $releaseCache,
    true,
    100
);
$releaseService->load($source);
$afterFailureLock = $releaseCache->acquireLock($source, 0);
m1e_check($afterFailureLock instanceof FeedCacheLock, 'parse failure releases URL lock in finally block');
$afterFailureLock?->release();

// Filesystem setup failure must degrade to uncached fetch.
$blocker = $tmp . '/not-a-directory';
file_put_contents($blocker, 'block');
$unwritableCache = new FeedCache($blocker . '/feed', 60, 1024, $clockFn);
$fallbackTransport = new M1eFakeTransport([m1e_success_fetch('VALID:filesystem-fallback', $source->url)]);
$fallbackResult = (new FeedFetchService($fallbackTransport, new M1eFakeParser(), $unwritableCache, true, 10))->load($source);
m1e_check(($fallbackResult['ok'] ?? false) === true && ($fallbackResult['cache_status'] ?? '') === FeedFetchService::CACHE_BYPASS, 'cache directory failure degrades to uncached safe transport');

// Symlink cache target must not be followed or overwritten.
if (function_exists('symlink') && DIRECTORY_SEPARATOR === '/') {
    $symlinkDir = $tmp . '/cache-symlink';
    @mkdir($symlinkDir, 0700, true);
    $symlinkCache = new FeedCache($symlinkDir, 60, 1024, $clockFn);
    $outside = $tmp . '/outside-cache-target';
    file_put_contents($outside, 'do-not-touch');
    @symlink($outside, $symlinkCache->cachePath($source));
    $symlinkWrite = $symlinkCache->writeSuccessfulFetch($source, m1e_success_fetch('VALID:symlink', $source->url));
    m1e_check($symlinkWrite === false && file_get_contents($outside) === 'do-not-touch', 'cache writer refuses symlink target without modifying external file');
} else {
    echo "SKIP: symlink cache-target test is unavailable on this platform.\n";
}

// Stored representation is JSON/base64, not PHP serialization.
$representation = (string) file_get_contents($cache->cachePath($source));
$decoded = json_decode($representation, true, 32, JSON_THROW_ON_ERROR);
m1e_check(($decoded['schema'] ?? null) === FeedCacheEntry::SCHEMA_VERSION, 'cache representation carries an explicit schema version');
m1e_check(base64_decode((string) ($decoded['body_base64'] ?? ''), true) !== false, 'cache stores arbitrary Feed bytes using strict base64 envelope');
m1e_check(!str_contains($representation, 'O:') && !str_contains($representation, 'C:'), 'cache representation contains no PHP serialized objects');

if (DIRECTORY_SEPARATOR === '/') {
    $dirMode = fileperms($cacheDir) & 0777;
    $fileMode = fileperms($cache->cachePath($source)) & 0777;
    m1e_check(($dirMode & 0077) === 0, 'cache directory has no group/other permission bits');
    m1e_check(($fileMode & 0077) === 0, 'cache file has no group/other permission bits');
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d/%d M1-E cache checks failed.\n", count($failures), $checks));
    exit(1);
}

echo "All {$checks} executable M1-E cache checks passed.\n";
