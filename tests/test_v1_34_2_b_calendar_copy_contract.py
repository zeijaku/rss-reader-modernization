#!/usr/bin/env python3
from pathlib import Path
from version_contract_utils import current_asset_revision

ROOT = Path(__file__).resolve().parents[1]
copy_js = (ROOT / 'public/js/calendar-copy.js').read_text(encoding='utf-8')
loader = (ROOT / 'public/js/calendar.js').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
runner = (ROOT / 'tests/run-current-features.sh').read_text(encoding='utf-8')
asset_revision = current_asset_revision(ROOT)
checks: list[bool] = []

def check(condition: bool, message: str) -> None:
    checks.append(bool(condition)); print(('PASS' if condition else 'FAIL') + ': ' + message)

check("'changeCalendarEventForm'" in copy_js and "'registerCalendarEventForm'" in copy_js, 'copy workflow reuses the existing edit and register Calendar forms')
check("button.type = 'button'" in copy_js and 'copy_calendar_event' in copy_js, 'copy action is an explicit non-submit button')
check('snapshotChangeForm' in copy_js and 'applySnapshot' in copy_js, 'copy workflow separates source snapshot from destination population')
for token in ('CalendarEventTitleValue','CalendarEventStartDate','CalendarEventEndDate','CalendarEventNote','CalendarEventColor','CalendarEventAllDay','CalendarEventStartTime','CalendarEventEndTime','CalendarEventUrl','CalendarEventReminder','CalendarEventRepeatType','CalendarEventRepeatUntil'):
    check(token in copy_js, f'copy workflow preserves {token}')
check("repeat: occurrenceOnly ? 'none'" in copy_js and "repeatUntil: occurrenceOnly ? ''" in copy_js, 'copying one recurrence occurrence creates a standalone non-recurring event')
check("reminder: fieldValue(form, '.changeCalendarEventReminder')" in copy_js, 'occurrence-only and series copy preserve the currently inherited reminder value')
check("selected.value !== 'series'" in copy_js, 'series scope remains distinguishishable from occurrence-only copy')
check('calendar.recurrence.create' not in copy_js and '$.ajax' not in copy_js and 'api_v1.php' not in copy_js, 'copy itself performs no save or direct API request before user confirmation')
check("bootstrap.Modal.getOrCreateInstance(registerModal).show()" in copy_js, 'copy opens the existing new-event modal for review before save')
check("data-calendar-copy-source" in copy_js, 'destination form records non-sensitive copy origin state for UI/test visibility')
check("registerForm.setAttribute('data-calendar-recurrence-submit-ready', '1')" in copy_js and copy_js.find("registerForm.setAttribute('data-calendar-recurrence-submit-ready', '1')") > copy_js.find("setValue(registerForm, '.registerCalendarEventRepeatUntil'"), 'copied recurrence data becomes save-ready only after all recurrence values are populated')
check("registerForm.setAttribute('aria-busy', 'false')" in copy_js and '.calendar-event-recurrence-loading' in copy_js, 'copy clears stale recurrence loading state before user review')
check("assetUrl('./js/calendar-copy.js')" in loader, 'Calendar copy module uses the centralized asset URL')
occurrence_pos = loader.find("assetUrl('./js/calendar-occurrence.js')")
recurrence_pos = loader.find("assetUrl('./js/calendar-recurrence.js')")
detail_pos = loader.find("assetUrl('./js/calendar-event-details.js')")
copy_pos = loader.find("assetUrl('./js/calendar-copy.js')")
check(-1 not in (occurrence_pos, recurrence_pos, detail_pos, copy_pos) and occurrence_pos < recurrence_pos < detail_pos < copy_pos, 'copy module loads after occurrence, recurrence and event-detail form controllers')
check(f"const APP_ASSET_REVISION = '{asset_revision}';" in version, 'Calendar loader revision matches app/version.php')
check('test_v1_34_2_b_calendar_copy_contract.py' in runner and 'test_v1_34_2_b_calendar_copy.js' in runner, 'current feature gate executes both static and runtime Calendar copy tests')
check('innerHTML' not in copy_js and '.html(' not in copy_js, 'copy workflow adds no HTML assignment sink')
failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
