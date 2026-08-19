#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
checks=[]

def check(name, cond):
    checks.append((name, bool(cond)))

def read(rel):
    return (ROOT/rel).read_text(encoding='utf-8')

version=read('app/version.php')
calendar=read('public/js/calendar.js')
helper=read('public/js/widget-card-refresh.js')
guard=read('public/js/widget-settings-no-reload.js')

version_match = re.search(r"const APP_VERSION = '(\d+)\.(\d+)\.(\d+)(?:-[^']+)?';", version)
version_tuple = tuple(int(part) for part in version_match.groups()) if version_match else (0, 0, 0)
revision_match = re.search(r"const APP_ASSET_REVISION = '([^']+)';", version)
active_revision = revision_match.group(1) if revision_match else ''
check('visible version keeps V1.17.1-D/R3-or-later behavior', version_tuple >= (1, 17, 1))
check('stable/current asset revision is active', bool(active_revision))
check('R3 helper is real runtime asset', 'widget-card-refresh.js?v=' + active_revision in calendar)
check('R3 production interceptor is loaded', 'widget-settings-no-reload.js?v=' + active_revision in calendar)
check('interceptor loads after Camera/Mail feature scripts', calendar.find('widget-settings-no-reload.js?v=' + active_revision) > calendar.find('camera-video-streaming.js?v=' + active_revision))
check('capture-phase submit interception is enabled', "document.addEventListener('submit', interceptSubmit, true);" in guard)
check('legacy update handlers are stopped', 'event.stopImmediatePropagation();' in guard and 'event.preventDefault();' in guard)
check('production interceptor never reloads whole page', 'window.location.reload' not in guard)
check('card helper never reloads whole page', 'window.location.reload' not in helper)

forms=[
    'changeContentForm','changeClockForm','changeGameWidgetForm','changeMemoForm','changeTaskWidgetForm',
    'changeSearchFeedForm','changeLinksWidgetForm','changeWeatherWidgetForm','changeEarthquakeWidgetForm',
    'changeSunMoonWidgetForm','changeAirQualityWidgetForm','changeCalendarWidgetForm','changeCameraVideoForm','changeMailWidgetForm'
]
for form in forms:
    check(f'intercepts {form}', re.search(rf'\b{re.escape(form)}:\s*true', guard) is not None)

for action in [
    'content.update','widget.clock.update','widget.game.update','widget.memo.update','widget.task.update',
    'widget.search.update','widget.links.update','widget.weather.update','widget.earthquake.update',
    'widget.sunmoon.update','widget.airquality.update','widget.calendar.update','camera.widget.update','mail.widget.update'
]:
    check(f'posts {action}', action in guard)

check('Weather visual-only update remains in-place', 'weatherDataChanged' in helper and 'previousWeatherLocation' in helper and 'previousWeatherDays' in helper)
check('Weather header-only change does not force data refresh', "if (weatherDataChanged)" in helper)
check('server-rendered cards replace only target card', '$current.replaceWith($next)' in helper)
check('Camera update replaces only target Camera card', '$old.replaceWith($next)' in guard and 'data-dashboard-widget-type="camera_video"' in guard)
check('Camera refresh does not sort or re-append sibling cards', 'sortGrid(' not in guard and 'nodes.forEach' not in guard)
check('Mail update keeps target card and refreshes it', '.mail-widget-refresh' in guard and ".data('mail-widget', widget)" in guard)
check('Mail update does not rebuild whole dashboard', 'mail.widget.list' in guard and 'replaceWith' not in guard[guard.find('function refreshMailTarget'):guard.find('function handleSimple')])
check('CSRF token accompanies update API calls', 'csrf_token: csrfToken()' in guard)
check('API calls are bounded', 'timeout: timeout || 8000' in guard)
check('API notices use text, not HTML', '.text(String(message' in guard)

fails=[name for name,ok in checks if not ok]
for name,ok in checks:
    print(f"[{'PASS' if ok else 'FAIL'}] {name}")
print(f"RESULT: PASS {len(checks)-len(fails)} / FAIL {len(fails)} / SKIP 0")
sys.exit(1 if fails else 0)
