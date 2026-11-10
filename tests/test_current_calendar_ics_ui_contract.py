#!/usr/bin/env python3
"""Current Dev1.1 Calendar Manager UI contract (non-browser).
Ensures both local and ICS management remain available and request semantics
are unchanged, while tabs, status indicators and busy state stay accessible.
"""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
read = lambda path: (ROOT / path).read_text(encoding="utf-8")
modal = read("app/view/dashboard_modals.php")
js = read("public/js/calendar-ics.js")
css = read("public/css/calendar-ics.css")
local_js = read("public/js/calendar-sources.js")
api = read("public/calendar_source_api.php")
version = read("app/version.php")
checks = {
    "Calendar Manager remains one modal":
        modal.count('id="calendarSourceManager"') == 1,
    "Bootstrap tabs are keyboard-aware and accessible":
        all(x in modal for x in [
            'id="calendarManagerLocalTab"', 'id="calendarManagerIcsTab"',
            'id="calendarManagerLocalPane"', 'id="calendarManagerIcsPane"',
            'role="tablist"', 'role="tab"', 'role="tabpanel"',
            'data-bs-toggle="tab"', 'aria-controls="calendarManagerIcsPane"',
            'aria-selected="true"', 'aria-selected="false"',
        ]) and modal.count('data-bs-toggle="tab"') >= 2,
    "Normal Calendar remains intact":
        all(x in modal for x in [
            'id="calendarSourceCreateForm"',
            'calendarSourceCreateName', 'calendarSourceCreateColor',
            'calendar-source-manager-list',
        ]),
    "External ICS remains intact":
        all(x in modal for x in [
            'id="calendarIcsCreateForm"', 'calendarIcsCreateName',
            'calendarIcsCreateColor', 'calendarIcsCreateUrl',
            'calendar-ics-manager-list',
        ]),
    "Primary header matches event entry UI":
        'calendar-manager-modal' in modal and
        'modal-header bg-primary text-white' in modal and
        'btn-close btn-close-white' in modal,
    "Existing ICS operations retained":
        all(x in js for x in [
            "calendar.ics.create", "calendar.ics.list",
            "calendar.ics.update", "calendar.ics.delete",
            "calendar.ics.refresh",
        ]) and 'calendar.ics.refresh' in api,
    "ICS fetch button has clear outline color":
        'btn-outline-primary calendar-ics-refresh' in js and
        '.calendar-ics-refresh:hover' in css and
        '.calendar-ics-refresh:disabled' in css,
    "ICS spinner and busy semantics are present":
        'spinner-border spinner-border-sm' in js and
        "attr('aria-busy', 'true')" in js and
        "removeAttr('aria-busy')" in js and '取得中…' in js,
    "Result status has clear three-state badges":
        all(x in js for x in [
            '取得成功', '取得失敗', '未取得',
            'text-bg-success', 'text-bg-danger', 'text-bg-secondary',
            "statusDetail(item)", "statusColor(item)",
        ]) and 'calendar-ics-status' in css,
    "Private URL is not injected as displayed data":
        'text(String(item.host || \'\'))' in js and
        'item.ics_url' not in js and 'item.ics_raw' not in js and
        "placeholder:'現在のURLは非表示・変更時のみ入力'" in js,
    "Existing local calendar source JS untouched in behavior":
        all(x in local_js for x in [
            "calendar.source.create", "calendar.source.update",
            "calendar.source.delete", "calendar-source-manager-list",
        ]),
    "Responsive and theme-aware layout":
        all(x in css for x in [
            "var(--bs-body-color", "var(--bs-border-color",
            "var(--bs-primary-rgb", "@media (max-width: 420px)",
        ]),
    "Versioned CSS & JS continue via Calendar loader":
        "calendar-ics.css" in read("public/js/calendar.js") and
        "calendar-ics.js" in read("public/js/calendar.js"),
    "Version + asset revision align":
        bool(re.search(r"APP_VERSION = '([^']+)'", version)) and
        re.search(r"APP_VERSION = '([^']+)'", version).group(1) ==
        re.search(r"APP_ASSET_REVISION = '([^']+)'", version).group(1),
}
failed = 0
for name, ok in checks.items():
    print(("PASS" if ok else "FAIL") + ": " + name)
    failed += not ok
print(f"RESULT: PASS {len(checks)-failed} / FAIL {failed}")
raise SystemExit(bool(failed))
