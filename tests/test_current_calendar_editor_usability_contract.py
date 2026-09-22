from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def check(condition: bool, label: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + label)


views = text('public/js/calendar-views.js')
core = text('public/js/calendar-core.js')
details = text('public/js/calendar-event-details.js')
dashboard_css = text('public/css/dashboard.css')
page = text('public/remote-editor.php')
editor_js = text('public/js/remote-editor.js')
editor_css = text('public/css/remote-editor.css')
calendar_range = text('app/calendar_range.php')
runner = text('tests/run-current-features.sh')

check('CALENDAR_RANGE_MAX_DAYS = 42' in calendar_range, 'server keeps the bounded 42-day Calendar range')
check("addDays(start, -startDate.getUTCDay())" in views, 'month range begins on the visible Sunday')
check("addDays(end, 6 - endDate.getUTCDay())" in views, 'month range ends on the visible Saturday')
check('calendar-day-outside-month' in core, 'Calendar marks adjacent-month cells explicitly')
check('.calendar-day-outside-month' in dashboard_css, 'adjacent-month cells have a dimmed visual treatment')
check("'(hover: hover) and (pointer: fine)'" in details, 'modal focus uses input-capability detection')
check("shown.bs.modal" in details and '#registerCalendarEvent' in details,
      'title focus waits for the add-event modal to finish opening')
check('preventScroll: true' in details, 'automatic title focus avoids scroll movement')
check('userAgent' not in details, 'focus behavior does not depend on User-Agent detection')

check('id="remoteEditorSurface"' in page, 'Remote Editor exposes a dedicated editor surface')
check('id="remoteEditorLineNumbers"' in page and 'aria-hidden="true"' in page,
      'line-number gutter is visual-only for assistive technology')
check('id="remoteEditorLineNumbersContent"' in page, 'line-number content has a narrow update target')
check('remote-editor-line-numbers' in editor_css and 'user-select: none' in editor_css,
      'line numbers are aligned and cannot pollute text selection')
check('el.lineNumbers.scrollTop = el.text.scrollTop' in editor_js,
      'line-number gutter follows vertical textarea scrolling')
check("el.lineNumbersContent.textContent = lines.join('\\n')" in editor_js,
      'line numbers use inert text rendering')
check('innerHTML' not in editor_js, 'Remote Editor retains its no-innerHTML security boundary')
check('text_base64: textBase64' in editor_js and 'el.text.value' in editor_js,
      'Remote save remains sourced only from the textarea')
check('remoteEditorLineNumbersContent' not in editor_js[editor_js.find('async function saveRemoteText'):],
      'save path never reads line-number content')

for test_name in [
    'test_current_calendar_adjacent_months.js',
    'test_current_calendar_modal_focus.js',
    'test_current_remote_editor_line_numbers.js',
    'test_current_calendar_editor_usability_contract.py',
]:
    check(test_name in runner, f'current feature gate runs {test_name}')

migrations = list((ROOT / 'database' / 'migrations').glob('*1_35_1*'))
check(migrations == [], 'UI improvements add no database migration')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
