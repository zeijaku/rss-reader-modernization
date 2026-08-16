from pathlib import Path
import re
from urllib.parse import urlparse

ROOT = Path(__file__).resolve().parents[1]
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
widget = (ROOT / 'app/dashboard_widget.php').read_text(encoding='utf-8')
api = (ROOT / 'app/api.php').read_text(encoding='utf-8')
search = (ROOT / 'app/search_feed.php').read_text(encoding='utf-8')
feeds = (ROOT / 'config/common_feeds.php').read_text(encoding='utf-8')
js = (ROOT / 'public/js/clock-timer.js').read_text(encoding='utf-8')

checks = []
def check(value, message):
    checks.append((bool(value), message))

# Final version contract.
check("const APP_VERSION = '1.16.0';" in version, 'APP_VERSION is final 1.16.0')
check("const APP_VERSION_LABEL = 'RSS Reader Modernization 1.16.0';" in version, 'APP_VERSION_LABEL is final 1.16.0')

# Existing Widget order must stay intact, with V1.16 types appended.
head = widget.split('function dashboard_widget_types', 1)[1].split('}', 1)[0]
for typ in ['feed', 'search', 'clock', 'memo', 'task', 'calendar', 'game', 'links', 'weather', 'sun_moon', 'air_quality', 'earthquake', 'calculator', 'blind_spot']:
    check(f"'{typ}'" in head, f'Widget type present: {typ}')
check("return ['feed', 'search', 'clock', 'memo', 'task', 'calendar', 'game'" in head, 'legacy Widget type order preserved')

# Calculator contract.
for action in ['widget.calculator.create', 'widget.calculator.update', 'widget.calculator.delete']:
    check(action in api, f'Calculator API action present: {action}')
check('function dashboard_widget_create_calculator' in widget and "widget_type = 'calculator'" in widget, 'Calculator persistence uses dashboard_widget')
check('function calculatorState()' in js and 'calculator-key' in js and 'eval(' not in js, 'Calculator client arithmetic exists without eval')

# Blind Spot persistence / rotation contract.
for action in ['widget.blindspot.create', 'widget.blindspot.update', 'widget.blindspot.delete', 'blindspot.fetch']:
    check(action in api, f'Blind Spot API action present: {action}')
check("'schema' => 2" in widget and "'last_category' => ''" in widget and "'recent_items' => []" in widget, 'Blind Spot schema 2 history config exists')
check('function dashboard_widget_blind_spot_recent_limit(): int' in widget and 'return 18;' in widget, 'Blind Spot recent history is capped at 18')
check('function dashboard_widget_blind_spot_recent_ttl_seconds(): int' in widget and 'return 86400;' in widget, 'Blind Spot history TTL is 24 hours')
check('candidate!==$previousCategory' in search, 'Blind Spot excludes the previous category')
check('isset($recentKeys[$key])' in search, 'Blind Spot suppresses recently shown articles')
check('array_slice($candidates,0,3)' in search, 'Blind Spot displays at most 3 articles')
check('FeedFetchService::fromRuntimeConfiguration()' in search and 'FeedSource::fromValidatedValues' in search, 'Blind Spot reuses safe Feed fetch pipeline')

# Shared catalog separation. Extract literal discovery entries from the PHP catalog.
entry_re = re.compile(r"\['name'\s*=>\s*'([^']+)'\s*,\s*'category'\s*=>\s*'([^']+)'\s*,\s*'url'\s*=>\s*'([^']+)'(?:\s*,\s*'discovery'\s*=>\s*true)?\]")
entries = entry_re.findall(feeds)
check(len(entries) >= 45, 'Common feed catalog contains legacy + discovery entries')
discovery_lines = [line for line in feeds.splitlines() if "'discovery' => true" in line]
check(len(discovery_lines) == 40, 'Blind Spot catalog contains exactly 40 discovery feeds')
# Categories are visible as PHP literals on discovery rows.
categories = []
urls = []
for line in discovery_lines:
    mcat = re.search(r"'category'\s*=>\s*'([^']+)'", line)
    murl = re.search(r"'url'\s*=>\s*'([^']+)'", line)
    if mcat:
        categories.append(mcat.group(1))
    if murl:
        urls.append(murl.group(1))
check(len(set(categories)) == 20, 'Blind Spot catalog contains exactly 20 categories')
check(all(categories.count(c) == 2 for c in set(categories)), 'Each Blind Spot category has exactly 2 feeds')
check(len(urls) == 40 and len(set(urls)) == 40, 'Blind Spot discovery URLs are unique')
check(all(urlparse(u).scheme == 'https' for u in urls), 'All Blind Spot feeds use HTTPS')
check(all((urlparse(u).hostname or '').endswith('.jp') for u in urls), 'All Blind Spot feeds are Japan-domain sources')
check("(($r['discovery']??false)===true)" in search, 'Search Feed excludes discovery-only rows')

# Article summary / actions / responsive UI contract.
for marker in ['blind-spot-item-summary-toggle', 'blind-spot-item-summary', 'blind-spot-item-actions', 'article-actions-trigger']:
    check(marker in js, f'Blind Spot UI marker exists: {marker}')
check("String(item.content || item.description || '')" in js or "String(item.content || '')" in js, 'Blind Spot summary uses Feed content/description')
check(".text(summaryText)" in js or ".text(summary)" in js, 'Blind Spot summary uses text rendering')
check("data-article-context" in js and "'feed'" in js, 'Blind Spot Article Actions use feed context')
check('Stockへ保存' not in js[js.find('function blindSpot'):js.find('function blindSpot') + 10000] if 'function blindSpot' in js else True, 'Blind Spot does not duplicate Stock backend/UI implementation')
check('.blind-spot-item-head{display:flex;min-width:0;min-height:44px' in js, 'Blind Spot article row keeps 44px touch height')
check('.blind-spot-item-summary-toggle{display:inline-flex;flex:0 0 36px;width:36px;min-width:36px;height:44px;min-height:44px' in js, 'Blind Spot summary control keeps 36x44 target')
check('@media (max-width:767.98px)' in js and '.blind-spot-card-inner{height:auto;min-height:11rem}' in js, 'Blind Spot smartphone layout contract exists')
check('.dashboard-widget[data-widget-height="2"].blind-spot-card' in js, 'Blind Spot Height 2 layout contract exists')
for var in ['--bs-body-bg', '--bs-body-color', '--bs-secondary-color', '--bs-border-color']:
    check(var in js, f'Bootstrap theme variable retained: {var}')

# Header / drag-handle normalization from A-R1/R2 must remain.
check('v116a-r1-dashboard-header-styles' in js and 'height:44px;min-height:44px;max-height:44px' in js, 'Dashboard header normalization retained')
check('v116a-r2-dashboard-drag-handle-styles' in js or 'widget-drag-handle' in js, 'Dashboard drag-handle normalization retained')

# No schema/migration was introduced by V1.16 files.
for text, name in [(widget, 'dashboard_widget.php'), (api, 'api.php'), (search, 'search_feed.php')]:
    check('CREATE TABLE' not in text and 'ALTER TABLE' not in text, f'No V1.16 DDL in {name}')

failed = [m for ok, m in checks if not ok]
for ok, message in checks:
    print(('PASS' if ok else 'FAIL') + ': ' + message)
print(f'RESULT: PASS {len(checks) - len(failed)} / FAIL {len(failed)}')
raise SystemExit(1 if failed else 0)
