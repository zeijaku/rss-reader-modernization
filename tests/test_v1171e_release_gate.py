#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
checks = []


def check(name, condition):
    checks.append((name, bool(condition)))


def read(path):
    return (ROOT / path).read_text(encoding='utf-8')

version = read('app/version.php')
api = read('public/api_v1.php')
calendar = read('public/js/calendar.js')
helper = read('public/js/widget-card-refresh.js')
interceptor = read('public/js/widget-settings-no-reload.js')
streaming = read('public/js/camera-video-streaming.js')
app_notice = read('public/js/app-notice.js')

check('APP_VERSION finalized', "const APP_VERSION = '1.17.1';" in version)
check('APP_VERSION_LABEL finalized', "const APP_VERSION_LABEL = 'RSS Reader Modernization 1.17.1';" in version)
check('APP_ASSET_REVISION finalized', "const APP_ASSET_REVISION = '1.17.1';" in version)

stable_assets = [
    './css/mail-widget.css', './css/camera-video.css', './css/camera-video-playback.css', './css/camera-video-streaming.css',
    './js/app-notice.js', './js/widget-card-refresh.js', './js/information-widget-watchdog.js',
    './js/mail-widget-watchdog.js', './js/camera-video-watchdog.js', './js/mail-widget.js', './js/camera-video.js',
    './js/camera-video-playback.js', './js/camera-video-streaming.js', './js/widget-settings-no-reload.js',
]
for asset in stable_assets:
    check(f'loader finalizes {asset}', asset + '?v=1.17.1' in calendar)
check('no staged V1.17.1 asset token remains in loader', re.search(r'1\.17\.1-[a-z]', calendar) is None)
check('D helper loads before Information watchdog', calendar.find('widget-card-refresh.js?v=1.17.1') < calendar.find('information-widget-watchdog.js?v=1.17.1'))
check('Mail watchdog loads before Mail feature', calendar.find('mail-widget-watchdog.js?v=1.17.1') < calendar.find('mail-widget.js?v=1.17.1'))
check('Camera watchdog loads before Camera feature', calendar.find('camera-video-watchdog.js?v=1.17.1') < calendar.find('camera-video.js?v=1.17.1'))
check('settings interceptor loads after media feature stack', calendar.find('widget-settings-no-reload.js?v=1.17.1') > calendar.find('camera-video-streaming.js?v=1.17.1'))

csrf_pos = api.find('app_csrf_is_valid($csrfToken)')
action_pos = api.find("preg_match('/^[a-z]+(?:\\.[a-z]+)+$/', $action)")
try_pos = api.find('try {', action_pos)
release_pos = api.find('app_session_release();')
first_dispatch = api.find("if (str_starts_with($action, 'camera.widget.'))")
catch_pos = api.find('} catch (Throwable $exception)', first_dispatch)
check('session release follows CSRF and action validation', 0 <= csrf_pos < action_pos < release_pos)
check('session release is inside Throwable boundary', 0 <= try_pos < release_pos < first_dispatch < catch_pos)
check('credential updates retain open session', "'account.email.update'" in api and "'account.password.update'" in api)
check('API release failure remains normal JSON internal error', "api_emit(api_error('internal_error'" in api and 'session_write_close() failure still returns the normal JSON error' in api)

check('target-card helper never forces whole-page reload', 'window.location.reload' not in helper)
check('settings interceptor never forces whole-page reload', 'window.location.reload' not in interceptor)
check('settings interceptor uses capture phase', "document.addEventListener('submit', interceptSubmit, true);" in interceptor)
check('settings interceptor stops legacy update handlers', 'event.stopImmediatePropagation();' in interceptor)
check('Weather visual-only changes are in-place', 'weatherDataChanged' in helper and 'if (weatherDataChanged)' in helper)
check('Camera update replaces only target Camera card', '$old.replaceWith($next)' in interceptor and 'sortGrid(' not in interceptor)
check('Mail settings keep target card', 'function refreshMailTarget(widgetId)' in interceptor and '.mail-widget-refresh' in interceptor)
check('shared notices remain bounded', "success: 2500" in app_notice and "info: 3000" in app_notice and "danger: 6000" in app_notice and 'MutationObserver' in app_notice)
check('hls.js SRI matches browser-computed SHA-384 observed during release review', 'sha384-5E8B0pTLZZJMabWpCOfyYf60UpeI5jJij34BqBAh4NXoHALLNOjCPRrwtOX0QFAn' in streaming)
check('old rejected hls.js SRI is removed', 'sha384-iZBI1/lW9u8FcBjxuQ8nPTsU7TXhZNtzkV8H3gQHSTgz+VYQoKWqGlBHqhO84alJ' not in streaming)
check('streaming fallback stylesheet uses stable revision', "camera-video-streaming.css?v=1.17.1" in streaming)

for path in [
    'app/session.php', 'app/version.php', 'public/api_v1.php', 'public/js/app-notice.js', 'public/js/calendar.js',
    'public/js/camera-video-watchdog.js', 'public/js/mail-widget-watchdog.js', 'public/js/information-widget-watchdog.js',
    'public/js/widget-card-refresh.js', 'public/js/widget-settings-no-reload.js', 'public/js/camera-video-streaming.js',
]:
    check(f'cumulative production file exists: {path}', (ROOT / path).is_file())

failed = [name for name, ok in checks if not ok]
for name, ok in checks:
    print(f"[{'PASS' if ok else 'FAIL'}] {name}")
print(f"RESULT: PASS {len(checks)-len(failed)} / FAIL {len(failed)} / SKIP 0")
sys.exit(1 if failed else 0)
