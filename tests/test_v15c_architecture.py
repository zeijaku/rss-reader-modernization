from pathlib import Path
import re

from version_test_utils import is_later_application_release, is_later_visible_label
ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
timer = (ROOT / 'public/js/clock-timer.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/clock-timer.css').read_text(encoding='utf-8')
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
run = (ROOT / 'tests/run.sh').read_text(encoding='utf-8')

check("const APP_VERSION = '1.5.0-dev.2';" in version or "const APP_VERSION = '1.5.0';" in version or re.search(r"const APP_VERSION = '1\.[67]\.0(?:-dev\.[1-9][0-9]*)?';", version) is not None or is_later_application_release(version, (1, 5, 0)), 'Application Version retains V1.5-C behavior')
check('RSS Reader Modernization V1.5-C / R' in version or "APP_VERSION_LABEL = 'RSS Reader Modernization 1.5.0'" in version or 'RSS Reader Modernization V1.6-' in version or "APP_VERSION_LABEL = 'RSS Reader Modernization 1.6.0'" in version or 'RSS Reader Modernization V1.7-' in version or 'RSS Reader Modernization V1.7-' in version or is_later_visible_label(version, (1, 5, 0)), 'visible label identifies V1.5-C or final release')
check('loadStateResult' in timer and "browserStorageRaw('localStorage'" in timer and "browserStorageRaw('sessionStorage'" in timer, 'Timer inspects all Browser Storage copies')
check('valid.sort' in timer and 'savedAt' in timer and "left.name === 'localStorage'" in timer, 'newest valid Storage copy is selected deterministically')
check('removeStorageCopy' in timer and "'repaired-copy'" in timer and "reason: 'invalid-data'" in timer, 'invalid Storage copies are removed with explicit recovery reasons')
check("window.addEventListener('storage', handleStorageEvent)" in timer, 'multiple Browser tabs synchronize through the storage event')
check("window.addEventListener('focus', handlePageResume)" in timer and "window.addEventListener('pageshow', handlePageResume)" in timer, 'focus and page restore trigger immediate Timer reconciliation')
check("document.addEventListener('visibilitychange'" in timer and '!document.hidden' in timer, 'visibility recovery recalculates Timer after background suspension')
check('ACTION_GUARD_MS = 250' in timer and 'actionAllowed' in timer and '!event.repeat' in timer, 'rapid repeated activation and key repeat are bounded')
check('clock-timer-completed-recent' in timer and 'highlightCompletion' in timer, 'Timer completion gets a short visual state')
check('@keyframes clock-timer-completed-pulse' in css, 'completion emphasis animation is defined')
check('@media (prefers-reduced-motion: reduce)' in css and 'animation: none !important' in css, 'completion emphasis respects reduced motion')
check(':focus-visible' in css and 'outline: 3px solid' in css, 'Timer controls keep an explicit keyboard Focus indicator')
check('bootstrap-solar' in css and 'bootstrap-slate' in css and '#ffb8c0' in css, 'dark Themes retain readable completion status')
check('aria-live="polite"' in index and 'aria-atomic="true"' in index, 'Timer retains bounded Screen Reader announcements')
check('Audio(' not in timer and 'new Audio' not in timer and 'Notification' not in timer, 'V1.5-C still adds no sound or Browser notification')
check(not any('v15' in path.name.lower() for path in (ROOT / 'database/migrations').glob('*')), 'V1.5-C adds no DB migration')
check('test_v15c_clock_timer_runtime.js' in run and 'test_v15c_theme_browser.py' in run, 'main runner includes V1.5-C checks')

if not all(checks):
    raise SystemExit(f'{checks.count(False)}/{len(checks)} V1.5-C architecture checks failed')
print(f'All {len(checks)} V1.5-C architecture checks passed.')
