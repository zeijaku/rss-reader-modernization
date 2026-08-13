from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
INDEX = ROOT / 'public/index.php'
WIDGETS = ROOT / 'app/view/dashboard_widgets.php'
MODALS = ROOT / 'app/view/dashboard_modals.php'

index = INDEX.read_text(encoding='utf-8')
widgets = WIDGETS.read_text(encoding='utf-8')
modals = MODALS.read_text(encoding='utf-8')
failures = []

def check(condition: bool, message: str) -> None:
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failures.append(message)

widget_marker = "<?php require dirname(__DIR__) . '/app/view/dashboard_widgets.php'; ?>"
modal_marker = "<?php require dirname(__DIR__) . '/app/view/dashboard_modals.php'; ?>"

# Structure / coarse-grained split.
check(INDEX.is_file(), 'Dashboard entry point exists')
check(WIDGETS.is_file(), 'Dashboard Widget View exists')
check(MODALS.is_file(), 'Dashboard Modal View exists')
check(index.count(widget_marker) == 1, 'index includes Widget View exactly once')
check(index.count(modal_marker) == 1, 'index includes Modal View exactly once')
check(index.index(widget_marker) < index.index(modal_marker), 'Widget View is included before Modal View')
check(not any((ROOT / 'app/view').glob('dashboard_widget_*.php')), 'no per-Widget View fragmentation was introduced')
check(len(list((ROOT / 'app/view').glob('dashboard_*.php'))) == 2, 'Dashboard split uses exactly two coarse internal Views')

# Widget coverage remains together.
for needle, label in [
    ("$widgetType === 'feed'", 'Feed Widget remains in Widget View'),
    ("$widgetType === 'search'", 'Search Feed Widget remains in Widget View'),
    ("$widgetType === 'clock'", 'Clock Widget remains in Widget View'),
    ("$widgetType === 'game'", 'Game Widget remains in Widget View'),
    ("$widgetType === 'memo'", 'Memo Widget remains in Widget View'),
    ("$widgetType === 'task'", 'Task Widget remains in Widget View'),
    ("$widgetType === 'links'", 'Links Widget remains in Widget View'),
    ("$widgetType === 'weather'", 'Weather Widget remains in Widget View'),
    ("$widgetType === 'calendar'", 'Calendar Widget remains in Widget View'),
]:
    check(needle in widgets, label)

check('<main id="main-content"' in widgets, 'Widget View owns Dashboard main landmark')
check('</main><!-- /igcontainer -->' in widgets, 'Widget View closes Dashboard main landmark')
check('search_dashboard_widgets(' in widgets, 'Widget View keeps existing Dashboard read helper')
check('data-dashboard-widget-id=' in widgets, 'Widget View keeps Widget identity hooks')
check('data-dashboard-widget-type=' in widgets, 'Widget View keeps Widget type hooks')
check('id="widget-sort-help"' in widgets, 'Widget View keeps Widget sort accessibility help')

# Modal coverage remains together.
for needle, label in [
    ('id="registerContent"', 'RSS add Modal remains in Modal View'),
    ('id="changeContent"', 'RSS change Modal remains in Modal View'),
    ('id="registerSearchFeed"', 'Search Feed add Modal remains in Modal View'),
    ('id="registerClock"', 'Clock add Modal remains in Modal View'),
    ('id="registerMemo"', 'Memo add Modal remains in Modal View'),
    ('id="registerTaskWidget"', 'Task add Modal remains in Modal View'),
    ('id="registerGameWidget"', 'Game add Modal remains in Modal View'),
    ('id="registerLinksWidget"', 'Links add Modal remains in Modal View'),
    ('id="registerWeatherWidget"', 'Weather add Modal remains in Modal View'),
    ('id="registerCalendarWidget"', 'Calendar add Modal remains in Modal View'),
    ('id="accountSettings"', 'Account Settings Modal remains in Dashboard Modal View'),
    ('id="saveContent"', 'Article Stock save Modal remains in Modal View'),
]:
    check(needle in modals, label)

# Entry-point responsibilities remain at the page shell.
check("require_once dirname(__DIR__) . '/app/bootstrap.php';" in index, 'index retains bootstrap')
check('app_session_start();' in index, 'index retains Session start')
check('app_send_private_no_store_headers();' in index, 'index retains private no-store headers')
check('$currentUserId = app_session_user_id();' in index, 'index retains authentication state lookup')
check("if ($tabParam === 'stock')" in index, 'index retains legacy Stock URL compatibility redirect')
check("header('Location: ' . $stockRedirectUrl, true, 302);" in index, 'index retains Stock compatibility 302')
check('<header class="app-header">' in index, 'index retains Dashboard Header')
check('id="drawerMenu"' in index, 'index retains Drawer')
check('data-app-version' in index, 'index retains Footer version marker')
check("app_asset_url('js/dashboard.js')" in index, 'index retains Dashboard JavaScript asset')
check("app_asset_url('css/dashboard.css')" in index, 'index retains Dashboard CSS asset')

# Views stay internal and do not create new request / mutation boundaries.
check('app_session_start();' not in widgets and 'app_session_start();' not in modals, 'Views do not start their own Session')
check('$_GET' not in widgets and '$_GET' not in modals, 'Views do not parse GET requests')
check('$_POST' not in widgets and '$_POST' not in modals, 'Views do not parse POST requests')
check('new PDO' not in widgets and 'new PDO' not in modals, 'Views do not create direct PDO connections')
check('api_v1.php' not in widgets, 'Widget View adds no new mutation endpoint')
check('<?php declare(strict_types=1);' not in widgets and '<?php declare(strict_types=1);' not in modals, 'Views are internal fragments rather than standalone entry points')

# Encoding / whitespace discipline.
for path, label in [(INDEX, 'index'), (WIDGETS, 'Widget View'), (MODALS, 'Modal View')]:
    raw = path.read_bytes()
    check(not raw.startswith(b'\xef\xbb\xbf'), f'{label} has no UTF-8 BOM')
    check(b'\r\n' not in raw and b'\r' not in raw, f'{label} keeps LF line endings')

if failures:
    raise SystemExit(1)
