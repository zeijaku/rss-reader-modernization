#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
js = (ROOT / 'public/js/calendar-occurrence.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/calendar-occurrence.css').read_text(encoding='utf-8')
core = (ROOT / 'public/js/calendar-core.js').read_text(encoding='utf-8')
polish = (ROOT / 'public/js/calendar-polish.js').read_text(encoding='utf-8')
loader = (ROOT / 'public/js/calendar.js').read_text(encoding='utf-8')
domain = (ROOT / 'app/calendar_recurrence.php').read_text(encoding='utf-8')
exception = (ROOT / 'app/calendar_exception.php').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
modals = [
    (ROOT / 'app/view/dashboard_modals.php').read_text(encoding='utf-8'),
    (ROOT / 'public/stock.php').read_text(encoding='utf-8'),
]

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


for modal in modals:
    check('value="occurrence" checked' in modal and 'この予定のみ' in modal, 'edit modal defaults to this occurrence only')
    check('value="series"' in modal and 'シリーズ全体' in modal, 'edit modal offers explicit whole-series scope')
    check('restore_calendar_occurrence' in modal and '個別変更を元に戻す' in modal, 'edit modal includes override restore action')
    check('changeCalendarOccurrenceOriginalStartDate' in modal and 'changeCalendarOccurrenceRevision' in modal, 'edit modal keeps stable occurrence identity and revision')

check(loader.index('calendar-occurrence.js') < loader.index('calendar-recurrence.js'), 'occurrence handler loads before series submit handler')
check('calendar-occurrence.css?v=1.33.1' in loader, 'formal V1.33 release keeps the E CSS contract')
check('calendar-occurrence.js?v=1.33.1' in loader, 'formal V1.33 release keeps the E JavaScript contract')
check("const APP_VERSION = '1.33.1';" in version, 'formal V1.33 release keeps the E version contract')
check("const APP_ASSET_REVISION = '1.33.1';" in version, 'formal V1.33 release keeps the E cache contract')

for field in ('source_title', 'source_note', 'source_color', 'source_all_day', 'source_start_time', 'source_end_time', 'source_url'):
    check(f"'{field}'" in domain and f"'{field}'" in exception, f'{field} survives base and override responses')

for action in ('calendar.occurrence.update', 'calendar.occurrence.cancel', 'calendar.occurrence.restore'):
    check(f"'{action}'" in js, f'UI calls fixed {action} action')
check('original_occurrence_start_date' in js and 'occurrence_revision' in js, 'every individual mutation carries stable identity and optimistic token')
check("xhr.status === 409" in js and "calendar:occurrenceChanged" in js, 'conflict refreshes visible Calendar state')
check("selectedScope(form) !== 'occurrence'" in js, 'whole-series submit is left to the existing recurrence handler')
check("state.cancelled" in js and "この予定を変更して復活" in js, 'cancelled occurrence can be edited back into an override')
check("window.confirm('この予定のみ取り消しますか？')" in js, 'single cancellation requires explicit confirmation')
check("window.confirm('この回の個別変更または取消を元に戻しますか？')" in js, 'restore requires explicit confirmation')
check("form.getAttribute('aria-busy') === 'true'" in js, 'duplicate occurrence mutations are blocked')
check("data: $.extend({}, data || {}, {action: action, csrf_token: csrfToken()})" in js, 'occurrence UI keeps CSRF on every request')
check("textContent" in js and not re.search(r'\.innerHTML\s*=', js), 'occurrence UI renders untrusted values as text')
check('eval(' not in js and 'document.write' not in js, 'occurrence UI introduces no executable-string sink')
check("data.cancelled_occurrences" in core and "calendar-event-cancelled" in core, 'month view exposes cancelled occurrences for restore')
check(".text('取消済み')" in core, 'cancelled marker is text-rendered')
check(core.count("calendar:occurrenceChanged") >= 1, 'month view refreshes after individual mutation')
check(polish.count("calendar:occurrenceChanged") >= 1 and 'upcomingPromise = null' in polish, 'upcoming list refreshes independently after mutation')
check('@media (max-width: 575.98px)' in css, 'scope actions include Smartphone layout rules')
check('@media (prefers-color-scheme: dark)' in css, 'scope and cancellation cues include Dark Mode rules')
check('DROP ' not in domain.upper() and 'ALTER ' not in domain.upper(), 'E response metadata needs no schema mutation')

print(f'RESULT: PASS {passed} / FAIL {failed} / SKIP 0')
raise SystemExit(0 if failed == 0 else 1)
