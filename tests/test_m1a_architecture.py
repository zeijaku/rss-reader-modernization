from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
api = (ROOT / 'app' / 'api.php').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app' / 'bootstrap.php').read_text(encoding='utf-8')
common = (ROOT / 'app' / 'common' / 'common_func.php').read_text(encoding='utf-8')
fetcher = (ROOT / 'app' / 'feed' / 'feed_fetcher.php').read_text(encoding='utf-8')
parser = (ROOT / 'app' / 'feed' / 'feed_parser.php').read_text(encoding='utf-8')
service = (ROOT / 'app' / 'feed' / 'feed_fetch_service.php').read_text(encoding='utf-8')
model = (ROOT / 'app' / 'feed' / 'normalized_item.php').read_text(encoding='utf-8')
helper = (ROOT / 'app' / 'feed' / 'feed_xml_helper.php').read_text(encoding='utf-8')
selector = (ROOT / 'app' / 'feed' / 'feed_link_selector.php').read_text(encoding='utf-8')
date_normalizer = (ROOT / 'app' / 'feed' / 'feed_date_normalizer.php').read_text(encoding='utf-8')
adapters = ''.join((ROOT / 'app' / 'feed' / 'adapters' / name).read_text(encoding='utf-8') for name in ['rss2_adapter.php', 'rss1_adapter.php', 'atom_adapter.php'])
index = (ROOT / 'public' / 'index.php').read_text(encoding='utf-8')
dashboard = (ROOT / 'public' / 'js' / 'dashboard.js').read_text(encoding='utf-8')
frontend = index + '\n' + dashboard

checks = []
def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

check("require_once __DIR__ . '/feed/feed_fetcher.php';" in bootstrap, 'bootstrap loads FeedFetcher module centrally')
check("require_once __DIR__ . '/feed/feed_parser.php';" in bootstrap, 'bootstrap loads FeedParser module centrally')
check(bootstrap.index('/feed/feed_fetcher.php') < bootstrap.index('/common/common_func.php'), 'FeedFetcher loads before Legacy common compatibility helpers')
check(bootstrap.index('/feed/feed_parser.php') < bootstrap.index('/common/common_func.php'), 'FeedParser loads before Legacy common compatibility helpers')
check("require_once dirname(__DIR__) . '/feed/feed_parser.php';" in common, 'direct common_func consumers retain parser compatibility without bootstrap')
check('class rss_parse' not in common and 'class FeedParser' not in common, 'parser implementation is removed from common_func')
check('class FeedFetcher' in fetcher and 'app_safe_http_fetch($source->url, null, null, $validators)' in fetcher, 'FeedFetcher owns transport delegation while retaining SB-09 implementation')
check('class FeedParser' in parser and 'class rss_parse extends FeedParser' in parser, 'FeedParser is explicit while Legacy parser name remains a compatibility alias')

m = re.search(r'function api_feed_fetch\([^)]*\): array\s*\{(?P<body>.*?)(?=\n\})', api, re.S)
body = m.group('body') if m else ''
check(bool(m), 'feed.fetch handler exists')
check('FeedFetchService::fromRuntimeConfiguration()' in body and '->load($source)' in body and '$this->transport->fetch($source)' in service, 'feed.fetch reaches the FeedFetcher transport boundary through FeedFetchService')
check('$this->parser->parse_start($body, $source->url)' in service and '$this->parser->parse_start($entry->body, $source->url)' in service, 'feed.fetch reaches the FeedParser boundary for network and cache bodies')
check('app_safe_http_fetch(' not in body, 'feed.fetch no longer calls HTTP transport implementation directly')
check('new rss_parse()' not in body, 'feed.fetch no longer instantiates Legacy parser name')

owned_pos = body.find('find_owned_active_content')
validate_pos = body.find('app_validate_feed_url')
source_pos = body.find('fromOwnedContent')
fetch_pos = body.find('FeedFetchService::fromRuntimeConfiguration()')
check(owned_pos >= 0 and validate_pos > owned_pos and source_pos > validate_pos and fetch_pos > source_pos, 'owner lookup, stored-URL validation, and FeedSource mapping still occur before outbound fetch')
check("$input['url']" not in body and '$input["url"]' not in body, 'feed.fetch still does not accept a client-supplied Feed URL')
check("['invalid_url', 'port_not_allowed', 'non_public_address', 'invalid_redirect']" in body, 'SSRF policy error classification remains explicit while DNS failure stays retryable')
check("api_safe_feed_payload($resultFeed, $effectiveUrl)" in body, 'XSS-safe API payload boundary remains after parsing')

check('final class NormalizedItem' in model, 'NormalizedItem model exists')
for field in ['title', 'link', 'description', 'content', 'date']:
    check('public readonly' in model and f'${field}' in model, f'NormalizedItem includes typed {field} field')
check(adapters.count('new NormalizedItem(') == 3, 'each XML adapter constructs normalized item objects')
check('parse_normalized' in parser and 'parse_start' in parser, 'parser exposes normalized path and compatibility array path')
check('instanceof NormalizedItem' in parser and '->toArray()' in parser, 'compatibility adapter explicitly converts normalized items to SB-15 arrays')

check('LIBXML_NONET' in parser, 'XML parser still forbids network access')
check('@simplexml_load_string' not in parser, 'XML parser errors are still not suppressed')
check("getName()) === 'feed'" in adapters and "getName()) !== 'rss'" in adapters and "getName()) === 'rdf'" in adapters, 'RSS 2.0 / RSS 1.0 / Atom recognition remains intact')
check('FeedLinkSelector::select($candidates)' in helper and 'function rss_select_link_candidate' in selector, 'Atom/RSS link-selection behavior remains centralized with compatibility wrapper')
check("return $date->format('Y-m-d H:i:s');" in date_normalizer and 'function rss_normalize_date' in date_normalizer, 'existing date-normalization output and compatibility wrapper remain unchanged')

check("Math.min(5, items.length)" in dashboard, 'existing frontend feed item cap remains unchanged')
check('api_safe_feed_payload($resultFeed, $effectiveUrl)' in body, 'later HTTP improvements do not bypass the M1-A public payload boundary')

if not all(checks):
    raise SystemExit(1)
print(f'All {len(checks)} M1-A architecture/static checks passed.')
