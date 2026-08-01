from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
FEED = ROOT / 'app' / 'feed'
identity = (FEED / 'item_identity.php').read_text(encoding='utf-8')
resolver = (FEED / 'item_identity_resolver.php').read_text(encoding='utf-8')
item = (FEED / 'normalized_item.php').read_text(encoding='utf-8')
parser = (FEED / 'feed_parser.php').read_text(encoding='utf-8')
helper = (FEED / 'feed_xml_helper.php').read_text(encoding='utf-8')
rss2 = (FEED / 'adapters' / 'rss2_adapter.php').read_text(encoding='utf-8')
rss1 = (FEED / 'adapters' / 'rss1_adapter.php').read_text(encoding='utf-8')
atom = (FEED / 'adapters' / 'atom_adapter.php').read_text(encoding='utf-8')
api = (ROOT / 'app' / 'api.php').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app' / 'bootstrap.php').read_text(encoding='utf-8')
index = (ROOT / 'public' / 'index.php').read_text(encoding='utf-8')
schema = (ROOT / 'database' / 'schema.sql').read_text(encoding='utf-8')
stock_schema = schema.lower()

checks = []
def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

check('final class ItemIdentity' in identity, 'ItemIdentity is an explicit final value object')
check("BASIS_SOURCE_ID = 'source-id'" in identity and "BASIS_LINK = 'link'" in identity and "BASIS_FINGERPRINT = 'fingerprint'" in identity, 'identity basis values are explicit and bounded')
check("m1i:v1:" in identity and '[a-f0-9]{64}' in identity, 'identity value has a versioned opaque SHA-256 format')
check('final class ItemIdentityResolver' in resolver, 'identity generation lives in a dedicated final resolver')
check("'m1-item-identity-v1'" in resolver and "hash('sha256'" in resolver, 'identity algorithm is explicitly versioned and deterministic')
check("'m1-item-fingerprint-v1'" in resolver, 'fallback fingerprint input is separately versioned')
check('sourceItemId' in item and '?ItemIdentity $identity = null' in item, 'NormalizedItem carries internal source ID and identity metadata')
check('function withIdentity' in item, 'identity is attached by immutable copy')

m = re.search(r'function toArray\(\): array\s*\{(?P<body>.*?)\n\s*\}', item, re.S)
to_array = m.group('body') if m else ''
for public_key in ['title', 'link', 'description', 'content', 'date']:
    check(f"'{public_key}'" in to_array, f'public array retains {public_key}')
check("'identity'" not in to_array and "'sourceItemId'" not in to_array, 'internal identity metadata is not exposed by public array')

check("FeedXmlHelper::firstText($item, ['guid'])" in rss2, 'RSS 2.0 adapter extracts guid')
check("FeedXmlHelper::attribute($item, 'about', self::RDF_NAMESPACE)" in rss1, 'RSS 1.0 adapter extracts rdf:about')
check("FeedXmlHelper::firstText($entry, ['id'])" in atom, 'Atom adapter extracts entry id')
check("RDF_NAMESPACE = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#'" in rss1, 'RSS 1.0 identity uses the RDF namespace explicitly')
check('firstText(' in helper and 'attribute(' in helper, 'shared XML helper exposes bounded identity extraction helpers')

check('?string $sourceUrl = null' in parser, 'legacy parser calls remain compatible through optional source scope')
check('attachIdentities($feed, $sourceUrl)' in parser, 'parser attaches identities only after adapter normalization')
check('$this->identityResolver->resolve($item, $sourceUrl)' in parser, 'all normalized items use the centralized resolver')
check('parse_start($feedBody, $source->url)' in api, 'API passes validated configured FeedSource URL as identity scope')
check('$effectiveUrl' not in re.search(r'\$parser = new FeedParser\(\);(?P<body>.*?return api_success)', api, re.S).group('body').split('parse_start',1)[1].split(';',1)[0], 'redirect effective URL is not used as identity scope')
check("$input['url']" not in api and '$input["url"]' not in api, 'client cannot provide identity scope URL')

check('/feed/item_identity.php' in bootstrap and '/feed/item_identity_resolver.php' in bootstrap, 'bootstrap loads identity modules')
check(bootstrap.index('/feed/item_identity.php') < bootstrap.index('/feed/normalized_item.php') < bootstrap.index('/feed/item_identity_resolver.php') < bootstrap.index('/feed/feed_parser.php'), 'identity modules load in dependency order')

identity_stack = '\n'.join([identity, resolver, item, rss2, rss1, atom, parser])
for forbidden in ['app_safe_http_fetch(', 'curl_', 'stream_socket_client(', 'file_get_contents("http', "file_get_contents('http"]:
    check(forbidden not in identity_stack, f'identity and adapter layers perform no outbound I/O: {forbidden}')
for forbidden in ['error_log(', 'syslog(', 'var_dump(', 'print_r(']:
    check(forbidden not in resolver and forbidden not in identity, f'identity implementation does not log raw candidate data: {forbidden}')

check('sourceitemid' not in index.lower() and 'itemidentity' not in index.lower() and 'm1i:v1:' not in index.lower(), 'Frontend does not depend on internal item identity metadata')
check('item_identity' not in stock_schema and 'source_item_id' not in stock_schema, 'database schema receives no item identity column in M1-D')
check('content_stock' in stock_schema, 'existing Stock schema remains present')
check('INSERT INTO' not in resolver and 'UPDATE ' not in resolver and 'DELETE ' not in resolver, 'identity resolver has no persistence side effects')
check('array_unique' not in parser and 'unset($feed[' not in parser, 'M1-D does not remove duplicate items')
check('ETag' not in identity_stack and 'Last-Modified' not in identity_stack, 'M1-D does not implement M1-F conditional requests')
check('sleep(' not in identity_stack and 'usleep(' not in identity_stack, 'M1-D does not implement retry/backoff')

if not all(checks):
    raise SystemExit(1)
print(f'All {len(checks)} M1-D architecture/static checks passed.')
