#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")

def require(text: str, needle: str, message: str) -> None:
    if needle not in text:
        raise AssertionError(message + f"\nMissing: {needle}")

migration = read("database/migrations/033_v1_44_calendar_source.sql")
require(migration, "CREATE TABLE IF NOT EXISTS", "Migration must create the Calendar source table.")
require(migration, "calendar_event_source_id", "Migration must add event source ownership.")
require(migration, "既定Calendar", "Migration must preserve existing events through a default Calendar.")
require(migration, "IS NULL", "Migration must only backfill unassigned events.")
require(migration, "@v144a_has_event_source_index", "Migration must recover a missing event source index independently.")

bootstrap = read("app/bootstrap.php")
require(bootstrap, "calendar_source.php", "Calendar source model must be loaded by bootstrap.")

common_conf = read("app/common/common_conf.php")
require(common_conf, "'calendar_source'", "Calendar source table must be present in the DB identifier allowlist.")

source_model = read("app/calendar_source.php")
for needle in [
    "CALENDAR_SOURCE_MAX_ACTIVE = 20",
    "calendar_source_schema_ready",
    "calendar_source_ensure_default",
    "calendar_source_create",
    "calendar_source_update",
    "calendar_source_delete",
    "calendar_source_assign_event",
    "calendar_source_attach_to_events",
    "calendar_source_owner = :owner",
]:
    require(source_model, needle, "Calendar source backend contract is incomplete.")

public_htaccess = read("public/.htaccess")
require(public_htaccess, "calendar_source_api\\.php$", "Calendar source API must be allowed by the public PHP endpoint matrix.")

source_api = read("public/calendar_source_api.php")
for action in [
    "calendar.source.list",
    "calendar.source.create",
    "calendar.source.update",
    "calendar.source.delete",
]:
    require(source_api, action, f"Calendar source API must expose {action}.")
for needle in ["app_csrf_is_valid", "app_session_user_id", "APP_API_MAX_REQUEST_BYTES", "calendar_source_schema_ready"]:
    require(source_api, needle, "Calendar source API security boundary is incomplete.")

range_php = read("app/calendar_range.php")
require(range_php, "calendar_source_attach_to_events", "Calendar range must attach source metadata.")
require(range_php, "'sources' => $sourceState['sources']", "Calendar range must return source definitions.")
require(range_php, "$cancelledSourceState", "Cancelled recurring occurrences must retain source metadata.")

widgets = read("app/view/dashboard_widgets.php")
require(widgets, "calendar-source-filter-toggle", "Calendar Widget needs a source filter control.")
require(widgets, "calendar-source-filter-menu", "Calendar Widget needs a source filter menu.")

modals = read("app/view/dashboard_modals.php")
for needle in [
    "registerCalendarEventSource",
    "changeCalendarEventSource",
    "calendarSourceManager",
    "calendarSourceCreateForm",
    "個別OccurrenceではCalendarを変更出来ません",
]:
    require(modals, needle, "Calendar source forms are incomplete.")

loader = read("public/js/calendar.js")
require(loader, "calendar-sources.css", "Calendar source stylesheet must be loaded.")
require(loader, "calendar-sources.js", "Calendar source script must be loaded.")
if loader.index("calendar-sources.js") > loader.index("calendar-core.js"):
    raise AssertionError("Calendar source helper must load before Calendar core so saved filters apply before first layout.")

core = read("public/js/calendar-core.js")
for needle in [
    "data-calendar-source-id",
    "data-calendar-source-name",
    "data-calendar-source-label-color",
    "sources:data.sources || []",
    "filterCardData",
    "calendar:sourceFilterChanged",
]:
    require(core, needle, "Calendar core must expose source metadata and include it in refresh signatures.")

for path in ["public/js/calendar-event-details.js", "public/js/calendar-recurrence.js"]:
    text = read(path)
    require(text, "calendar_source_id:", f"{path} must submit the selected Calendar source.")

calendar_time = read("app/calendar_time.php")
require(calendar_time, "$sourceId !== null && $sourceId !== ''",
        "Legacy event updates that omit Calendar source must preserve existing membership.")

drag = read("public/js/calendar-drag-drop.js")
require(drag, "sourceId:", "Drag and drop must snapshot the Calendar source.")
require(drag, "calendar_source_id: state.sourceId", "Drag and drop must preserve the Calendar source.")

copy = read("public/js/calendar-copy.js")
require(copy, "sourceId: fieldValue(form, '.changeCalendarEventSource')", "Copy must snapshot Calendar source.")
require(copy, "'.registerCalendarEventSource', snapshot.sourceId", "Copy must preserve Calendar source.")

sources_js = read("public/js/calendar-sources.js")
for needle in [
    "rss-calendar-source-hidden:",
    "calendar-source-filter-check",
    "filterRangeData",
    "calendar:sourceFilterChanged",
    "calendar.source.create",
    "calendar.source.update",
    "calendar.source.delete",
]:
    require(sources_js, needle, "Calendar source UI contract is incomplete.")

http_test = read("tests/test_v1_44_a_calendar_source_http.py")
for needle in ["method_not_allowed", "unauthenticated", "csrf_invalid", "request_too_large", "another owner Calendar"]:
    require(http_test, needle, "Calendar source HTTP boundary coverage is incomplete.")

print("V1.44-A Calendar source contract: OK")
