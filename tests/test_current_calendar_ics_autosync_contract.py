#!/usr/bin/env python3
"""ICS Dev4 auto-refresh, cache, permissions and UI flow contract."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
read = lambda path: (ROOT/path).read_text(encoding="utf-8")
model = read("app/calendar_ics.php")
api = read("public/calendar_source_api.php")
client = read("public/js/calendar-ics.js")
core = read("public/js/calendar-core.js")
range_api = read("app/calendar_range.php")
modal = read("app/view/dashboard_modals.php")
version = read("app/version.php")

checks = {
    "Server-side 30-minute automatic refresh TTL":
        "CALENDAR_ICS_AUTO_REFRESH_SECONDS = 1800" in model
        and "CALENDAR_ICS_MIN_REFRESH_SECONDS = 60" in model,
    "Auto sync safely queries only owner-specific feeds":
        "function calendar_ics_sync_due" in model
        and "ics_owner = :owner" in model
        and "calendar_ics_schema_ready($pdo)" in model,
    "One remote feed maximum per request":
        "CALENDAR_ICS_SYNC_BATCH_SIZE = 1" in model
        and "LIMIT ' . CALENDAR_ICS_SYNC_BATCH_SIZE" in model,
    "Cache success updates body; error preserves previous cache":
        "ics_raw = :body" in model
        and "SET ics_status = 'error'" in model
        and "ics_last_checked_at = :checked" in model
        and "ics_url = :url" in model,
    "Full sync reports safe metadata only":
        all(key in model for key in ["'attempted'","'changed'","'failed'","'remaining'"])
        and "interval_minutes" in model
        and "'ics_raw' =>" not in model,
    "Authenticated CSRF-protected endpoint allows sync action":
        "'calendar.ics.sync_due'" in api
        and "app_csrf_is_valid($csrfToken)" in api
        and "calendar_ics_sync_due($userId" in api
        and "calendar.ics.list" in api,
    "Manual sync bypasses 30-minute TTL while preserving 60s cap":
        "manual === '1'" in api
        and "manual ? CALENDAR_ICS_MIN_REFRESH_SECONDS : CALENDAR_ICS_AUTO_REFRESH_SECONDS" in model,
    "Visible Calendar checks for due feeds and page resume":
        "calendar:rangeLoaded" in client
        and "AUTO_CHECK_MS = 180000" in client
        and "visibilitychange" in client
        and "pageshow" in client,
    "Calendar refresh button triggers remote ICS sync first":
        "ics.syncDue(true).always(function ()" in core
        and "refreshCalendarsQuietly($card)" in core,
    "Calendar refreshes when cached external events change":
        "calendar:icsCacheChanged" in client
        and "calendar:icsCacheChanged" in core,
    "ICS subscription manual refresh notifies Calendar":
        "response.data.refresh.changed === true" in client
        and "'refresh' => $refresh" in api,
    "No external fetch occurs in Calendar range endpoint":
        "calendar_ics_range($ownerId" in range_api
        and "calendar_ics_sync_due(" not in range_api
        and "calendar_ics_refresh(" not in range_api,
    "Status explains stale cached data on failures":
        "前回の予定を表示中" in client
        and "最短60秒" in modal
        and "30分" in modal,
    "Version and asset revision agree":
        re.search(r"APP_VERSION = '([^']+)'",version) is not None
        and re.search(r"APP_VERSION = '([^']+)'",version).group(1)
        == re.search(r"APP_ASSET_REVISION = '([^']+)'",version).group(1),
}
bad=0
for title,ok in checks.items():
    print(("PASS" if ok else "FAIL")+": "+title)
    bad+=int(not ok)
print(f"RESULT: PASS {len(checks)-bad} / FAIL {bad}")
raise SystemExit(bad>0)
