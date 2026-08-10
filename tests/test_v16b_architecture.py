from pathlib import Path
import re
import sys

from version_test_utils import is_later_application_release, is_later_visible_label
ROOT = Path(__file__).resolve().parents[1]
js = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
run = (ROOT / 'tests/run.sh').read_text(encoding='utf-8')
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


check(re.search(r"const APP_VERSION = '(?:1\.6\.0(?:-dev\.[1-9][0-9]*)?|1\.7\.0-dev\.[1-9][0-9]*)';", version) is not None or is_later_application_release(version, (1, 6, 0)), 'Application Version retains V1.6-B behavior in later checkpoints')
check('RSS Reader Modernization V1.6-' in version or "APP_VERSION_LABEL = 'RSS Reader Modernization 1.6.0'" in version or 'RSS Reader Modernization V1.7-' in version or is_later_visible_label(version, (1, 6, 0)), 'Application Label identifies V1.6-B or later')
check('dashboardSwipeThreshold = 64' in js and 'dashboardSwipeEdge = 24' in js, 'existing Swipe threshold and screen-edge exclusion are unchanged')
check("absY > 18 && absY > absX" in js and "absX > 14 && absX > absY * 1.25" in js, 'existing vertical and horizontal intent checks are unchanged')
check("Math.abs(distanceX) < Math.abs(distanceY) * 1.3" in js and 'elapsed > 1200' in js, 'existing final dominance and time limit are unchanged')
for token in ["'a'", "'button'", "'input'", "'textarea'", "'select'", "'.widget-drag-handle'", "'[data-dashboard-widget-type=\"calendar\"]'", "'.table-responsive'", "'[data-dashboard-swipe-ignore=\"true\"]'"]:
    check(token in js, f'existing excluded interaction remains: {token}')
check('function dashboardSwipeIndicatorElement()' in js, 'Swipe indicator has one lazy DOM creation boundary')
check("setAttribute('aria-hidden', 'true')" in js, 'visual-only indicator is hidden from Screen Readers')
check("setAttribute('data-dashboard-swipe-indicator', 'true')" in js, 'indicator has a stable non-interactive test hook')
check("nextTab ? '‹' : '›'" in js, 'next and previous directions use matching edge arrows')
check("nextTab ? 'is-right' : 'is-left'" in js, 'next tab is shown at the right edge and previous tab at the left edge')
check('Math.abs(distanceX) / dashboardSwipeThreshold' in js, 'indicator opacity is derived from Swipe distance')
check('dashboardSwipeNavigateDelay = 160' in js, 'accepted Swipe has a bounded visual confirmation interval')
check('dashboardSwipeIndicatorHide(false)' in js and 'dashboardSwipeIndicatorHide(true)' in js, 'cancelled and accepted gestures have separate visual endings')
check('pointer-events: none' in css, 'indicator never intercepts pointer or touch input')
check(re.search(r'\.dashboard-swipe-indicator\s*\{[^}]*display:\s*none;', css, re.S) is not None, 'indicator is hidden by default')
check(re.search(r'@media \(max-width: 767\.98px\)[\s\S]*?\.dashboard-swipe-indicator\.is-visible\s*\{[^}]*display:\s*flex;', css) is not None, 'indicator is visible only at smartphone width')
check('env(safe-area-inset-left)' in css and 'env(safe-area-inset-right)' in css, 'indicator respects iPhone safe-area insets')
check(re.search(r'@media \(prefers-reduced-motion: reduce\)[\s\S]*?\.dashboard-swipe-indicator\s*\{[^}]*translate3d\(0, -50%, 0\)', css) is not None, 'Reduced Motion suppresses horizontal indicator movement')
check(("app_asset_url('css/dashboard.css')" in index and "app_asset_url('js/dashboard.js')" in index) or ('./css/dashboard.css?v=1.6-b-r1' in index and re.search(r'./js/dashboard\.js\?v=1\.6-[bcd]-r1', index) is not None), 'Swipe indicator assets keep Cache Busting through the current Asset strategy')
check('test_v16b_architecture.py' in run and 'test_v16b_frontend_runtime.js' in run and 'test_v16b_browser.py' in run, 'main runner includes V1.6-B tests')
check(not any('v16' in path.name.lower() for path in (ROOT / 'database/migrations').glob('*')), 'V1.6-B adds no DB migration')
check('widget.game' not in js[js.find('var dashboardSwipeState'):js.find('function drawerFocusableItems')], 'Swipe indicator does not add Game or API behavior')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
sys.exit(1 if failed else 0)
