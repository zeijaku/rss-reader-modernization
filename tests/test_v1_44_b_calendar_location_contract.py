#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
files = {
    'migration': (ROOT / 'database/migrations/034_v1_44_calendar_location.sql').read_text(encoding='utf-8'),
    'schema': (ROOT / 'database/schema.sql').read_text(encoding='utf-8'),
    'time': (ROOT / 'app/calendar_time.php').read_text(encoding='utf-8'),
    'range': (ROOT / 'app/calendar_range.php').read_text(encoding='utf-8'),
    'recurrence': (ROOT / 'app/calendar_recurrence.php').read_text(encoding='utf-8'),
    'exception': (ROOT / 'app/calendar_exception.php').read_text(encoding='utf-8'),
    'color_api': (ROOT / 'public/calendar_color_api.php').read_text(encoding='utf-8'),
    'recurrence_api': (ROOT / 'public/calendar_recurrence_api.php').read_text(encoding='utf-8'),
    'details': (ROOT / 'public/js/calendar-event-details.js').read_text(encoding='utf-8'),
    'usability': (ROOT / 'public/js/calendar-usability.js').read_text(encoding='utf-8'),
    'occurrence': (ROOT / 'public/js/calendar-occurrence.js').read_text(encoding='utf-8'),
    'copy': (ROOT / 'public/js/calendar-copy.js').read_text(encoding='utf-8'),
    'drag': (ROOT / 'public/js/calendar-drag-drop.js').read_text(encoding='utf-8'),
}

checks = []
def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

for column in ('calendar_event_location', 'calendar_event_exception_location'):
    check(column in files['migration'] and column in files['schema'],
          f'{column} is present in upgrade migration and fresh schema')

check('information_schema' not in files['migration'].lower(),
      'upgrade migration avoids information_schema for restricted shared-hosting accounts')
check('@t_schema_migration' in files['migration']
      and '034:v1.44-b:event-location' in files['migration']
      and '034:v1.44-b:exception-location' in files['migration'],
      'upgrade migration tracks parent and occurrence DDL with app-owned markers')

check("$_POST['calendar_event_location'] ?? ''" in files['color_api']
      and "$_POST['calendar_event_location'] ?? ''" in files['recurrence_api'],
      'normal and recurring event APIs accept optional location')
check('calendar_event_owner = :owner AND calendar_event_flag = 0' in files['time'],
      'location persistence remains owner-scoped')
check("'location' =>" in files['range']
      and "'source_location' =>" in files['recurrence']
      and 'calendar_event_exception_location' in files['exception'],
      'range, recurring series and occurrence exceptions carry location')
check('詳細（場所・URL・メモ）' in files['usability']
      and 'content.appendChild(locationGroup)' in files['usability'],
      'details area orders Location before URL and Memo')
check('encodeURIComponent(value)' in files['details']
      and 'https://www.google.com/maps/search/?api=1&query=' in files['details'],
      'Google Maps link uses an encoded search query')
check("mapLink.target = '_blank'" in files['details']
      and "mapLink.rel = 'noopener noreferrer'" in files['details']
      and 'mapLink.hidden = true' in files['details'],
      'Maps link opens safely and is hidden for blank location')
check('calendar_event_location' in files['occurrence']
      and 'CalendarEventLocation' in files['copy']
      and 'calendar_event_location: state.location' in files['drag'],
      'occurrence editing, Copy and Drag & Drop preserve location')
check('maps.googleapis.com' not in files['details']
      and 'places.googleapis.com' not in files['details'],
      'feature introduces no Google Maps/Places API dependency')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
