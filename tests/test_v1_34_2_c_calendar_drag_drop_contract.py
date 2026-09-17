#!/usr/bin/env python3
from pathlib import Path
from version_contract_utils import current_asset_revision

ROOT = Path(__file__).resolve().parents[1]
drag = (ROOT / 'public/js/calendar-drag-drop.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/calendar-drag-drop.css').read_text(encoding='utf-8')
loader = (ROOT / 'public/js/calendar.js').read_text(encoding='utf-8')
core = (ROOT / 'public/js/calendar-core.js').read_text(encoding='utf-8')
polish = (ROOT / 'public/js/calendar-polish.js').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
runner = (ROOT / 'tests/run-current-features.sh').read_text(encoding='utf-8')
asset_revision = current_asset_revision(ROOT)
asset_suffix = f'?v={asset_revision}'
checks = []

def check(ok, msg):
    checks.append(bool(ok)); print(('PASS' if ok else 'FAIL') + ': ' + msg)

check(f"./js/calendar-drag-drop.js{asset_suffix}" in loader, 'drag module uses the current asset revision')
check(f"./css/calendar-drag-drop.css{asset_suffix}" in loader, 'drag style uses the current asset revision')
copy_pos = loader.find(f'calendar-copy.js{asset_suffix}')
drag_pos = loader.find(f'calendar-drag-drop.js{asset_suffix}')
check(-1 not in (copy_pos, drag_pos) and copy_pos < drag_pos, 'drag module loads after Calendar edit/copy controllers')
check(f"const APP_ASSET_REVISION = '{asset_revision}';" in version, 'Calendar loader revision matches app/version.php')
check("calendar.color.update" in drag and "calendar.occurrence.update" in drag, 'existing normal and occurrence update actions are reused')
check("./calendar_color_api.php" in drag and "./calendar_recurrence_api.php" in drag, 'existing Calendar endpoints are reused')
check('original_occurrence_start_date' in drag and 'occurrence_revision' in drag, 'occurrence move retains original identity and optimistic revision')
check("exceptionKind === 'cancelled'" in drag, 'cancelled occurrence cannot be dragged')
check(".calendar-event-edit-trigger" in drag and 'calendar-task' not in drag, 'drag eligibility is limited to editable Calendar events, not Tasks')
for key in ('calendar_event_title','calendar_event_start_date','calendar_event_end_date','calendar_event_note','calendar_event_color','calendar_event_all_day','calendar_event_start_time','calendar_event_end_time','calendar_event_url'):
    check(key in drag, f'move payload preserves {key}')
check("range.delta === 0" in drag, 'same-date drop performs no update')
check("csrf_token: csrfToken()" in drag and "X-CSRF-Token" in drag, 'CSRF token is sent and refreshed')
check("timeout: 4000" in drag, 'D&D request remains bounded')
check("xhr.status === 409" in drag and "calendar:occurrenceChanged" in drag, 'occurrence revision conflict explicitly resynchronizes Calendar state')
check("draggable" in drag and "calendar-drag-drop-target" in drag and 'currentColor' in css, 'drag source and theme-neutral drop feedback are defined')
check('MutationObserver' in drag and 'observeCalendarRedraws' in drag and '{childList: true, subtree: true}' in drag, 'Calendar DOM redraws are observed for repeated drag preparation')
check('schedulePrepare' in drag and 'prepareTimer' in drag, 'redraw preparation is coalesced instead of racing render timing')
check(".always(function ()" in drag and 'schedulePrepare();' in drag, 'request completion schedules drag re-preparation')
check(drag.count("$(document).trigger('calendar:occurrenceChanged')") >= 2,
      'successful drag and occurrence conflict both trigger Calendar projection synchronization')
check('refreshVisibleCalendars' in core and 'loadUpcoming();' in polish,
      'Calendar synchronization refreshes every visible Calendar and the upcoming projection')
check('window.location.reload' not in drag, 'drag and drop never reloads the whole Dashboard')
check('innerHTML' not in drag and '.html(' not in drag, 'drag module introduces no HTML assignment sink')
check('test_v1_34_2_c_calendar_drag_drop_contract.py' in runner and 'test_v1_34_2_c_calendar_drag_drop.js' in runner, 'current feature gate runs C static and runtime tests')
failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
