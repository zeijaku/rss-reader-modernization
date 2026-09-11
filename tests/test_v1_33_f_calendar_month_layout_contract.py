#!/usr/bin/env python3
from pathlib import Path
import re

from version_contract_utils import read_app_version_constants

ROOT = Path(__file__).resolve().parents[1]
layout = (ROOT / 'public/js/calendar-month-layout.js').read_text(encoding='utf-8')
core = (ROOT / 'public/js/calendar-core.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/calendar-month-layout.css').read_text(encoding='utf-8')
loader = (ROOT / 'public/js/calendar.js').read_text(encoding='utf-8')
version_constants = read_app_version_constants(ROOT)
current_version = version_constants.get('APP_VERSION', '')
current_revision = version_constants.get('APP_ASSET_REVISION', '')
version_match = re.fullmatch(r'(\d+)\.(\d+)\.(\d+)(?:-(?:rc\d+|dev\.\d+))?', current_version)

passed = 0
failed = 0


def check(condition: bool, message: str) -> None:
    global passed, failed
    if condition:
        passed += 1
        print(f'PASS: {message}')
    else:
        failed += 1
        print(f'FAIL: {message}')


check('iGuguruCalendarMonthLayout' in layout and 'Object.freeze({place: place})' in layout, 'placement module exposes a narrow immutable API')
check("parsed.getUTCDay() === 6" in layout, 'placement splits connected events at Saturday')
check("['single', 'start', 'middle', 'end']" in core, 'renderer accepts the four fixed span positions')
check('_calendar_span_continues_before' in layout and '_calendar_span_continues_after' in layout, 'placement preserves week and month continuation state')
check('lowestFreeLane(map, dates)' in layout, 'one free lane is selected across every day in a weekly segment')
check('_calendar_placeholder' in layout and '_calendar_placeholder' in core, 'empty earlier lanes receive non-interactive placeholders')
check("item.kind === 'task'" in core and "kind: 'task'" in layout, 'Task rendering remains in the common day stack')
check("exception_kind: 'cancelled'" in layout, 'cancelled occurrence restore target remains visible')
check("exception_kind" in layout and "occurrence_start_date" in layout, 'occurrence overrides use their effective dates')
check("calendar-event-span-' + spanPosition" in core, 'DOM receives position-specific connected classes')
check("data-calendar-lane" in core and "data-calendar-span-position" in core, 'DOM exposes deterministic lane and position for verification')
check("aria-label" in core and '複数日予定' in core, 'continuation-only segments retain an accessible event label')
check(".addClass('calendar-entry-title').text(item.title)" in core, 'event title remains text-rendered')
check(not re.search(r'\.innerHTML\s*=', layout + core), 'month layout introduces no innerHTML assignment')
check('eval(' not in layout and 'document.write' not in layout, 'placement module introduces no executable-string sink')
check('$.ajax' not in layout and 'fetch(' not in layout and 'XMLHttpRequest' not in layout, 'placement module performs no network request')
check('calendar-event-span-start' in css and 'calendar-event-span-middle' in css and 'calendar-event-span-end' in css, 'CSS connects start middle and end segments')
check('calendar-event-span-continued-before' in css and 'calendar-event-span-continued-after' in css, 'CSS marks continuation across calendar rows and months')
check('calendar-entry-placeholder' in css and 'visibility: hidden' in css and 'pointer-events: none' in css, 'lane placeholders are invisible and non-interactive')
check('@media (max-width: 575.98px)' in css, 'connected layout includes Smartphone sizing')
check('bootstrap-solar' in css and 'bootstrap-slate' in css, 'connected layout accounts for dark application Themes')
check(loader.index('calendar-month-layout.js') < loader.index('calendar-core.js'), 'placement module loads before Calendar core')
check(version_match is not None and tuple(map(int, version_match.groups()[:3])) >= (1, 33, 1), 'F contract runs on V1.33.1 or later')
check(bool(current_revision) and current_revision == current_version, 'current asset revision matches the application version')
check(f'calendar-month-layout.css?v={current_revision}' in loader, 'connected CSS uses the current cache revision')
check(f'calendar-month-layout.js?v={current_revision}' in loader, 'placement JavaScript uses the current cache revision')
check(not list((ROOT / 'database/migrations').glob('026*v1_33*')), 'F adds no database migration')
check('calendar.range.list' not in layout, 'F reuses C range data without a new API action')

print(f'RESULT: PASS {passed} / FAIL {failed} / SKIP 0')
raise SystemExit(0 if failed == 0 else 1)
