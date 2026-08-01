from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
api = (ROOT / 'app' / 'api.php').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app' / 'bootstrap.php').read_text(encoding='utf-8')
source = (ROOT / 'app' / 'feed' / 'feed_source.php').read_text(encoding='utf-8')
mapper = (ROOT / 'app' / 'feed' / 'feed_source_mapper.php').read_text(encoding='utf-8')
fetcher = (ROOT / 'app' / 'feed' / 'feed_fetcher.php').read_text(encoding='utf-8')
service = (ROOT / 'app' / 'feed' / 'feed_fetch_service.php').read_text(encoding='utf-8')
db = (ROOT / 'app' / 'common' / 'common_db.php').read_text(encoding='utf-8')
schema = (ROOT / 'database' / 'schema.sql').read_text(encoding='utf-8')
index = (ROOT / 'public' / 'index.php').read_text(encoding='utf-8')

checks = []
def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

# Explicit model / mapper / transport boundaries.
check('final class FeedSource' in source, 'FeedSource model is explicit and final')
for field in ['sourceId', 'ownerId', 'url']:
    check('public readonly' in source and f'${field}' in source, f'FeedSource includes immutable {field}')
check(re.search(r'public\s+readonly\s+[^;]+\$(?:contentStyle|contentLocation|content_style|content_location)\b', source) is None, 'FeedSource excludes presentation settings')
check('final class FeedSourceMapper' in mapper and 'fromOwnedContent' in mapper, 'owner-scoped DB row mapping has an explicit boundary')
check("$content['content_id']" in mapper and "$content['content_owner']" in mapper, 'mapper reads only source identity from content row')
check("$content['content_value']" not in mapper, 'mapper cannot bypass separately validated URL with raw DB content_value')
check('$ownerId !== $authenticatedOwnerId' in mapper, 'mapper verifies authenticated ownership again')
check('public function fetch(FeedSource $source): array' in fetcher, 'FeedFetcher no longer accepts arbitrary URL strings')
check('app_safe_http_fetch($source->url)' in fetcher, 'FeedFetcher still delegates to SB-09 transport')

# Central loading order must make types available before API/common consumers.
for module in ['/feed/feed_source.php', '/feed/feed_source_mapper.php', '/feed/feed_fetcher.php', '/feed/feed_parser.php']:
    check(module in bootstrap, f'bootstrap loads {module.rsplit("/", 1)[-1]}')
check(bootstrap.index('/feed/feed_source.php') < bootstrap.index('/feed/feed_source_mapper.php') < bootstrap.index('/feed/feed_fetcher.php'), 'FeedSource, mapper, and fetcher load in dependency order')

m = re.search(r'function api_feed_fetch\([^)]*\): array\s*\{(?P<body>.*?)(?=\n\})', api, re.S)
body = m.group('body') if m else ''
check(bool(m), 'feed.fetch handler exists')
owned_pos = body.find('find_owned_active_content')
validate_pos = body.find('app_validate_feed_url')
map_pos = body.find('fromOwnedContent')
service_pos = body.find('FeedFetchService::fromRuntimeConfiguration()')
load_pos = body.find('->load($source)')
check(owned_pos >= 0 and validate_pos > owned_pos and map_pos > validate_pos and service_pos > map_pos and load_pos > service_pos, 'feed.fetch order is owner lookup → URL validation → source mapping → cache-aware service')
check('$this->transport->fetch($source)' in service and '$this->parser->parse_start($body, $source->url)' in service, 'FeedFetchService preserves fetch → parse ordering on cache miss')
check('new FeedSourceMapper()' in body and 'FeedFetchService::fromRuntimeConfiguration()' in body, 'API orchestrates FeedSource mapping and cache-aware loading boundaries')
check('->fetch($url)' not in body and 'app_safe_http_fetch(' not in body, 'API cannot fetch a raw URL directly')
check("$input['url']" not in body and '$input["url"]' not in body, 'client-supplied Feed URL remains unsupported')
check("$fetch['url'] ?? null" in service and "? $fetch['url'] : $source->url" in service, 'effective URL fallback comes from FeedSource inside the load service')
check("api_safe_feed_payload($resultFeed, $effectiveUrl)" in body, 'SB-10 payload boundary remains after FeedSource introduction')

# No DB/frontend scope creep in M1-B.
check('CREATE TABLE' not in source and 'CREATE TABLE' not in mapper, 'FeedSource modules contain no schema ownership')
check('feed_source' not in schema.lower(), 'M1-B adds no Feed Source database table')
check('function find_owned_active_content' in db, 'existing owner-scoped content lookup remains the persistence boundary')
check("'content_id': content_id" in index, 'browser still submits only content_id for feed.fetch')
check('FeedSource' not in index, 'Frontend remains unaware of FeedSource model')
check('ETag' not in fetcher and 'Last-Modified' not in fetcher, 'M1-B does not introduce cache/conditional HTTP scope')

if not all(checks):
    raise SystemExit(1)
print(f'All {len(checks)} M1-B architecture/static checks passed.')
