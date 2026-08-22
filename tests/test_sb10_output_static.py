from pathlib import Path
import re
import sys

from dashboard_source_utils import dashboard_source
ROOT = Path(__file__).resolve().parents[1]
index = dashboard_source(ROOT)
stock = (ROOT / 'public/stock.php').read_text()
dashboard = (ROOT / 'public' / 'js' / 'dashboard.js').read_text(encoding='utf-8')
frontend = index + '\n' + dashboard
api = (ROOT / 'app/api.php').read_text(encoding='utf-8') + ''.join(path.read_text(encoding='utf-8') for path in sorted((ROOT / 'app/api').glob('*.php')))
api_content = (ROOT / 'app/api/content.php').read_text(encoding='utf-8')
validation = (ROOT / 'app/validation.php').read_text()
http_fetch = (ROOT / 'app/http_fetch.php').read_text()
common_func = (ROOT / 'app/common/common_func.php').read_text()
feed_parser = (ROOT / 'app/feed/feed_parser.php').read_text()
api_endpoint = (ROOT / 'public/api_v1.php').read_text()
version = (ROOT / 'app/version.php').read_text()
bootstrap = (ROOT / 'app/bootstrap.php').read_text()

checks = []
def check(cond, msg):
    print(('PASS' if cond else 'FAIL') + ': ' + msg)
    checks.append(bool(cond))

check("function app_html" in validation and "ENT_QUOTES | ENT_SUBSTITUTE" in validation, 'context-safe HTML escaping helper exists')
check("app_safe_ui_config" in validation, 'Legacy DB UI values are normalized before rendering')
check("app_normalize_content_style" in index, 'content style class is allowlisted at render time')
check("app_validate_stock_url" in stock and "app_html($stockDisplayTitle)" in stock, 'Stock URL/title are validated/escaped at render time')
check("rel=\"noopener noreferrer\"" in index, 'external target=_blank links are hardened with rel')

# Untrusted Feed data must not be concatenated into HTML strings.
check("append('<a href=\"'" not in frontend, 'Feed channel title/link are not concatenated into HTML')
check("var append_dom" not in frontend, 'Feed rows are no longer built as an HTML string')
check('.text(viewTitle)' in dashboard, 'Feed channel title uses text insertion')
check(".text(viewTitle)" in dashboard, 'Feed item title uses text insertion')
check(".attr('href', itemLink)" in dashboard, 'validated Feed link is assigned as an attribute')
check("data-stock-url" in dashboard and "data-stock-title" in dashboard, 'Stock modal receives data through explicit safe data attributes')
check("'stock_title': stockTitle" in dashboard, 'client sends Feed item title with Stock request')
check('info_tweet' not in frontend and 'information_modal_tweet' not in frontend and 'twitter.com/intent/tweet' not in frontend, 'removed Tweet UI leaves no dead client-side Tweet handler')
check("rendered < itemLimit" in dashboard and "rendered++" in dashboard, 'Feed renderer bounds item access rather than blindly dereferencing five entries')

# PHP-rendered DB values should not appear raw in common dangerous contexts.
raw_ui_patterns = [
    r'echo\s+\$ui\[',
    r'href="<\?php echo \$ui',
    r'value="<\?php echo \$ui',
]
for pattern in raw_ui_patterns:
    check(re.search(pattern, index) is None, f'no raw UI DB output matches {pattern}')
check("value=\"' . $result_content[$i]['content_value']" not in index, 'Feed URL hidden input is escaped instead of raw DB interpolation')
check("href=\"' . $result_stock[$i]['stock_data']" not in stock, 'Stock href is not raw DB interpolation')

check("strip_tags($text)" in api and "api_safe_feed_payload" in api, 'Feed payload is reduced to bounded text plus validated URLs')
check("app_validate_external_link" in api, 'Feed channel/item links are URL-validated before JSON response')
check("JSON_HEX_TAG" in api_endpoint and "JSON_HEX_AMP" in api_endpoint, 'JSON response applies defense-in-depth HTML-significant character escaping')

# SB-09 implementation invariants.
check("CURLOPT_FOLLOWLOCATION => false" in http_fetch, 'cURL auto-redirect following is disabled')
check("CURLOPT_SSL_VERIFYPEER => true" in http_fetch and "CURLOPT_SSL_VERIFYHOST => 2" in http_fetch, 'TLS peer and hostname verification are enabled')
check("CURLOPT_RESOLVE" in http_fetch, 'validated DNS result is pinned into cURL connection')
check("filter_var($request['host'], FILTER_VALIDATE_IP) === false" in http_fetch, 'literal IP URLs skip hostname CURLOPT_RESOLVE pin while DNS hostnames remain pinned')
check("FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE" in http_fetch, 'private/reserved IP classes are rejected')
check("APP_HTTP_MAX_BYTES" in http_fetch and "CURLOPT_WRITEFUNCTION" in http_fetch, 'response body is bounded during transfer')
check("CURLOPT_USERAGENT" in http_fetch and "$_SERVER['HTTP_USER_AGENT']" not in http_fetch and '$_SERVER["HTTP_USER_AGENT"]' not in http_fetch, 'outbound request uses fixed app UA, not browser UA')
check("LIBXML_NONET" in feed_parser, 'XML parser forbids network access')
check("@simplexml_load_string" not in feed_parser, 'XML parse errors are not hidden with @')
check("steal_contents($url)" not in api, 'API no longer calls Legacy generic fetch helper')
stock_match = re.search(r'function api_stock_create\b.*?(?=\nfunction \w+|\Z)', api_content, flags=re.S)
stock_create = stock_match.group(0) if stock_match else ''
check(bool(stock_create) and "app_safe_http_fetch" not in stock_create, 'Stock create performs no server-side article fetch')

check("require_once __DIR__ . '/validation.php';" in bootstrap and "require_once __DIR__ . '/http_fetch.php';" in bootstrap, 'validation and safe-fetch modules load centrally')
check('const APP_VERSION =' in version and 'const APP_VERSION_LABEL =' in version, 'visible release marker infrastructure remains present')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} SB-10/XSS and SB-09 static checks passed.')
