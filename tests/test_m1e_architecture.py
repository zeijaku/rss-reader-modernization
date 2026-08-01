from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
FEED = ROOT / 'app' / 'feed'
cache = (FEED / 'feed_cache.php').read_text(encoding='utf-8')
entry = (FEED / 'feed_cache_entry.php').read_text(encoding='utf-8')
lock = (FEED / 'feed_cache_lock.php').read_text(encoding='utf-8')
service = (FEED / 'feed_fetch_service.php').read_text(encoding='utf-8')
transport = (FEED / 'feed_transport_interface.php').read_text(encoding='utf-8')
fetcher = (FEED / 'feed_fetcher.php').read_text(encoding='utf-8')
api = (ROOT / 'app' / 'api.php').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app' / 'bootstrap.php').read_text(encoding='utf-8')
conf = (ROOT / 'app' / 'common' / 'common_conf.php').read_text(encoding='utf-8')
gitignore = (ROOT / '.gitignore').read_text(encoding='utf-8')
env_example = (ROOT / 'config' / '.env.example').read_text(encoding='utf-8')
local_example = (ROOT / 'config' / 'local.php.example').read_text(encoding='utf-8')
index = (ROOT / 'public' / 'index.php').read_text(encoding='utf-8')
schema = (ROOT / 'database' / 'schema.sql').read_text(encoding='utf-8')

checks = []
def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

for name in [
    'feed_transport_interface.php', 'feed_cache_entry.php', 'feed_cache_lock.php',
    'feed_cache.php', 'feed_fetch_service.php',
]:
    check((FEED / name).is_file(), f'M1-E module exists: {name}')

check('interface FeedTransportInterface' in transport, 'safe transport has an injectable interface for cache/concurrency tests')
check('implements FeedTransportInterface' in fetcher, 'existing hardened FeedFetcher implements the transport interface')
check('app_safe_http_fetch($source->url)' in fetcher, 'M1-E still delegates network access to the SB-09 hardened transport')
check('final class FeedCacheEntry' in entry and 'SCHEMA_VERSION = 1' in entry, 'cache payload is an explicit versioned immutable value')
check("'body_base64'" in entry and "'body_sha256'" in entry, 'cache body uses base64 plus integrity hash')
check("base64_decode($payload['body_base64'], true)" in entry, 'cache base64 decoding uses strict mode')
check("hash_equals($payload['body_sha256'], hash('sha256', $body))" in entry, 'cache validates body integrity before use')
check('serialize(' not in cache + entry + service and 'unserialize(' not in cache + entry + service, 'cache never uses PHP object serialization')
check("hash('sha256', $source->url)" in cache, 'cache and lock keys derive from the validated Feed URL')
check("self::FILE_PREFIX . $this->cacheKey($source)" in cache, 'raw Feed URL is absent from cache filenames')
check("tempnam($this->directory" in cache and "rename($temp, $target)" in cache, 'cache writes use a same-directory temporary file and rename')
check('LOCK_EX | LOCK_NB' in cache and 'hrtime(true)' in cache, 'duplicate fetch suppression uses bounded non-blocking file locking')
check('finally {' in service and '$lock->release();' in service, 'Feed URL lock is released on every service path')
check('Double-checked locking' in service and service.count('loadFreshCache($source)') >= 3, 'service rechecks cache after waiting for a lock')
check("parse_start($entry->body, $source->url)" in service, 'cache hit still passes body through Parser and Item Identity scope')
check("parse_start($body, $source->url)" in service, 'network body uses the same Parser and Item Identity scope')
check('writeSuccessfulFetch($source, $fetch)' in service, 'cache persistence occurs only after successful parse')
write_pos = service.find('writeSuccessfulFetch($source, $fetch)')
parse_pos = service.find('$feed = $this->parser->parse_start($body, $source->url)')
check(parse_pos >= 0 and write_pos > parse_pos, 'parse validation precedes cache persistence')
check('CACHE_BYPASS' in service and 'fetchAndParse($source, self::CACHE_BYPASS, false)' in service, 'lock/filesystem failure degrades to uncached safe transport without racing writes')
check('stale' not in re.sub(r'//.*', '', service.lower()), 'M1-E does not silently implement stale-if-error behavior')

for token, default in [
    ('APP_FEED_CACHE_ENABLED', 'true'),
    ('APP_FEED_CACHE_TTL_SECONDS', '60'),
    ('APP_FEED_CACHE_LOCK_TIMEOUT_MS', '9000'),
]:
    check(token in conf, f'runtime configuration defines {token}')
    check(token in env_example and token in local_example, f'configuration examples document {token}')
check("dirname(__DIR__, 2) . '/var/cache/feed'" in conf, 'default cache directory is outside public DocumentRoot')
check('APP_FEED_CACHE_TTL_SECONDS' in conf and 'min(86400' in conf, 'cache TTL is bounded')
check('APP_FEED_CACHE_LOCK_TIMEOUT_MS' in conf and 'min(30000' in conf, 'lock timeout is bounded')

order = [
    '/feed/feed_transport_interface.php', '/feed/feed_fetcher.php', '/feed/feed_cache_entry.php',
    '/feed/feed_cache_lock.php', '/feed/feed_cache.php', '/feed/feed_parser.php', '/feed/feed_fetch_service.php',
]
check(all(x in bootstrap for x in order), 'bootstrap loads all M1-E modules')
check(all(bootstrap.index(order[i]) < bootstrap.index(order[i+1]) for i in range(len(order)-1)), 'M1-E modules load in dependency order')

body = re.search(r'function api_feed_fetch\(.*?\n\}', api, re.S).group(0)
owner_pos = body.find('find_owned_active_content')
service_pos = body.find('FeedFetchService::fromRuntimeConfiguration')
load_pos = body.find('->load($source)')
check(0 <= owner_pos < service_pos < load_pos, 'owner-scoped content lookup occurs before any cache/service access')
check("$input['url']" not in body and '$input["url"]' not in body, 'client cannot select cache key or raw outbound URL')
check('api_safe_feed_payload($resultFeed, $effectiveUrl)' in body, 'cache and network results retain the XSS-safe API payload boundary')
check("'cache_status'" not in re.search(r'return api_success\(\[(?P<body>.*?)\]\);', body, re.S).group('body'), 'internal cache status is not exposed by the public API')

check('/var/cache/feed/*' in gitignore and '!/var/cache/feed/.gitkeep' in gitignore, 'runtime Feed cache is ignored while placeholder remains versioned')
check((ROOT / 'var/cache/.gitkeep').is_file() and (ROOT / 'var/cache/feed/.gitkeep').is_file(), 'private cache directory placeholders exist')
check('feed_cache' not in schema.lower(), 'M1-E adds no database cache table or column')
check('feedcache' not in index.lower() and 'cache_status' not in index.lower(), 'Frontend does not depend on cache internals')

m1e_stack = '\n'.join([cache, entry, lock, service, transport])
for forbidden in ['PDO(', 'mysqli_', 'Redis', 'Memcached', 'session_start(', 'setcookie(']:
    check(forbidden not in m1e_stack, f'cache layer has no unrelated persistence/session dependency: {forbidden}')
check('ETag' not in m1e_stack and 'Last-Modified' not in m1e_stack and 'If-None-Match' not in m1e_stack, 'M1-E does not implement deferred M1-F conditional requests')
check('array_unique' not in service and 'unset($feed[' not in service, 'M1-E does not remove or reorder duplicate Feed items')

if not all(checks):
    raise SystemExit(1)
print(f'All {len(checks)} M1-E architecture/static checks passed.')
