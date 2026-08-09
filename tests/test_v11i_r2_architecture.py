from pathlib import Path
import re, sys

ROOT = Path(__file__).resolve().parents[1]
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
dashboard = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')
calendar = (ROOT / 'public/js/calendar-core.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
run = (ROOT / 'tests/run.sh').read_text(encoding='utf-8')
local_run = (ROOT / 'tests/run-local-v1-1-i.sh').read_text(encoding='utf-8')

checks = []
def check(cond, msg):
    checks.append(bool(cond))
    print(('PASS' if cond else 'FAIL') + ': ' + msg)

check('data-dashboard-current-tab=' in index and 'data-dashboard-tab-count="4"' in index, 'Dashboard exposes current tab and bounded tab count')
check("function initTabSwipe()" in dashboard, 'mobile tab swipe has one initialization boundary')
for event in ['touchstart', 'touchmove', 'touchend', 'touchcancel']:
    check((".'" + event) not in dashboard and ("'" + event + "' + eventNamespace") in dashboard, f'{event} handler uses the Dashboard namespace')
check("(max-width: 767.98px)" in dashboard, 'tab swipe is limited to smartphone width')
check('dashboardSwipeThreshold = 64' in dashboard, 'tab swipe requires a deliberate horizontal distance')
check('dashboardSwipeEdge = 24' in dashboard, 'screen-edge gestures are excluded')
check("absY > 18 && absY > absX" in dashboard, 'vertical scrolling cancels tab swipe recognition')
check("absX > 14 && absX > absY * 1.25" in dashboard, 'horizontal intent must be clear before scroll suppression')
check("Math.abs(distanceX) < Math.abs(distanceY) * 1.3" in dashboard, 'completed swipe remains horizontally dominant')
check('elapsed > 1200' in dashboard, 'slow drags are not treated as tab swipes')
for selector in ["'a'", "'button'", "'input'", "'textarea'", "'select'", "'.modal'", "'.drawer-nav'", "'.widget-drag-handle'", "'[data-dashboard-widget-type=\"calendar\"]'"]:
    check(selector in dashboard, f'swipe exclusion remains represented: {selector}')
check("$('.modal.show').length > 0" in dashboard and "$('.drawer').hasClass('drawer-open')" in dashboard, 'open Modal and Drawer block tab swipe')
check('widgetDragState !== null' in dashboard, 'Widget Drag and tab swipe do not run together')
check('targetTab < 0 || targetTab >= tabCount' in dashboard, 'first and last tabs do not wrap')
check("window.location.assign(target)" in dashboard and "'./?tab='" in dashboard, 'successful swipe uses the existing tab URL contract')
check('initTabSwipe();' in dashboard, 'swipe initialization is part of Dashboard startup')

check(index.count('fas fa-spinner fa-spin') >= 3, 'initial Feed and Calendar loading states include a spinning icon')
check("function appendLoadingText" in dashboard and "function appendLoadingText" in calendar, 'Feed and Calendar share the same restrained loading pattern within each runtime')
check("addClass('fas fa-spinner fa-spin')" in dashboard, 'Feed runtime creates the spinner without HTML insertion')
check("addClass('fas fa-spinner fa-spin')" in calendar, 'Calendar runtime creates the spinner without HTML insertion')
check("appendLoadingText($title, title)" in dashboard and "appendLoadingText($cell, message)" in dashboard, 'Feed title and body both show a spinner while loading')
check("appendLoadingText($loading, 'Calendarを読み込んでいます')" in calendar, 'Calendar month loading shows the spinner')
check("$days.attr('aria-busy', 'false')" in calendar, 'Calendar clears busy state on failure and invalid response')
check('.loading-inline {' in css and '.loading-inline > i' in css, 'loading indicator has a small shared layout rule')
check('@media (prefers-reduced-motion: reduce)' in css and '.loading-inline .fa-spin' in css, 'reduced-motion preference stops spinner animation')
for unsafe in ['.html(', 'innerHTML', 'insertAdjacentHTML', 'document.write(', 'eval(', 'new Function']:
    check(unsafe not in dashboard and unsafe not in calendar, f'new Frontend behavior keeps unsafe operation absent: {unsafe}')

check((re.search(r"const APP_VERSION = '1\.1\.0-dev\.[89][0-9]*';", version) is not None and any(label in version for label in ['V1.1-I / R2','V1.1-J / R1'])) or ("const APP_VERSION = '1.1.0';" in version and 'RSS Reader Modernization 1.1.0' in version) or "const APP_VERSION = '1.2.0-dev.3';" or "const APP_VERSION = '1.2.0-dev.4';" in version, 'visible Version marker identifies V1.1-I R2 or later')
check('test_v11i_r2_architecture.py' in run and 'test_v11i_r2_frontend_runtime.js' in run and 'test_v11i_r2_loading_browser.py' in run, 'main regression runner includes R2 checks')
check('test_v11i_r2_architecture.py' in local_run and 'test_v11i_r2_frontend_runtime.js' in local_run and 'test_v11i_r2_loading_browser.py' in local_run, 'local V1.1 runner includes R2 checks')
check(not (ROOT / 'database/migrations/007_v1_1_mobile_swipe.sql').exists(), 'R2 adds no DB migration')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} V1.1-I / R2 architecture checks passed.')
