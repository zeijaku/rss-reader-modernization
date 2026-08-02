from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
index = (ROOT / 'public' / 'index.php').read_text(encoding='utf-8')
dashboard = (ROOT / 'public' / 'js' / 'dashboard.js').read_text(encoding='utf-8')
frontend = index + '\n' + dashboard
api = (ROOT / 'app' / 'api.php').read_text(encoding='utf-8')
feed_service = (ROOT / 'app' / 'feed' / 'feed_fetch_service.php').read_text(encoding='utf-8')
common_func = (ROOT / 'app' / 'common' / 'common_func.php').read_text(encoding='utf-8')
feed_parser = (ROOT / 'app' / 'feed' / 'feed_parser.php').read_text(encoding='utf-8')
feed_helper = (ROOT / 'app' / 'feed' / 'feed_xml_helper.php').read_text(encoding='utf-8')
feed_date = (ROOT / 'app' / 'feed' / 'feed_date_normalizer.php').read_text(encoding='utf-8')
rss2_adapter = (ROOT / 'app' / 'feed' / 'adapters' / 'rss2_adapter.php').read_text(encoding='utf-8')
rss1_adapter = (ROOT / 'app' / 'feed' / 'adapters' / 'rss1_adapter.php').read_text(encoding='utf-8')
atom_adapter = (ROOT / 'app' / 'feed' / 'adapters' / 'atom_adapter.php').read_text(encoding='utf-8')
common_db = (ROOT / 'app' / 'common' / 'common_db.php').read_text(encoding='utf-8')
common_conf = (ROOT / 'app' / 'common' / 'common_conf.php').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app' / 'bootstrap.php').read_text(encoding='utf-8')
validation = (ROOT / 'app' / 'validation.php').read_text(encoding='utf-8')
root_ht = (ROOT / '.htaccess').read_text(encoding='utf-8')
public_ht = (ROOT / 'public' / '.htaccess').read_text(encoding='utf-8')
version = (ROOT / 'app' / 'version.php').read_text(encoding='utf-8')

checks = []
def check(cond: bool, msg: str) -> None:
    print(('PASS' if cond else 'FAIL') + ': ' + msg)
    checks.append(bool(cond))

# SB-11-01: exact four-tab mapping.
check("for ($tabLocation = 0; $tabLocation <= 3; $tabLocation++)" in index, 'drawer renders all four tab locations 0..3 from one mapping')
check("'conf_style_tabname' . ($tabLocation + 1)" in index, 'drawer maps location N to tab name N+1')
check('href="./?tab=2"' not in index or "for ($tabLocation" in index, 'Legacy hard-coded 0,2,3,3 drawer mapping removed')
check("'conf_style_tabname' . ($tabParam + 1)" in index, 'navbar title uses the same 0-based location to 1-based label mapping')
check('value="<?php echo app_html((string) $addTargetLocation); ?>"' in index and '$addTargetLocation = is_int($tabParam) ? $tabParam : 0;' in index, 'RSS create hidden location uses current validated tab location')

# SB-11-02/03: parser semantics and item bounds.
check("$feedType = rss_check_string" not in api and 'FeedFetchService::fromRuntimeConfiguration()' in api and '$this->parser->parse_start(' in feed_service, 'API always parses network/cache response through the explicit parser boundary')
check("'invalid_feed'" in api and 'supported RSS or Atom feed' in api, 'unsupported/malformed upstream response returns structured invalid_feed error')
check("'title' => 'Text'" not in api and "'feed_type' => 'Text'" not in api, 'Legacy text-success fallback removed')
check("getName()) === 'feed'" in atom_adapter and "getName()) !== 'rss'" in rss2_adapter and "getName()) === 'rdf'" in rss1_adapter, 'dedicated adapters explicitly recognize Atom, RSS2, and RSS1 roots')
check('defaultNamespaceChildren' in feed_helper and "http://purl.org/rss/1.0/" in rss1_adapter, 'Atom/default-namespace and RSS1 namespace parsing are handled explicitly')
check("'$1UTF-8$2'" in feed_parser and 'Feed XML declaration could not be normalized.' in feed_parser, 'converted Feed bytes keep XML encoding declaration aligned to UTF-8')
check('$items = [];' in rss2_adapter and '$items = [];' in rss1_adapter and '$items = [];' in atom_adapter, 'zero-item feeds remain valid adapter results')
check('rendered < 5' in dashboard and 'rendered++' in dashboard, 'browser only renders available items up to five')

# SB-11-04: close partial rows for Feed and Stock branches.
check("echo '</div><!-- /feed-grid -->';" in index and "echo '</div><!-- /stock-grid -->';" in index, 'Feed/Stock responsive grid rows are explicitly closed')
check('$row_cnt' not in index and 'row content-grid feed-grid' in index and 'row content-grid stock-grid' in index, 'legacy grid row counter is replaced by responsive grid wrapping')
check('rsort($result_stock)' not in index, 'Stock order is not re-sorted incorrectly after ordered DB query')

# SB-11-05/06: tab update is isolated and one request path.
match = re.search(r'function api_tabs_update\([^)]*\): array\s*\{(?P<body>.*?)\n\}', api, re.S)
body = match.group('body') if match else ''
check(bool(match), 'tabs.update handler exists')
check('update_content' not in body and 'delete_content' not in body, 'tabs.update contains no stray content mutation logic')
check(".on('submit' + eventNamespace, '#tabsForm'" in dashboard and 'event.preventDefault();' in dashboard, 'tabs form uses AJAX submit with preventDefault')
check('type="submit" class="btn btn-primary submit_tab"' in index, 'tab button uses the single form submit path')

# SB-11-07/08: settings persistence/current values.
check('$_SESSION' not in api, 'settings/tab API does not cache mutable UI settings in Session')
check('function app_selected_attr' in validation and 'function app_checked_attr' in validation, 'selected/checked output helpers exist')
check("app_selected_attr($ui['conf_style']" in index and "app_selected_attr($ui['conf_style_nav']" in index, 'theme and navbar selects render stored current values')
check('app_checked_attr($ui[$iconKey]' in index, 'navbar icon radios render stored current values')
check(".on('submit' + eventNamespace, '#settingsForm'" in dashboard and 'type="submit" class="btn btn-primary submit_setting"' in index, 'settings form uses one AJAX submit path')

# Additional functional corrections found while auditing SB-11.
check('content-edit-trigger' in index and 'data-content-style' in index, 'content edit modal carries the current style explicitly')
check("$('.changeContentStyle').val(contentStyle);" in dashboard, 'content edit modal restores current style instead of silently resetting it')
check("$('.fa-edit').on('click'" not in frontend, 'generic FontAwesome edit icons no longer trigger content-edit behavior')
check('id="saveContent"' in index, 'Stock modal no longer duplicates the content-edit modal id')
check('id="exampleModalCenterTitle"' not in index and 'aria-labelledby="changeContent"' not in index, 'modal title ids/aria references are unique instead of Legacy duplicates')
check('class="nav-link disabled"' not in index, 'configured navbar links are no longer rendered disabled')

# SB-11-09/10: previous fixes remain present.
stock_start = api.find('function api_stock_create')
stock_end = api.find('function api_settings_update')
stock_body = api[stock_start:stock_end] if stock_start >= 0 and stock_end > stock_start else ''
check('app_safe_http_fetch' not in stock_body and 'preg_match' not in stock_body, 'Stock title save does not refetch/scrape article page')
check("$_SERVER['HTTP_USER_AGENT'] ?? '-'" in common_func, 'missing HTTP_USER_AGENT is handled without warning in access log')

# SB-12-01/02: signatures/runtime settings.
check('function update_setting(' in common_db, 'update_setting exists in PDO layer')
check('short_open_tag' not in root_ht + public_ht, 'no short_open_tag runtime dependency')
check('Options +MultiViews' not in root_ht + public_ht and 'Options MultiViews' not in root_ht + public_ht, 'MultiViews is not required')
check('href="./logout"' not in index and "action=\"./logout.php\"" in index, 'logout does not depend on extensionless MultiViews routing')
check('CURLOPT_BINARYTRANSFER' not in (ROOT / 'app' / 'http_fetch.php').read_text(encoding='utf-8'), 'obsolete CURLOPT_BINARYTRANSFER is absent')

# SB-12-03/04: PHP 8 policy/gate.
check('error_reporting(E_ALL);' in bootstrap, 'runtime error policy enables E_ALL')
check("ini_set('display_errors', APP_DEBUG ? '1' : '0');" in bootstrap, 'display_errors follows APP_DEBUG')
check("ini_set('display_startup_errors', APP_DEBUG ? '1' : '0');" in bootstrap, 'display_startup_errors follows APP_DEBUG')
check("ini_set('html_errors', '0');" in bootstrap, 'HTML-formatted PHP errors are disabled')
check('PHP_VERSION_ID < 80100' in common_conf, 'runtime health check enforces PHP 8.1+ used by the codebase')
check('strtotime($date)' not in feed_parser + feed_date, 'parser no longer passes nullable dates through strtotime/date')
check('mb_internal_encoding(' not in feed_parser and 'mb_detect_order(' not in feed_parser and 'mb_language(' not in feed_parser, 'Feed parser no longer mutates global mbstring runtime settings')
check(bool(re.search(r'mb_detect_encoding\([^;]+?true\s*\)', feed_parser, re.S)), 'Feed encoding detection uses strict failure-aware detection')
check(bool(re.search(r"const APP_VERSION = '(?:(?:SB-(?:1[2-9]|[2-9]\d+)|M\d+-[A-Z]) R\d+|1\.0\.0(?:-rc\d+)?|1\.1\.0-dev\.\d+)';", version)), 'visible release marker is SB-12 or later / M-series / supported SemVer development')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} SB-11/SB-12 static checks passed.')
