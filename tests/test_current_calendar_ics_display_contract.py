#!/usr/bin/env python3
"""ICS Dev3 display contract: shared Calendar API, read-only UI, source filtering."""
from pathlib import Path
import re

ROOT=Path(__file__).resolve().parents[1]
read=lambda name:(ROOT/name).read_text(encoding="utf-8")
backend=read("app/calendar_range.php")
parser=read("app/calendar_ics_parser.php")
core=read("public/js/calendar-core.js")
sources=read("public/js/calendar-sources.js")
modal=read("app/view/dashboard_modals.php")
css=read("public/css/calendar-ics.css")
version=read("app/version.php")
checks={
    "Range endpoint reuses original Calendar API":
        "calendar_ics_range($ownerId" in backend
        and "array_merge($sourceState['events'], $icsState['events'])" in backend
        and "array_merge($sourceState['sources'], $icsState['sources'])" in backend,
    "Unparsed cached ICS is read owner-scoped":
        "ics_owner = :owner" in parser and "ics_raw" in parser
        and "calendar_ics_schema_ready" in parser
        and "CALENDAR_ICS_PARSE_MAX_OCCURRENCES" in parser,
    "Event is explicitly read only and retains ICS filter source":
        "'external_read_only'=>true" in parser
        and "'source_type'=>'ics'" in parser
        and "'calendar_source_id'=>'ics:'.$subscriptionId" in parser,
    "Recurrence and exceptions are distinct from local DB":
        all(x in parser for x in ["RRULE","BYSETPOS","EXDATE","RDATE","RECURRENCE-ID","STATUS"])
        and "INSERT INTO" not in parser and "UPDATE " not in parser,
    "Source filters allow ICS IDs while add/edit remains local-only":
        "ics:[1-9][0-9]*" in sources
        and "item.source_type !== 'ics'" in sources
        and "return normalizeSources(data && Array.isArray(data.sources)" in sources,
    "ICS UI cannot accidentally trigger local edit / drag":
        "calendar-ics-event-entry" in core
        and "if (item.external_read_only === true && item.source_type === 'ics')" in core
        and core.index("if (item.external_read_only === true") <
            core.index("calendar-event-edit-trigger calendar-event-color-"),
    "ICS read-only modal is separate from local edit modal":
        'id="calendarIcsEventDetails"' in modal
        and 'id="changeCalendarEvent"' in modal
        and "window.bootstrap.Modal.getOrCreateInstance(modal).show()" in core,
    "Untrusted calendar text inserted as text rather than HTML":
        ".find('.calendar-ics-detail-title').text(" in core
        and ".find('.calendar-ics-detail-description').text(" in core
        and ".find('.calendar-ics-detail-location').text(" in core,
    "Link accepts HTTPS only and uses noopener noreferrer":
        "if (/^https:" in core
        and 'rel="noopener noreferrer"' in modal,
    "ICS UI is visible in month/week/day view through shared item renderer":
        "function calendarItemButton(item, date)" in core
        and "calendarItemElement(item, date)" in core
        and "appendDayEntry($allDayEntries, item" in core,
    "Matte, read-only visual style retains theme variables":
        ".calendar-ics-event-entry" in css
        and ".calendar-ics-event-details-modal" in css
        and "var(--bs-" in css,
    "Version aligns with JS/CSS cache busting":
        bool(re.search(r"APP_VERSION = '([^']+)'",version))
        and re.search(r"APP_VERSION = '([^']+)'",version).group(1)
        == re.search(r"APP_ASSET_REVISION = '([^']+)'",version).group(1),
}
bad=0
for label,ok in checks.items():
    print(("PASS" if ok else "FAIL")+": "+label)
    bad+=not ok
print(f"RESULT: PASS {len(checks)-bad} / FAIL {bad}")
raise SystemExit(bool(bad))
