from pathlib import Path
import re

from dashboard_source_utils import dashboard_source
ROOT = Path(__file__).resolve().parents[1]
index = dashboard_source(ROOT)
dashboard = (ROOT / 'public' / 'js' / 'dashboard.js').read_text(encoding='utf-8')
frontend = index + '\n' + dashboard
api_endpoint = (ROOT / 'public/api_v1.php').read_text(encoding='utf-8')
logout = (ROOT / 'public/logout.php').read_text(encoding='utf-8')
http_fetch = (ROOT / 'app/http_fetch.php').read_text(encoding='utf-8')
common_func = (ROOT / 'app/common/common_func.php').read_text(encoding='utf-8')
feed_parser = (ROOT / 'app/feed/feed_parser.php').read_text(encoding='utf-8')
feed_helper = (ROOT / 'app/feed/feed_xml_helper.php').read_text(encoding='utf-8')
validation = (ROOT / 'app/validation.php').read_text(encoding='utf-8')

def check(cond: bool, msg: str) -> None:
    print(('PASS' if cond else 'FAIL') + ': ' + msg)
    if not cond:
        raise AssertionError(msg)

# One CSRF gate protects every API action before dispatch.
csrf_pos = api_endpoint.find('app_csrf_is_valid')
dispatch_pos = api_endpoint.find('api_dispatch(')
check(csrf_pos >= 0 and dispatch_pos > csrf_pos, 'API validates CSRF before dispatching any state-changing action')
check("($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'" in api_endpoint, 'API rejects non-POST methods')
check('app_csrf_is_valid($csrfToken)' in logout and "'POST'" in logout, 'logout is POST-only and CSRF protected')
check("$token === 'login' || $token === 'regist'" in index and 'app_csrf_is_valid($submittedCsrf)' in index, 'login and registration share explicit CSRF validation')
check("'csrf_token': appCsrfToken()" in dashboard, 'AJAX helper injects CSRF token into every API request')

for action in ['content.create', 'content.update', 'content.delete', 'stock.create', 'stock.delete', 'settings.update', 'tabs.update', 'feed.fetch', 'feed.new.clear']:
    check(action in frontend or action in (ROOT / 'app/api.php').read_text(encoding='utf-8'), f'expected API action remains represented: {action}')

# 4-tab regression mapping is generated from location 0..3.
check('for ($tabLocation = 0; $tabLocation <= 3; $tabLocation++)' in index, 'drawer renders exactly locations 0 through 3')
check("$tabLabelKey = 'conf_style_tabname' . ($tabLocation + 1)" in index, 'tab label index is location + 1')
check("app_tab_from_query($_GET['tab'] ?? null)" in index, 'query tab is normalized through strict validation helper')
check("$tabParam === 'stock'" in index, 'Stock uses explicit non-numeric tab branch')
check("return 0;" in validation[validation.find('function app_tab_from_query'):validation.find('function app_validate_enum')], 'invalid tab falls back safely to tab 0')

# TLS/network invariants and the SB-14 explicit special-range hardening.
check('CURLOPT_FOLLOWLOCATION => false' in http_fetch, 'automatic redirect following remains disabled')
check('CURLOPT_SSL_VERIFYPEER => true' in http_fetch and 'CURLOPT_SSL_VERIFYHOST => 2' in http_fetch, 'TLS certificate and hostname verification remain enabled')
for cidr in ['100.64.0.0/10', '192.0.2.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', 'fc00::/7', 'fe80::/10', 'ff00::/8']:
    check(cidr in http_fetch, f'SSRF special-use deny list contains {cidr}')

# Parser should make a single direct-link XPath query (R2 accidentally duplicated this harmless call).
needle = "$links = $xml->xpath('./*[local-name()=\"link\"]');"
check(feed_helper.count(needle) == 1, 'Atom link XPath is evaluated once per element')
check('LIBXML_NONET' in feed_parser, 'parser forbids XML network access')
check('rendered < itemLimit' in dashboard and 'rendered++' in dashboard, 'frontend safely caps rendered items to five')

print('All SB-14 surface/static checks passed.')
