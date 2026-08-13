from pathlib import Path

from dashboard_source_utils import dashboard_source
ROOT = Path(__file__).resolve().parents[1]
index = dashboard_source(ROOT)
timer = (ROOT / 'public/js/clock-timer.js').read_text(encoding='utf-8')
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

check('clock-card-body clock-timer-enabled' in index, 'Clock Widget still renders Timer inside the existing body')
check(index.count('data-clock-view-trigger="clock"') == 1 and index.count('data-clock-view-trigger="timer"') == 1, 'one Clock and Timer trigger template is rendered')
check('data-dashboard-swipe-ignore="true"' in index, 'Clock Timer remains isolated from Dashboard swipe')
check('aria-live="polite" aria-atomic="true"' in index, 'Timer status remains one atomic polite live region')
check('clock-timer-completed-recent' in timer, 'completion emphasis is controlled from the Timer runtime')
check("renderCard(instance, loadedResult.recovered ? 'recovered'" in timer, 'recovered Storage state is announced during initialization')
check("renderCard(instance, loadedResult.recovered ? 'recovered' : eventName)" in timer, 'recovery and cross-tab synchronization reuse bounded rendering')
check("storageKey(instance.userId, instance.widgetId) === event.key" in timer, 'storage events only update the matching User and Widget state')
check('innerHTML' not in timer, 'Storage and synchronization never insert HTML')
check(("app_asset_url('css/clock-timer.css')" in index and "app_asset_url('js/clock-timer.js')" in index) or ('./css/clock-timer.css' in index and './js/clock-timer.js' in index), 'Clock Timer remains split into dedicated assets')

if not all(checks):
    raise SystemExit(f'{checks.count(False)}/{len(checks)} V1.5-C Dashboard checks failed')
print(f'All {len(checks)} V1.5-C Dashboard checks passed.')
