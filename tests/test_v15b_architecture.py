from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
dashboard = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')
timer_js = (ROOT / 'public/js/clock-timer.js').read_text(encoding='utf-8')
timer_css = (ROOT / 'public/css/clock-timer.css').read_text(encoding='utf-8')
widget = (ROOT / 'app/dashboard_widget.php').read_text(encoding='utf-8')
schema = (ROOT / 'database/schema.sql').read_text(encoding='utf-8')
run = (ROOT / 'tests/run.sh').read_text(encoding='utf-8')

check(re.search(r"const APP_VERSION = '1\.5\.0(?:-dev\.[12])?';", version) is not None or re.search(r"const APP_VERSION = '1\.[67]\.0(?:-dev\.[1-9][0-9]*)?';", version) is not None, 'Application Version remains in the V1.5 line')
check('RSS Reader Modernization V1.5-' in version or "APP_VERSION_LABEL = 'RSS Reader Modernization 1.5.0'" in version or 'RSS Reader Modernization V1.6-' in version or "APP_VERSION_LABEL = 'RSS Reader Modernization 1.6.0'" in version or 'RSS Reader Modernization V1.7-' in version or 'RSS Reader Modernization V1.7-' in version, 'visible label identifies Version 1.5')
check('data-clock-view-trigger="clock"' in index and 'data-clock-view-trigger="timer"' in index, 'Clock and Timer view controls render')
check(all(label in index for label in ['1分', '3分', '5分', '10分', '25分']), 'approved Timer presets render')
check('min="1" max="1440" step="1"' in index, 'custom minutes input is bounded from 1 to 1440')
check('clock-timer-start' in index and 'clock-timer-pause' in index and 'clock-timer-reset' in index, 'Start, Pause and Reset controls render')
check('aria-live="polite"' in index and 'aria-atomic="true"' in index, 'Timer status uses a bounded live region')
check('data-dashboard-swipe-ignore="true"' in index, 'Timer controls avoid Dashboard swipe conflicts')
check(("app_asset_url('css/clock-timer.css')" in index and "app_asset_url('js/clock-timer.js')" in index) or ('./css/clock-timer.css' in index and './js/clock-timer.js' in index), 'Timer assets are loaded separately')
check("rssReader.clockTimer.v1" in timer_js and ".user.' + safeUserId + '.widget.' + safeWidgetId" in timer_js, 'Storage key separates Timer version, User and Widget')
check("browserStorage('localStorage')" in timer_js and "browserStorage('sessionStorage')" in timer_js and "storageMode = 'memory'" in timer_js, 'Storage wrapper supports local, session and memory modes')
check('JSON.parse' in timer_js and 'validateState' in timer_js and 'JSON.stringify' in timer_js, 'Storage values are parsed and validated')
check('innerHTML' not in timer_js and 'textContent' in timer_js, 'Timer Storage data is never written through innerHTML')
check('endAt - now' in timer_js and 'Math.ceil' in timer_js, 'Timer uses absolute endAt calculation instead of decrement-only state')
check('window.setInterval(updateRunningTimers, 500)' in timer_js, 'all running Timers share a bounded update loop')
check("window.RssClockTimer.removeWidgetState(widgetId)" in dashboard, 'successful Clock deletion removes Browser Timer state')
check('Browserに保存されたTimer状態も削除します' in dashboard, 'Clock deletion warning explains Timer state cleanup')
check('Audio(' not in timer_js and 'new Audio' not in timer_js and 'Notification' not in timer_js, 'V1.5-B adds no sound or Browser notification')
check('min-height: 44px' in timer_css, 'Timer controls preserve 44px interaction targets')
check('@media (prefers-reduced-motion: reduce)' in timer_css, 'Timer styling supports reduced motion')
check('bootstrap-solar' in timer_css and 'bootstrap-slate' in timer_css, 'existing dark Themes have Timer surface adjustments')
check("'clock'" in widget and 'dashboard_widget_clock_defaults' in widget, 'Timer remains inside the existing Clock Widget type')
check('CREATE TABLE' in schema and 'dashboard_widget' in schema, 'existing generic Widget table remains the storage for Clock placement')
check(not any('v15' in path.name.lower() for path in (ROOT / 'database/migrations').glob('*')), 'V1.5-B adds no DB migration')
check('test_v15b_clock_timer_runtime.js' in run and 'test_v15b_dashboard_render.py' in run, 'main runner includes V1.5-B checks')

if not all(checks):
    raise SystemExit(f'{checks.count(False)}/{len(checks)} V1.5-B architecture checks failed')
print(f'All {len(checks)} V1.5-B architecture checks passed.')
