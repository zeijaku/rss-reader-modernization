#!/usr/bin/env python3
from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--overlay-only', action='store_true', help='skip unchanged baseline dependency files when validating an overlay package')
args = parser.parse_args()
checks: list[tuple[str, bool]] = []


def check(name: str, condition: bool) -> None:
    checks.append((name, bool(condition)))


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


version = text('app/version.php')
calendar = text('public/js/calendar.js')
watchdog = text('public/js/information-widget-watchdog.js')

check('visible APP_VERSION is finalized at 1.17.1', "const APP_VERSION = '1.17.1';" in version)
revision_match = re.search(r"const APP_ASSET_REVISION = '([^']+)';", version)
active_revision = revision_match.group(1) if revision_match else ''
check('V1.17.1-C-or-later asset revision is active', active_revision == '1.17.1' or bool(re.fullmatch(r'1\.17\.1-[c-z][A-Za-z0-9._-]*', active_revision)))
check('Information Widget watchdog is staged by the loader', './js/information-widget-watchdog.js?v=' + active_revision in calendar)
check('shared notice is refreshed to active revision', './js/app-notice.js?v=' + active_revision in calendar)
check('Mail watchdog remains present at active revision', './js/mail-widget-watchdog.js?v=' + active_revision in calendar)
check('Camera watchdog remains present at active revision', './js/camera-video-watchdog.js?v=' + active_revision in calendar)
check('Mail feature remains staged at active revision', './js/mail-widget.js?v=' + active_revision in calendar)
check('Camera feature remains staged at active revision', './js/camera-video.js?v=' + active_revision in calendar)

info_pos = calendar.find('./js/information-widget-watchdog.js?v=' + active_revision)
mail_pos = calendar.find('./js/mail-widget-watchdog.js?v=' + active_revision)
check('Information watchdog loads before B feature watchdogs', 0 <= info_pos < mail_pos)

check('Earthquake card is watched', "selector: '.earthquake-card'" in watchdog)
check('Earthquake timeout allows XHR/server budget plus UI margin', 'timeoutMs: 10500' in watchdog)
check('Earthquake recovery points user to refresh', '地震情報の読み込みがタイムアウトしました。更新ボタンから再試行できます。' in watchdog)
check('Sun / Moon card is watched', "selector: '.sun-moon-card'" in watchdog)
check('Sun / Moon timeout is bounded', 'timeoutMs: 6500' in watchdog)
check('Sun / Moon recovery points user to refresh', 'Sun / Moon情報の計算がタイムアウトしました。更新ボタンから再試行できます。' in watchdog)
check('Air Quality card is watched', "selector: '.air-quality-card'" in watchdog)
check('Air Quality timeout allows XHR/server budget plus UI margin', 'timeoutMs: 8500' in watchdog)
check('Air Quality recovery points user to refresh', '大気情報の読み込みがタイムアウトしました。更新ボタンから再試行できます。' in watchdog)

check('recovery clears aria-busy', "$card.attr('aria-busy', 'false')" in watchdog)
check('recovery clears request-pending', ".data('request-pending', false)" in watchdog)
check('recovery re-enables refresh control', ".prop('disabled', false)" in watchdog)
check('recovery stops refresh spinner', ".removeClass('fa-spin')" in watchdog)
check('recovery uses existing Information Widget state class', "addClass('information-widget-state text-muted')" in watchdog)
check('watchdog observes aria-busy changes', "attributeFilter: ['aria-busy']" in watchdog)
check('watchdog observes dynamically inserted cards', 'childList: true' in watchdog and 'scan(node)' in watchdog)
check('watchdog cleans timers for removed cards', 'cleanup(node)' in watchdog)
check('watchdog cleans timers on unload', "beforeunload' + namespace" in watchdog and 'cleanup(document)' in watchdog)

if not args.overlay_only:
    dependency_files = {
        'public/js/utility-widgets.js': [
            "apiRequest('earthquake.latest'",
            "apiRequest('sunmoon.current'",
            "apiRequest('airquality.current'",
            "'.earthquake-refresh-trigger'",
            "'.sun-moon-refresh-trigger'",
            "'.air-quality-refresh-trigger'",
            "common.setState($card, '.earthquake-card-body'",
            "common.setState($card, '.sun-moon-card-body'",
            "common.setState($card, '.air-quality-card-body'",
        ],
        'app/earthquake.php': [
            'APP_EARTHQUAKE_TIMEOUT_MS',
            "$stale['stale'] = true",
            'earthquake_cache_read',
        ],
        'app/air_quality.php': [
            'AIR_QUALITY_CACHE_TTL_SECONDS = 900',
            'AIR_QUALITY_STALE_MAX_AGE_SECONDS = 86400',
            'weather_safe_fetch',
            "['stale'] = true",
        ],
        'app/sun_moon.php': [
            'sun_moon_current',
        ],
    }
    for path, needles in dependency_files.items():
        source_path = ROOT / path
        check(f'baseline dependency exists: {path}', source_path.is_file())
        if not source_path.is_file():
            continue
        source = source_path.read_text(encoding='utf-8')
        for needle in needles:
            check(f'{path} retains C stability contract: {needle}', needle in source)
else:
    check('overlay-only validation explicitly selected', True)

failed = [name for name, ok in checks if not ok]
for name, ok in checks:
    print(f"[{'PASS' if ok else 'FAIL'}] {name}")
print(f'RESULT: PASS {len(checks) - len(failed)} / FAIL {len(failed)} / SKIP 0')
if failed:
    sys.exit(1)
