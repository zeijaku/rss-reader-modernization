from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
api = (ROOT / 'app' / 'api.php').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app' / 'bootstrap.php').read_text(encoding='utf-8')
common = (ROOT / 'app' / 'common' / 'common_func.php').read_text(encoding='utf-8')
source = (ROOT / 'app' / 'feed' / 'feed_source.php').read_text(encoding='utf-8')
fetcher = (ROOT / 'app' / 'feed' / 'feed_fetcher.php').read_text(encoding='utf-8')
parser = (ROOT / 'app' / 'feed' / 'feed_parser.php').read_text(encoding='utf-8')
model = (ROOT / 'app' / 'feed' / 'normalized_item.php').read_text(encoding='utf-8')
index = (ROOT / 'public' / 'index.php').read_text(encoding='utf-8')

checks = []
def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

# Responsibility separation and module loading.
check("require_once __DIR__ . '/feed/feed_fetcher.php';" in bootstrap, 'bootstrap loads FeedFetcher module centrally')
check("require_once __DIR__ . '/feed/feed_parser.php';" in bootstrap, 'bootstrap loads FeedParser module centrally')
check(bootstrap.index("/feed/feed_fetcher.php") < bootstrap.index("/common/common_func.php"), 'FeedFetcher loads before Legacy common compatibility helpers')
check(bootstrap.index("/feed/feed_parser.php") < bootstrap.index("/common/common_func.php"), 'FeedParser loads before Legacy common compatibility helpers')
check("require_once dirname(__DIR__) . '/feed/feed_parser.php';" in common, 'direct common_func consumers retain parser compatibility without bootstrap')
check('class rss_parse' not in common and 'class FeedParser' not in common, 'parser implementation is removed from common_func')
check('class FeedFetcher' in fetcher and 'app_safe_http_fetch($source->url)' in fetcher, 'FeedFetcher owns transport delegation while retaining SB-09 implementation')
check('class FeedParser' in parser and 'class rss_parse extends FeedParser' in parser, 'FeedParser is explicit while Legacy parser name remains a compatibility alias')

# API orchestration should use boundaries instead of implementation details.
m = re.search(r'function api_feed_fetch\([^)]*\): array\s*\{(?P<body>.*?)(?=\n\})', api, re.S)
body = m.group('body') if m else ''
check(bool(m), 'feed.fetch handler exists')
check('new FeedFetcher()' in body and '->fetch($source)' in body, 'feed.fetch uses FeedFetcher boundary')
check('new FeedParser()' in body and '->parse_start($feedBody)' in body, 'feed.fetch uses FeedParser boundary')
check('app_safe_http_fetch(' not in body, 'feed.fetch no longer calls HTTP transport implementation directly')
check('new rss_parse()' not in body, 'feed.fetch no longer instantiates Legacy parser name')

# Security ordering from SB-06/SB-09 must stay intact.
owned_pos = body.find('find_owned_active_content')
validate_pos = body.find('app_validate_feed_url')
source_pos = body.find('fromOwnedContent')
fetch_pos = body.find('new FeedFetcher()')
check(owned_pos >= 0 and validate_pos > owned_pos and source_pos > validate_pos and fetch_pos > source_pos, 'owner lookup, stored-URL validation, and FeedSource mapping still occur before outbound fetch')
check("$input['url']" not in body and '$input["url"]' not in body, 'feed.fetch still does not accept a client-supplied Feed URL')
check("['invalid_url', 'port_not_allowed', 'dns_failed', 'non_public_address', 'invalid_redirect']" in body, 'blocked outbound error classification is preserved')
check("api_safe_feed_payload($resultFeed, $effectiveUrl)" in body, 'XSS-safe API payload boundary remains after parsing')

# M1-02 normalized item model is actively used by parser, not dead scaffolding.
check('final class NormalizedItem' in model, 'NormalizedItem model exists')
for field in ['title', 'link', 'description', 'content', 'date']:
    check(f'public readonly' in model and f'${field}' in model, f'NormalizedItem includes typed {field} field')
check('new NormalizedItem(' in parser, 'FeedParser constructs normalized item objects')
check('parse_normalized' in parser and 'parse_start' in parser, 'parser exposes normalized path and compatibility array path')
check('instanceof NormalizedItem' in parser and '->toArray()' in parser, 'compatibility adapter explicitly converts normalized items to SB-15 arrays')

# Parser security/behavior invariants remain in the extracted module.
check('LIBXML_NONET' in parser, 'XML parser still forbids network access')
check('@simplexml_load_string' not in parser, 'XML parser errors are still not suppressed')
check("$rootName === 'feed'" in parser and "$rootName === 'rss'" in parser and "$rootName === 'rdf'" in parser, 'RSS 2.0 / RSS 1.0 / Atom recognition remains intact')
check('rss_select_link_candidate($candidates)' in parser, 'Atom/RSS link-selection behavior remains centralized')
check("return $date->format('Y-m-d H:i:s');" in parser, 'existing date-normalization output remains unchanged in M1-A')

# M1-A deliberately avoids frontend/cache/database scope creep.
check("Math.min(5, items.length)" in index, 'existing frontend feed item cap remains unchanged')
check('ETag' not in fetcher and 'Last-Modified' not in fetcher, 'M1-A does not prematurely implement conditional HTTP caching')

if not all(checks):
    raise SystemExit(1)
print(f'All {len(checks)} M1-A architecture/static checks passed.')
