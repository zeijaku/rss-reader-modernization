from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
failures: list[str] = []

def check(condition: bool, message: str) -> None:
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failures.append(message)

version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
widget = (ROOT / 'app/dashboard_widget.php').read_text(encoding='utf-8')
search = (ROOT / 'app/search_feed.php').read_text(encoding='utf-8')
game = (ROOT / 'app/mini_game.php').read_text(encoding='utf-8')
api = (ROOT / 'app/api.php').read_text(encoding='utf-8')
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
dash_js = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')
calendar_js = (ROOT / 'public/js/calendar.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
schema = (ROOT / 'database/schema.sql').read_text(encoding='utf-8')
migration = (ROOT / 'database/migrations/008_v1_7_widget_height.sql').read_text(encoding='utf-8')
preflight = (ROOT / 'database/audit/v1_7_h_preflight.sql').read_text(encoding='utf-8')
postflight = (ROOT / 'database/audit/v1_7_h_postflight.sql').read_text(encoding='utf-8')
run = (ROOT / 'tests/run.sh').read_text(encoding='utf-8')

check(re.search(r"const APP_VERSION = '(?:1\.7\.0-dev\.(?:9|10)|1\.7\.0)';", version) is not None, 'Application Version is V1.7-H/R3 or later R4 checkpoint')
check("RSS Reader Modernization V1.7-H / R3" in version or "RSS Reader Modernization V1.7-H / R4" in version or "RSS Reader Modernization 1.7.0" in version, 'Application Label is V1.7-H/R3 or R4')

for rel in [
    'database/migrations/008_v1_7_widget_height.sql',
    'database/audit/v1_7_h_preflight.sql',
    'database/audit/v1_7_h_postflight.sql',
]:
    check((ROOT / rel).is_file(), rel + ' exists')
check('`widget_height` TINYINT UNSIGNED NOT NULL DEFAULT 1' in schema, 'new installs include widget_height default 1')
check('ADD COLUMN `widget_height` TINYINT UNSIGNED NOT NULL DEFAULT 1' in migration and re.search(r'FROM\s+information_schema', migration, re.I) is None, 'Migration 008 adds height without information_schema query')
check('NOT IN (1, 2)' in migration, 'Migration normalizes invalid stored heights')
check('SHOW COLUMNS' in preflight and 'SHOW COLUMNS' in postflight and re.search(r'FROM\s+information_schema', preflight, re.I) is None and re.search(r'FROM\s+information_schema', postflight, re.I) is None, 'preflight and postflight inspect the target column without information_schema query')
check("SET @table_prefix = 'ig_';" in migration and "SET @table_prefix = 'ig_';" in preflight and "SET @table_prefix = 'ig_';" in postflight, 'all SQL files expose the configurable prefix')

check('function dashboard_widget_validate_height' in widget and 'height <= 2' in widget, 'backend accepts only heights 1 and 2')
check("$row['widget_height'] = $height;" in widget, 'normalized Widget row exposes its height')
check('w.widget_width, w.widget_height, w.widget_style' in widget, 'Dashboard query selects Widget height')
check("'widget_height' => $row['widget_height']" in widget, 'public Widget list includes height')
for source, label in [(widget, 'Feed/Clock/Memo/Task/Calendar'), (search, 'Search Feed'), (game, 'Game')]:
    check('widget_height' in source and ':height' in source, label + ' persistence includes Widget height')
check(api.count("dashboard_widget_validate_height($input['widget_height']") >= 14, 'all Widget create/update API paths validate height')
check(api.count('widget_height must be 1 or 2') >= 5 or 'settings are invalid' in api, 'API rejects invalid height without coercion')

check('dashboard-grid' in index and 'data-widget-height="' in index, 'Dashboard markup exposes Grid and height hooks')
check(index.count('>縦2段</option>') >= 13 and 'SearchHeight' in index, 'all seven Widget add/edit form pairs offer vertical height')
for name in ['Content', 'Clock', 'Memo', 'TaskWidget', 'Game', 'CalendarWidget']:
    check(f'register{name}Height' in index and f'change{name}Height' in index, name + ' add/edit forms include height controls')
check('SearchHeight' in index and "$p === 'change'" in index, 'Search add/edit forms include height controls')
check(dash_js.count("'widget_height'") >= 6, 'Dashboard JavaScript sends Widget height')
check('data-widget-height' in dash_js and '.val(String(' in dash_js, 'Dashboard edit modals restore the current height')
check("'widget_height'" in calendar_js and 'data-widget-height' in calendar_js, 'Calendar JavaScript sends and restores height')

check('grid-template-columns: repeat(4, minmax(0, 1fr))' in css, 'Desktop Dashboard uses four CSS Grid columns')
check(css.count('grid-auto-rows: minmax(320px, auto)') >= 2, 'Desktop and Tablet use the 320px minimum row unit')
check('[data-widget-height="2"] { grid-row: span 2; }' in css, 'height 2 spans two Grid rows')
check('grid-template-columns: repeat(2, minmax(0, 1fr))' in css, 'Tablet Dashboard uses two columns')
check('grid-template-columns: minmax(0, 1fr)' in css and 'grid-auto-rows: auto' in css, 'Smartphone Dashboard returns to one auto-height column')
check('grid-auto-flow: dense' not in css, 'dense packing is not used, preserving DOM and keyboard order')
check('overflow-x: hidden' in css and '.feed-card-inner.is-scrollable-y' in css, 'R2 removes universal two-axis scrolling and scopes vertical overflow')

check('test_v17h_widget_height.php' in run and 'test_v17h_dashboard_render.py' in run and 'test_v17h_architecture.py' in run, 'main runner includes V1.7-H tests')
check(not any('v1_7_h' in p.name.lower() for p in (ROOT / 'config').glob('*')), 'V1.7-H adds no configuration file')
check('remember_token' not in migration.lower(), 'Widget migration does not alter Remember Token storage')

if failures:
    raise SystemExit(f'{len(failures)} V1.7-H architecture checks failed')
print('All V1.7-H architecture checks passed.')
