from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def check(condition: bool, label: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + label)


def function_body(source: str, name: str, next_name: str) -> str:
    start = source.index('function ' + name + '(')
    end = source.index('function ' + next_name + '(', start)
    return source[start:end]


core = text('public/js/calendar-core.js')
details = text('public/js/calendar-event-details.js')
recurrence = text('public/js/calendar-recurrence.js')
occurrence = text('public/js/calendar-occurrence.js')
drag_drop = text('public/js/calendar-drag-drop.js')
polish = text('public/js/calendar-polish.js')
runner = text('tests/run-current-features.sh')

core_add = function_body(core, 'addCalendarEvent', 'editCalendarEvent')
core_change = function_body(core, 'changeCalendarEvent', 'deleteCalendarEvent')
core_delete = function_body(core, 'deleteCalendarEvent', 'addItemToDate')
widget_change = function_body(core, 'changeCalendarWidget', 'deleteCalendarWidget')
detail_submit = function_body(details, 'submitEvent', 'captureSubmit')
recurrence_submit = function_body(recurrence, 'submitEvent', 'captureSubmit')

for label, body in [
    ('fallback event create', core_add),
    ('fallback event update', core_change),
    ('event delete', core_delete),
    ('detailed event create/update', detail_submit),
    ('production recurrence create/update', recurrence_submit),
]:
    check('window.location.reload()' not in body, f'{label} does not reload the page')

check(core_add.count('completeCalendarEventMutation(') == 1,
      'fallback event create completes through the shared partial-refresh path')
check(core_change.count('completeCalendarEventMutation(') == 1,
      'fallback event update completes through the shared partial-refresh path')
check(core_delete.count('completeCalendarEventMutation(') == 1,
      'event delete completes through the shared partial-refresh path')
check('completeEventMutation(form,' in detail_submit,
      'detailed ordinary and series save uses the partial-refresh path')
check('completeEventMutation(form,' in recurrence_submit,
      'production ordinary and series save uses the partial-refresh path')
check("trigger('calendar:occurrenceChanged')" in core,
      'fallback create/update/delete emits the existing Calendar refresh event')
check("trigger('calendar:occurrenceChanged')" in details,
      'detailed create/update emits the existing Calendar refresh event')
check("trigger('calendar:occurrenceChanged')" in recurrence,
      'production create/update emits the existing Calendar refresh event')
check(".on('calendar:occurrenceChanged' + eventNamespace, refreshVisibleCalendars)" in core,
      'Calendar refresh event redraws every visible Calendar card')
check(".on('calendar:occurrenceChanged' + namespace" in polish and 'loadUpcoming();' in polish,
      'Calendar refresh event also refreshes the upcoming projection')
check('window.location.reload()' in widget_change,
      'Calendar Widget setting changes intentionally keep their existing full-page reload')
check("trigger('calendar:occurrenceChanged')" in occurrence,
      'occurrence-only updates retain their partial refresh')
check("trigger('calendar:occurrenceChanged')" in drag_drop,
      'drag and drop retains its partial refresh')
check('test_current_calendar_event_partial_refresh.js' in runner,
      'current feature gate runs the Calendar event partial-refresh runtime test')
check('test_current_calendar_recurrence_partial_refresh.js' in runner,
      'current feature gate runs the production recurrence partial-refresh runtime test')
check('test_current_calendar_event_partial_refresh_contract.py' in runner,
      'current feature gate runs the Calendar event partial-refresh contract')
check(not list((ROOT / 'database' / 'migrations').glob('*1_35_2*')),
      'Calendar partial refresh adds no database migration')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
