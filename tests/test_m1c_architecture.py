from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
FEED = ROOT / 'app' / 'feed'
parser = (FEED / 'feed_parser.php').read_text(encoding='utf-8')
helper = (FEED / 'feed_xml_helper.php').read_text(encoding='utf-8')
date = (FEED / 'feed_date_normalizer.php').read_text(encoding='utf-8')
selector = (FEED / 'feed_link_selector.php').read_text(encoding='utf-8')
interface = (FEED / 'adapters' / 'feed_adapter_interface.php').read_text(encoding='utf-8')
rss2 = (FEED / 'adapters' / 'rss2_adapter.php').read_text(encoding='utf-8')
rss1 = (FEED / 'adapters' / 'rss1_adapter.php').read_text(encoding='utf-8')
atom = (FEED / 'adapters' / 'atom_adapter.php').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app' / 'bootstrap.php').read_text(encoding='utf-8')
api = (ROOT / 'app' / 'api.php').read_text(encoding='utf-8')
fetcher = (FEED / 'feed_fetcher.php').read_text(encoding='utf-8')
service = (FEED / 'feed_fetch_service.php').read_text(encoding='utf-8')
source = (FEED / 'feed_source.php').read_text(encoding='utf-8')
index = (ROOT / 'public' / 'index.php').read_text(encoding='utf-8')
dashboard = (ROOT / 'public' / 'js' / 'dashboard.js').read_text(encoding='utf-8')
frontend = index + '\n' + dashboard
schema = (ROOT / 'database' / 'schema.sql').read_text(encoding='utf-8')

checks = []
def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

check('interface FeedAdapterInterface' in interface, 'FeedAdapterInterface is explicit')
check('supports(SimpleXMLElement $xml): bool' in interface and 'parse(SimpleXMLElement $xml): array' in interface, 'adapter interface defines detection and normalized parsing')
for name, body in [('Rss2Adapter', rss2), ('Rss1Adapter', rss1), ('AtomAdapter', atom)]:
    check(f'final class {name} implements FeedAdapterInterface' in body, f'{name} is a final feed adapter')
    check('new NormalizedItem(' in body, f'{name} emits NormalizedItem objects')

check("getName()) !== 'rss'" in rss2 and "'type' => 'rss2'" in rss2, 'RSS 2.0 root detection and normalized type live in Rss2Adapter')
check("getName()) === 'rdf'" in rss1 and "'type' => 'rss1'" in rss1, 'RSS 1.0 root detection and normalized type live in Rss1Adapter')
check("getName()) === 'feed'" in atom and "'type' => 'atom'" in atom, 'Atom root detection and normalized type live in AtomAdapter')
check("http://purl.org/rss/1.0/" in rss1, 'RSS 1.0 namespace handling lives in Rss1Adapter')
check("'updated', 'published'" in atom, 'Atom published fallback follows updated in adapter priority')
check('published' not in rss2 and 'published' not in rss1, 'Atom-only published field does not leak into RSS adapters')

check('new AtomAdapter()' in parser and 'new Rss2Adapter()' in parser and 'new Rss1Adapter()' in parser, 'FeedParser registers all three adapters')
check('foreach ($this->adapters as $adapter)' in parser and '->supports($xml)' in parser and '->parse($xml)' in parser, 'FeedParser only detects and dispatches through adapter interface')
for format_detail in ['->entry', '->channel->item', "children('http://purl.org/rss/1.0/')", 'new NormalizedItem(']:
    check(format_detail not in parser, f'FeedParser no longer contains format-specific extraction: {format_detail}')
check('LIBXML_NONET' in parser and '@simplexml_load_string' not in parser, 'secure XML loading remains in FeedParser')
check('class rss_parse extends FeedParser' in parser, 'Legacy parser compatibility alias remains')
check('parse_start' in parser and '->toArray()' in parser, 'Legacy array compatibility boundary remains')

feed_php = '\n'.join(path.read_text(encoding='utf-8') for path in FEED.rglob('*.php'))
check(feed_php.count('new DateTimeImmutable(') == 1 and 'new DateTimeImmutable(' in date, 'all feed date parsing is centralized in FeedDateNormalizer')
check("return $date->format('Y-m-d H:i:s');" in date, 'date output contract remains Y-m-d H:i:s')
check('function rss_normalize_date' in date and 'FeedDateNormalizer::normalize($value)' in date, 'Legacy date helper delegates to centralized normalizer')
check('function rss_select_link_candidate' in selector and 'FeedLinkSelector::select($candidates)' in helper, 'Legacy and adapter link paths share one selector')
check("xpath('./*[local-name()=\"link\"]')" in helper and "xpath('./*[local-name()=\"url\"]')" in helper, 'shared XML helper retains namespace-agnostic link and url extraction')

for module in [
    '/feed/feed_date_normalizer.php', '/feed/feed_link_selector.php', '/feed/feed_xml_helper.php',
    '/feed/adapters/feed_adapter_interface.php', '/feed/adapters/rss2_adapter.php',
    '/feed/adapters/rss1_adapter.php', '/feed/adapters/atom_adapter.php', '/feed/feed_parser.php'
]:
    check(module in bootstrap, f'bootstrap loads {module.rsplit("/", 1)[-1]}')
check(bootstrap.index('/feed/feed_date_normalizer.php') < bootstrap.index('/feed/feed_xml_helper.php') < bootstrap.index('/feed/adapters/rss2_adapter.php') < bootstrap.index('/feed/feed_parser.php'), 'M1-C modules load in dependency order')

parser_stack = '\n'.join([parser, helper, date, selector, interface, rss2, rss1, atom])
for forbidden in ['app_safe_http_fetch(', 'curl_', 'file_get_contents("http', "file_get_contents('http", 'stream_socket_client(']:
    check(forbidden not in parser_stack, f'parser/adapter layer performs no outbound HTTP operation: {forbidden}')
check('app_safe_http_fetch($source->url, null, null, $validators)' in fetcher, 'outbound HTTP remains isolated in FeedFetcher')
check('public function fetch(FeedSource $source, array $validators = []): array' in fetcher, 'M1-B FeedSource boundary remains intact')
check('FeedSource' not in index and 'feed_source' not in schema.lower(), 'M1-C adds no frontend or database FeedSource coupling')
check('ETag' not in parser_stack and 'Last-Modified' not in parser_stack, 'M1-C does not implement later conditional-cache scope')

m = re.search(r'function api_feed_fetch\([^)]*\): array\s*\{(?P<body>.*?)(?=\n\})', api, re.S)
body = m.group('body') if m else ''
check('FeedFetchService::fromRuntimeConfiguration()' in body and '$this->parser->parse_start($body, $source->url)' in service and '$this->parser->parse_start($entry->body, $source->url)' in service, 'API keeps the FeedParser compatibility boundary through cache-aware service')
check('api_safe_feed_payload($resultFeed, $effectiveUrl)' in body, 'API XSS-safe payload boundary remains unchanged')
check("$input['url']" not in body and '$input["url"]' not in body, 'client cannot supply an outbound Feed URL')
check('Math.min(5, items.length)' in dashboard, 'frontend display cap remains unchanged')

if not all(checks):
    raise SystemExit(1)
print(f'All {len(checks)} M1-C architecture/static checks passed.')
