from pathlib import Path
import re

from dashboard_source_utils import dashboard_source

ROOT = Path(__file__).resolve().parents[1]
FEED = ROOT / 'app' / 'feed'
headers = (FEED / 'feed_http_headers.php').read_text(encoding='utf-8')
fetcher = (FEED / 'feed_fetcher.php').read_text(encoding='utf-8')
transport = (FEED / 'feed_transport_interface.php').read_text(encoding='utf-8')
entry = (FEED / 'feed_cache_entry.php').read_text(encoding='utf-8')
cache = (FEED / 'feed_cache.php').read_text(encoding='utf-8')
service = (FEED / 'feed_fetch_service.php').read_text(encoding='utf-8')
http_fetch = (ROOT / 'app' / 'http_fetch.php').read_text(encoding='utf-8')
conf = (ROOT / 'app' / 'common' / 'common_conf.php').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app' / 'bootstrap.php').read_text(encoding='utf-8')
env_example = (ROOT / 'config' / '.env.example').read_text(encoding='utf-8')
local_example = (ROOT / 'config' / 'local.php.example').read_text(encoding='utf-8')
api = (ROOT / 'app' / 'api.php').read_text(encoding='utf-8')
index = dashboard_source(ROOT)
schema = (ROOT / 'database' / 'schema.sql').read_text(encoding='utf-8')

checks = []
def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

check((FEED / 'feed_http_headers.php').is_file(), 'M1-F adds one small HTTP validator helper module')
check('class FeedHttp' not in headers and 'interface FeedHttp' not in headers, 'M1-F avoids an unnecessary validator class hierarchy')
check('function feed_clean_etag' in headers and 'function feed_clean_last_modified' in headers, 'ETag and Last-Modified validation stays in readable helper functions')
check("'If-None-Match: '" in headers and "'If-Modified-Since: '" in headers, 'only the two supported conditional headers are created')
check("hash_equals($resourceUrl, $requestUrl)" in headers, 'validators are scoped to the exact prior effective URL')
check('X-Unsafe' not in headers and 'foreach ($validators as' not in headers, 'arbitrary caller-supplied headers cannot pass through')

check('array $validators = []' in transport and 'array $validators = []' in fetcher, 'existing FeedSource transport boundary receives optional validators')
check('app_safe_http_fetch($source->url, null, null, $validators)' in fetcher, 'FeedFetcher still uses the SB-09 safe HTTP function')
check("'request_headers' => $requestHeaders" in http_fetch, 'conditional headers are added only after target validation')
check('app_validate_fetch_target($currentUrl, $resolver)' in http_fetch, 'every redirect hop remains subject to SSRF validation')
check("$status === 304" in http_fetch and 'unexpected_not_modified' in http_fetch, 'HTTP 304 is accepted only when a conditional header was sent')
check('CURLOPT_SSL_VERIFYPEER => true' in http_fetch and 'CURLOPT_SSL_VERIFYHOST => 2' in http_fetch, 'TLS peer and hostname verification remain enabled')
check('CURLOPT_RESOLVE' in http_fetch, 'DNS pinning remains enabled')
check('CURLOPT_FOLLOWLOCATION => false' in http_fetch, 'redirects remain manually controlled')

check('SCHEMA_VERSION = 2' in entry, 'M1-F cache payload uses schema version 2')
check("$schema !== 1 && $schema !== self::SCHEMA_VERSION" in entry, 'M1-E schema version 1 remains readable')
check("'body_fetched_at'" in entry and "'validated_at'" in entry, 'body fetch time and validation time are stored separately')
check("'etag'" in entry and "'last_modified'" in entry, 'cache stores only ETag and Last-Modified validators')
check('fromNotModified' in entry and '$cached->bodyFetchedAt' in entry, 'HTTP 304 keeps the original body fetch time')
check('feed_clean_etag' in entry and 'feed_clean_last_modified' in entry, 'cached validators are validated again before use')
check('serialize(' not in entry + cache + service and 'unserialize(' not in entry + cache + service, 'conditional cache does not use PHP serialization')

check('readStale' in cache and 'readFresh' in cache, 'fresh and stale cache reads are explicit')
check('$entry->validatedAt' in cache, 'cache freshness uses validation time')
check('writeNotModified' in cache and 'fromNotModified' in cache, 'HTTP 304 updates the existing cache envelope')
check("tempnam($this->directory" in cache and 'rename($temp, $target)' in cache, 'HTTP 304 cache updates keep atomic file replacement')

check('APP_FEED_CONDITIONAL_REQUEST_ENABLED' in service, 'runtime service reads the conditional-request switch')
check('loadStaleCache' in service and 'revalidate' in service, 'stale cache revalidation is kept in the existing service')
check(service.find('parse_start($entry->body') < service.find('$this->transport->fetch($source, $cached->validators())'), 'stale cache body is parsed before its validators can extend it')
check('CACHE_REVALIDATED' in service, 'internal result distinguishes an HTTP 304 revalidation')
check('private readonly bool $staleIfErrorEnabled = false' in service, 'M1-F constructor compatibility keeps stale-if-error disabled unless explicitly enabled')
check("'result_feed' => $cachedFeed" in service, 'HTTP 304 reuses the already validated cached Feed body')
check('writeNotModified($source, $cached, $fetch)' in service, 'HTTP 304 refreshes cache validation metadata')
check('writeSuccessfulFetch($source, $fetch)' in service, 'HTTP 200 still replaces cache only after parse success')

check('APP_FEED_CONDITIONAL_REQUEST_ENABLED' in conf, 'runtime configuration defines the M1-F switch')
check('APP_FEED_CONDITIONAL_REQUEST_ENABLED=true' in env_example, 'environment example documents the M1-F switch')
check("'APP_FEED_CONDITIONAL_REQUEST_ENABLED' => true" in local_example, 'local configuration example documents the M1-F switch')
check("'/feed/feed_http_headers.php'" in bootstrap, 'bootstrap loads the M1-F helper')
check(bootstrap.index('/feed/feed_http_headers.php') < bootstrap.index('/feed/feed_fetcher.php'), 'M1-F helper loads before FeedFetcher')

body = re.search(r'function api_feed_fetch\(.*?\n\}', api, re.S).group(0)
check('find_owned_active_content' in body and body.find('find_owned_active_content') < body.find('FeedFetchService::fromRuntimeConfiguration'), 'owner check still occurs before cache or conditional request access')
check('etag' not in body.lower() and 'last_modified' not in body.lower(), 'HTTP validators are not exposed by the public API')
check('cache_status' not in index.lower() and 'etag' not in index.lower(), 'Frontend remains independent of M1-F internals')
check('etag' not in schema.lower() and 'last_modified' not in schema.lower(), 'M1-F adds no database columns')

combined = '\n'.join([headers, fetcher, entry, cache, service, http_fetch])
for forbidden in ['Cache-Control', "'expires'"]:
    check(forbidden.lower() not in combined.lower(), f'M1-F remains independent of upstream cache-policy behavior: {forbidden}')
check('array_unique' not in service and 'unset($feed[' not in service, 'M1-F does not remove or reorder Feed items')

if not all(checks):
    raise SystemExit(1)
print(f'All {len(checks)} M1-F architecture/static checks passed.')
