#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
checks = 0
failures: list[str] = []


def text(relative: str) -> str:
    return (ROOT / relative).read_text(encoding='utf-8')


def check(condition: bool, message: str) -> None:
    global checks
    checks += 1
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failures.append(message)


range_php = text('app/calendar_range.php')
recurrence_php = text('app/calendar_recurrence.php')
upcoming_php = text('app/calendar_upcoming.php')
api = text('public/calendar_recurrence_api.php')
core = text('public/js/calendar-core.js')
recurrence_js = text('public/js/calendar-recurrence.js')
details_js = text('public/js/calendar-event-details.js')
colors_js = text('public/js/calendar-colors.js')
polish_js = text('public/js/calendar-polish.js')
loader = text('public/js/calendar.js')
version = text('app/version.php')
schema = text('database/schema.sql')
htaccess = text('public/.htaccess')

check('const CALENDAR_RANGE_MAX_DAYS = 42;' in range_php, 'server fixes the range limit at 42 days')
check('CALENDAR_RANGE_MAX_SOURCE_EVENTS = 500' in range_php, 'source event resource limit remains 500')
check('CALENDAR_RANGE_MAX_OCCURRENCES = 2000' in range_php, 'occurrence expansion resource limit remains 2000')
check('CALENDAR_RANGE_MAX_TASKS = 500' in range_php, 'Task resource limit remains 500')
check("'occurrence_key' => calendar_event_occurrence_key" in recurrence_php, 'recurring response includes stable occurrence key')
check("'original_occurrence_start_date' => $occurrenceStart" in recurrence_php, 'recurring response separates original occurrence date')
check("return 'event:' . $eventId . ':' . $originalOccurrenceStartDate" in recurrence_php, 'identity derives from parent id and original start')
check("'source_start_date'" in range_php and "'occurrence_start_date'" in range_php, 'unified data separates source and occurrence dates')
check("'kind' => 'event'" in range_php and "'kind' => 'task'" in range_php, 'unified data keeps event and Task kinds distinct')
check('isset($seen[$key])' in range_php, 'server deduplicates stable occurrence identities')
check('calendar_range_event_list($ownerId, $windowStartValue, $windowEndValue)' in upcoming_php, 'upcoming list reuses common range expansion')

check("'calendar.range.list'" in api, 'existing recurrence endpoint exposes the fixed range action')
check("calendar_range_validate($_POST['calendar_range_start']" in api, 'range input is validated server-side')
check("calendar_recurrence_positive_int($_POST['widget_id']" in api, 'range API requires a positive Widget id')
check('calendar_range_data(' in api, 'range API delegates to the common Calendar domain')
check("catch (OutOfBoundsException)" in api and "'not_found'" in api, 'foreign or missing Widget fails as not found')
check('app_session_user_id()' in api, 'range owner comes from the authenticated session')
check('app_csrf_is_valid' in api, 'range endpoint retains CSRF validation')
check('APP_API_MAX_REQUEST_BYTES' in api, 'range endpoint retains request byte limit')
check('app_session_release();' in api, 'range endpoint releases session before DB work')
check('calendar_event_owner = :owner' in range_php, 'event query remains owner scoped')
check('widget_owner = :owner' in text('app/calendar.php'), 'Widget config remains owner scoped')
check('t.task_owner = :owner' in range_php, 'Task query remains owner scoped')
check("JSON_HEX_TAG" in api and "JSON_HEX_AMP" in api, 'JSON response keeps defensive HTML-character escaping')
check('calendar_range_api.php' not in htaccess, 'C adds no public PHP endpoint or allowlist expansion')

check("action: 'calendar.range.list'" in core, 'month UI uses one unified range request')
check("url: rangeEndpoint" in core and "./calendar_recurrence_api.php" in core, 'month UI uses the existing Calendar endpoint')
check("previousRequest.abort()" in core, 'month switch aborts the previous request')
check("calendar-range-request-sequence" in core, 'Widget keeps a monotonic request sequence')
check(core.count("!== requestSequence") >= 2, 'success and failure paths both reject stale responses')
check(".attr('data-calendar-occurrence-key', occurrenceKey)" in core, 'month DOM keeps stable occurrence identity')
check(".attr('data-calendar-original-occurrence-start-date', originalStart)" in core, 'month DOM keeps original occurrence date')
check(".attr('data-calendar-event-meta-ready', '1')" in core, 'month DOM receives metadata atomically')
check("calendar-event-color-' + color" in core, 'month DOM receives color atomically')
check("calendar-event-repeat-type" in core, 'month DOM receives recurrence metadata atomically')
check("$('<span>').text(item.title)" in core, 'event title is rendered with text escaping')
check(core.count('editCalendarEvent($(this));') == 1, 'Calendar entry opens the edit path exactly once')
check('.html(item.title' not in core and '.html(item.note' not in core, 'common renderer introduces no title/note HTML sink')
check("$card.trigger('calendar:rangeLoaded'" in core, 'range completion is scoped to its Calendar card')

check('original_occurrence_start_date || item.occurrence_start_date' in recurrence_js, 'recurrence map prefers original occurrence identity')
check('map[occurrenceKey] || map[eventId]' in recurrence_js, 'edit lookup distinguishes occurrences with legacy fallback')
check("calendar:rangeLoaded" in recurrence_js, 'recurrence overlay consumes unified range state')
check("action !== 'calendar.month.list'" in colors_js, 'legacy color observer remains available only for compatibility loads')
check("action !== 'calendar.month.list'" in details_js, 'legacy metadata observer remains available only for compatibility loads')
check("data-calendar-occurrence-key" in polish_js, 'upcoming DOM carries the same occurrence identity')

version_match = re.search(r"const APP_VERSION = '([^']+)';", version)
asset_revision_match = re.search(r"const APP_ASSET_REVISION = '([^']+)';", version)
current_version = version_match.group(1) if version_match else ''
current_revision = asset_revision_match.group(1) if asset_revision_match else ''
check(bool(current_version), 'current release version is defined')
check(bool(current_revision), 'current cache revision is defined')
check('1.33.0-dev.1' not in loader, 'Calendar loader contains no stale B cache key')
check(f"calendar-core.js?v={current_revision}" in loader, 'Calendar core uses the current cache revision')
check(f"calendar-recurrence.js?v={current_revision}" in loader, 'Calendar recurrence layer uses the current cache revision')
check("`calendar_event_color` VARCHAR(8) NOT NULL DEFAULT ''blue''" in schema, 'fresh schema remains compatible without C migration')

check("calendar.event.create" not in core[core.find('function loadCalendar'):core.find('function moveCalendarMonth')], 'range load cannot enter a save action')
check("calendar_event_owner" not in core, 'client range request cannot select an owner')
check('innerHTML' not in range_php, 'server range domain has no presentation sink')
check(not re.search(r'\b(DROP|TRUNCATE|ALTER\s+TABLE)\b', range_php, re.I), 'range domain performs no destructive or schema SQL')

print(f'RESULT: PASS {checks - len(failures)} / FAIL {len(failures)} / SKIP 0')
raise SystemExit(0 if not failures else 1)
