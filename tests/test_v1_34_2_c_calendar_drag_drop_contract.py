#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
drag = (ROOT / 'public/js/calendar-drag-drop.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/calendar-drag-drop.css').read_text(encoding='utf-8')
loader = (ROOT / 'public/js/calendar.js').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
workflow = (ROOT / '.github/workflows/ci.yml').read_text(encoding='utf-8')
checks = []

def check(ok, msg):
    checks.append(bool(ok)); print(('PASS' if ok else 'FAIL') + ': ' + msg)

check("./js/calendar-drag-drop.js?v=1.34.2-dev.4" in loader, 'drag module uses dev.4 cache key')
check("./css/calendar-drag-drop.css?v=1.34.2-dev.4" in loader, 'drag style uses dev.4 cache key')
check(loader.find('calendar-copy.js?v=1.34.2-dev.4') < loader.find('calendar-drag-drop.js?v=1.34.2-dev.4'), 'drag module loads after Calendar edit/copy controllers')
check("const APP_VERSION = '1.34.2-dev.4';" in version and "const APP_ASSET_REVISION = '1.34.2-dev.4';" in version, 'version and asset revision are dev.4')
check("calendar.color.update" in drag and "calendar.occurrence.update" in drag, 'existing normal and occurrence update actions are reused')
check("./calendar_color_api.php" in drag and "./calendar_recurrence_api.php" in drag, 'existing Calendar endpoints are reused')
check('original_occurrence_start_date' in drag and 'occurrence_revision' in drag, 'occurrence move retains original identity and optimistic revision')
check("exceptionKind === 'cancelled'" in drag, 'cancelled occurrence cannot be dragged')
check(".calendar-event-edit-trigger" in drag and 'calendar-task' not in drag, 'drag eligibility is limited to editable Calendar events, not Tasks')
for key in ('calendar_event_title','calendar_event_start_date','calendar_event_end_date','calendar_event_note','calendar_event_color','calendar_event_all_day','calendar_event_start_time','calendar_event_end_time','calendar_event_url'):
    check(key in drag, f'move payload preserves {key}')
check("range.delta === 0" in drag, 'same-date drop performs no update')
check("csrf_token: csrfToken()" in drag and "X-CSRF-Token" in drag, 'CSRF token is sent and refreshed')
check("timeout: 4000" in drag and "xhr.status === 409" in drag, 'timeout and occurrence revision conflict are handled')
check("draggable" in drag and "calendar-drag-drop-target" in drag and 'currentColor' in css, 'drag source and theme-neutral drop feedback are defined')
check('innerHTML' not in drag and '.html(' not in drag, 'drag module introduces no HTML assignment sink')
check('test_v1_34_2_c_calendar_drag_drop_contract.py' in workflow and 'test_v1_34_2_c_calendar_drag_drop.js' in workflow, 'CI runs C static and runtime tests')
failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
