<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/feed/feed_fetch_service.php';

$checks = 0;
$failures = [];
function m1g_res_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) {
        $failures[] = $message;
    }
}

function m1g_res_remove(string $path): void
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
            m1g_res_remove($path . DIRECTORY_SEPARATOR . $entry);
        }
    }
    @rmdir($path);
}

function m1g_res_source(string $url = 'https://feed.example.test/rss.xml'): FeedSource
{
    return FeedSource::fromValidatedValues(10, 20, $url);
}

/** @return array<string,mixed> */
function m1g_res_ok(string $body, string $url, ?string $etag = null): array
{
    return [
        'ok' => true, 'url' => $url, 'status' => 200, 'body' => $body,
        'etag' => $etag, 'last_modified' => null, 'not_modified' => false,
        'retry_after' => null, 'error_code' => '', 'error_message' => '',
    ];
}

/** @return array<string,mixed> */
function m1g_res_error(string $code, int $status = 0, ?string $retryAfter = null, string $message = 'synthetic failure'): array
{
    return [
        'ok' => false, 'url' => 'https://feed.example.test/rss.xml', 'status' => $status, 'body' => '',
        'etag' => null, 'last_modified' => null, 'not_modified' => false,
        'retry_after' => $retryAfter, 'error_code' => $code, 'error_message' => $message,
    ];
}

/** @return array<string,mixed> */
function m1g_res_304(string $url, ?string $etag = null): array
{
    return [
        'ok' => true, 'url' => $url, 'status' => 304, 'body' => '',
        'etag' => $etag, 'last_modified' => null, 'not_modified' => true,
        'retry_after' => null, 'error_code' => '', 'error_message' => '',
    ];
}

final class M1gResTransport implements FeedTransportInterface
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
        return array_shift($this->responses) ?? m1g_res_error('no_response');
    }
}

class M1gResParser extends FeedParser
{
    public int $calls = 0;

    public function parse_start(mixed $contents, ?string $sourceUrl = null, bool $includeIdentity = false): array
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
                'date' => '2026-08-01 18:00:00',
            ]],
        ];
    }
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rss-m1g-' . bin2hex(random_bytes(6));
@mkdir($tmp, 0700, true);
register_shutdown_function(static fn () => m1g_res_remove($tmp));

$source = m1g_res_source('https://feed.example.test/rss.xml?token=private-value');
$clock = 1000;
$clockFn = static function () use (&$clock): int { return $clock; };
$cache = new FeedCache($tmp . '/main', 60, 4096, $clockFn);
$transport = new M1gResTransport([
    m1g_res_ok('VALID:one', 'https://cdn.example.test/final.xml', '"v1"'),
    m1g_res_error('timeout'),
    m1g_res_error('http_status', 503, '300'),
    m1g_res_304('https://cdn.example.test/final.xml', '"v1"'),
]);
$service = new FeedFetchService($transport, new M1gResParser(), $cache, true, 500, true, true, 3600, true, 86400);

$first = $service->load($source);
m1g_res_check(($first['ok'] ?? false) === true && ($first['cache_status'] ?? '') === FeedFetchService::CACHE_MISS, 'initial HTTP 200 stores a Feed normally');
$state = $cache->readState($source);
m1g_res_check(is_array($state) && $state['last_result'] === 'success' && $state['consecutive_failures'] === 0, 'successful fetch creates a clean state record');
m1g_res_check(($state['last_attempt_at'] ?? 0) === 1000 && ($state['last_success_at'] ?? 0) === 1000, 'success timestamps use the cache clock');
$stateJson = @file_get_contents($cache->statePath($source));
m1g_res_check(is_string($stateJson) && !str_contains($stateJson, 'feed.example.test') && !str_contains($stateJson, 'private-value'), 'state file does not contain the Feed URL or query token');
m1g_res_check(is_string($stateJson) && !str_contains($stateJson, 'synthetic failure'), 'state file does not store detailed transport messages');

$clock = 1060;
$stale = $service->load($source);
m1g_res_check(($stale['ok'] ?? false) === true && ($stale['cache_status'] ?? '') === FeedFetchService::CACHE_STALE, 'timeout serves bounded stale Feed data');
m1g_res_check(($stale['result_feed']['item'][0]['title'] ?? '') === 'Item one', 'stale result preserves the last valid parsed Feed');
$state = $cache->readState($source);
m1g_res_check(($state['last_result'] ?? '') === 'transient_error' && ($state['last_error_code'] ?? '') === 'timeout', 'timeout is recorded as a transient error');
m1g_res_check(($state['consecutive_failures'] ?? 0) === 1 && ($state['next_retry_at'] ?? 0) === 1120, 'first transient failure schedules a 60-second retry');
m1g_res_check(($state['last_success_at'] ?? 0) === 1000, 'failure keeps the previous success time');

$clock = 1100;
$beforeRetry = $service->load($source);
m1g_res_check(($beforeRetry['cache_status'] ?? '') === FeedFetchService::CACHE_STALE, 'backoff period serves stale data without a new request');
m1g_res_check($transport->calls === 2, 'backoff prevents repeated upstream access');

$clock = 1120;
$retry503 = $service->load($source);
m1g_res_check(($retry503['cache_status'] ?? '') === FeedFetchService::CACHE_STALE, 'HTTP 503 also falls back to stale data');
$state = $cache->readState($source);
m1g_res_check(($state['consecutive_failures'] ?? 0) === 2, 'second failure increments the failure counter');
m1g_res_check(($state['next_retry_at'] ?? 0) === 1420, 'HTTP 503 Retry-After overrides local backoff');
m1g_res_check(($state['last_http_status'] ?? 0) === 503, 'last HTTP status is recorded without response content');

$clock = 1420;
$recovered = $service->load($source);
m1g_res_check(($recovered['ok'] ?? false) === true && ($recovered['cache_status'] ?? '') === FeedFetchService::CACHE_REVALIDATED, 'retry resumes at the boundary and accepts HTTP 304');
$state = $cache->readState($source);
m1g_res_check(($state['last_result'] ?? '') === 'not_modified' && ($state['consecutive_failures'] ?? -1) === 0, 'HTTP 304 resets failure state');
m1g_res_check(($state['next_retry_at'] ?? -1) === 0 && ($state['last_success_at'] ?? 0) === 1420, 'successful revalidation clears backoff and updates success time');
m1g_res_check($transport->calls === 4, 'recovery performs one request at the retry boundary');

// Permanent failure does not use stale data and is held for 15 minutes.
$clock = 2000;
$permanentCache = new FeedCache($tmp . '/permanent', 10, 4096, $clockFn);
$permanentTransport = new M1gResTransport([m1g_res_ok('VALID:old', $source->url), m1g_res_error('http_status', 404)]);
$permanentService = new FeedFetchService($permanentTransport, new M1gResParser(), $permanentCache, true, 500, false, true, 3600, true, 86400);
$permanentService->load($source);
$clock = 2010;
$permanent = $permanentService->load($source);
m1g_res_check(($permanent['ok'] ?? true) === false && ($permanent['error_type'] ?? '') === 'fetch', 'HTTP 404 is not hidden by stale data');
$permanentState = $permanentCache->readState($source);
m1g_res_check(($permanentState['last_result'] ?? '') === 'permanent_error' && ($permanentState['next_retry_at'] ?? 0) === 2910, 'permanent error receives a fixed 15-minute wait');
$clock = 2020;
$permanentBackoff = $permanentService->load($source);
m1g_res_check(($permanentBackoff['fetch']['error_code'] ?? '') === 'retry_backoff' && $permanentTransport->calls === 2, 'permanent backoff suppresses another HTTP request');

// Security failures are never hidden and do not create a retry delay.
$clock = 3000;
$securityCache = new FeedCache($tmp . '/security', 10, 4096, $clockFn);
$securityTransport = new M1gResTransport([m1g_res_ok('VALID:old', $source->url), m1g_res_error('tls_error')]);
$securityService = new FeedFetchService($securityTransport, new M1gResParser(), $securityCache, true, 500, false, true, 3600, true, 86400);
$securityService->load($source);
$clock = 3010;
$security = $securityService->load($source);
m1g_res_check(($security['ok'] ?? true) === false && ($security['fetch']['error_code'] ?? '') === 'tls_error', 'TLS failure is returned instead of stale Feed data');
$securityState = $securityCache->readState($source);
m1g_res_check(($securityState['last_result'] ?? '') === 'security_error' && ($securityState['next_retry_at'] ?? -1) === 0, 'security failure is recorded without retry backoff');

// Parser failure may use stale data, but only inside the age limit.
$clock = 4000;
$parseCache = new FeedCache($tmp . '/parse', 10, 4096, $clockFn);
$parseTransport = new M1gResTransport([m1g_res_ok('VALID:old', $source->url), m1g_res_ok('BROKEN', $source->url)]);
$parseService = new FeedFetchService($parseTransport, new M1gResParser(), $parseCache, true, 500, false, true, 3600, true, 100);
$parseService->load($source);
$clock = 4010;
$parseFallback = $parseService->load($source);
m1g_res_check(($parseFallback['cache_status'] ?? '') === FeedFetchService::CACHE_STALE, 'temporary parser failure uses a recent stale Feed');
$parseState = $parseCache->readState($source);
m1g_res_check(($parseState['last_error_code'] ?? '') === 'parse_error' && ($parseState['last_result'] ?? '') === 'transient_error', 'parser failure stores only a short parse error code');

$clock = 5000;
$oldCache = new FeedCache($tmp . '/too-old', 10, 4096, $clockFn);
$oldTransport = new M1gResTransport([m1g_res_ok('VALID:old', $source->url), m1g_res_error('timeout')]);
$oldService = new FeedFetchService($oldTransport, new M1gResParser(), $oldCache, true, 500, false, true, 3600, true, 100);
$oldService->load($source);
$clock = 5101;
$tooOld = $oldService->load($source);
m1g_res_check(($tooOld['ok'] ?? true) === false, 'stale cache older than the configured limit is rejected');

$clock = 6000;
$boundaryCache = new FeedCache($tmp . '/boundary', 10, 4096, $clockFn);
$boundaryTransport = new M1gResTransport([m1g_res_ok('VALID:old', $source->url), m1g_res_error('timeout')]);
$boundaryService = new FeedFetchService($boundaryTransport, new M1gResParser(), $boundaryCache, true, 500, false, true, 3600, true, 100);
$boundaryService->load($source);
$clock = 6100;
$boundary = $boundaryService->load($source);
m1g_res_check(($boundary['cache_status'] ?? '') === FeedFetchService::CACHE_STALE, 'stale cache is accepted exactly at the maximum age boundary');

// Feature switches keep the old behavior available.
$clock = 7000;
$offCache = new FeedCache($tmp . '/off', 10, 4096, $clockFn);
$offTransport = new M1gResTransport([m1g_res_ok('VALID:old', $source->url), m1g_res_error('timeout')]);
$offService = new FeedFetchService($offTransport, new M1gResParser(), $offCache, true, 500, false, true, 3600, false, 100);
$offService->load($source);
$clock = 7010;
$staleOff = $offService->load($source);
m1g_res_check(($staleOff['ok'] ?? true) === false, 'stale-if-error can be disabled independently');

$clock = 8000;
$retryOffCache = new FeedCache($tmp . '/retry-off', 10, 4096, $clockFn);
$retryOffTransport = new M1gResTransport([m1g_res_ok('VALID:old', $source->url), m1g_res_error('timeout'), m1g_res_error('timeout')]);
$retryOffService = new FeedFetchService($retryOffTransport, new M1gResParser(), $retryOffCache, true, 500, false, false, 3600, true, 100);
$retryOffService->load($source);
$clock = 8010;
$retryOffService->load($source);
$clock = 8011;
$retryOffService->load($source);
m1g_res_check($retryOffTransport->calls === 3, 'retry state can be disabled while stale fallback remains available');
m1g_res_check($retryOffCache->readState($source) === null, 'retry-disabled mode creates no state file');

$disabledTransport = new M1gResTransport([m1g_res_error('timeout'), m1g_res_error('timeout')]);
$disabledService = new FeedFetchService($disabledTransport, new M1gResParser(), $offCache, false, 500, false, true, 3600, true, 100);
$disabledService->load($source);
$disabledService->load($source);
m1g_res_check($disabledTransport->calls === 2, 'cache-disabled mode performs normal fetches without stale or backoff');

// State corruption and filesystem problems fail safely.
$clock = 9000;
$stateCache = new FeedCache($tmp . '/state', 10, 4096, $clockFn);
@mkdir(dirname($stateCache->statePath($source)), 0700, true);
file_put_contents($stateCache->statePath($source), '{broken');
m1g_res_check($stateCache->readState($source) === null && !is_file($stateCache->statePath($source)), 'broken state JSON is removed safely');

$wrongState = [
    'schema' => 1, 'source_key' => str_repeat('a', 64), 'last_attempt_at' => 9000, 'last_success_at' => 0,
    'last_result' => 'transient_error', 'last_http_status' => 503, 'last_error_code' => 'http_status',
    'consecutive_failures' => 1, 'next_retry_at' => 9060,
];
file_put_contents($stateCache->statePath($source), json_encode($wrongState));
m1g_res_check($stateCache->readState($source) === null, 'state file cannot be swapped between Feed URLs');

$futureState = $wrongState;
$futureState['source_key'] = $stateCache->cacheKey($source);
$futureState['last_attempt_at'] = 10000;
file_put_contents($stateCache->statePath($source), json_encode($futureState));
m1g_res_check($stateCache->readState($source) === null, 'far-future state timestamp is rejected');

$outside = $tmp . '/outside-state.json';
file_put_contents($outside, 'SAFE');
@mkdir(dirname($stateCache->statePath($source)), 0700, true);
@symlink($outside, $stateCache->statePath($source));
$validState = [
    'schema' => 1, 'source_key' => $stateCache->cacheKey($source), 'last_attempt_at' => 9000, 'last_success_at' => 9000,
    'last_result' => 'success', 'last_http_status' => 200, 'last_error_code' => '',
    'consecutive_failures' => 0, 'next_retry_at' => 0,
];
m1g_res_check($stateCache->writeState($source, $validState) === false && file_get_contents($outside) === 'SAFE', 'state writer refuses a symlink target');
@unlink($stateCache->statePath($source));

m1g_res_check($stateCache->writeState($source, $validState) === true, 'valid state is written atomically');
$mode = fileperms($stateCache->statePath($source));
m1g_res_check(is_int($mode) && (($mode & 0077) === 0), 'state file has no group or other permission bits');
$leftovers = glob(dirname($stateCache->statePath($source)) . '/.feed-state-*') ?: [];
m1g_res_check($leftovers === [], 'state writes leave no temporary files');

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d of %d M1-G resilience checks failed.\n", count($failures), $checks));
    exit(1);
}
echo sprintf("All %d executable M1-G resilience checks passed.\n", $checks);
