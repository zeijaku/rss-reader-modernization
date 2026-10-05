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
require(migration, "__migration_033_event_source__", "Migration must persist a hidden V1.44-A DDL marker.")
require(migration, "calendar_source_flag", "Migration marker must use the Calendar source flag boundary.")
require(migration, "255", "Migration marker must stay outside active Calendar-source rows.")
if "information_schema" in migration.lower():
    raise AssertionError("V1.44-A migration must not read information_schema on restricted shared-hosting accounts.")

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
if "calendar-source-bar" in widgets:
    raise AssertionError("Calendar source filter must stay in the primary Calendar toolbar on normal-width layouts.")
require(widgets, "calendar-toolbar-actions", "Calendar source filter must share one toolbar action group with next/refresh/add controls.")

views_css = read("public/css/calendar-views.css")
require(views_css, '"prev today switch actions"', "Desktop Calendar toolbar must keep right-side actions on the first row.")
require(views_css, ".calendar-toolbar-actions", "Calendar source filter and right-side buttons must be grouped in one actions cell.")
require(views_css, ".calendar-toolbar-stack .calendar-toolbar-with-sources", "Only the dedicated toolbar stack class may move grouped actions to a second row.")

core_js = read("public/js/calendar-core.js")
require(core_js, "width > 0 && width < 720", "Week-view compact behavior must retain its 720px card-width threshold.")
require(core_js, "width > 0 && width < 560", "Toolbar stacking must use the narrower dedicated threshold.")

sources_css = read("public/css/calendar-sources.css")
require(sources_css, ".calendar-source-icon-blue", "Calendar source stylesheet must expose the blue layer-icon marker.")
require(sources_css, "color: #60a5fa;", "Blue Calendar source layer icon must keep the softer V1.44 review color.")
if ".calendar-source-dot" in sources_css:
    raise AssertionError("Legacy circular Calendar source markers must be removed.")

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
    "fas fa-layer-group calendar-source-icon",
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
    "fas fa-layer-group calendar-source-icon",
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
