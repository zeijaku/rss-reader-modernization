#!/usr/bin/env python3
"""Calendar event-entry UI regression contract; no DB/browser runner required."""
from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]
read = lambda name: (root / name).read_text(encoding="utf-8")
loader, modal, entry, colors, css, version = (
    read("public/js/calendar.js"),
    read("app/view/dashboard_modals.php"),
    read("public/js/calendar-entry-ui.js"),
    read("public/js/calendar-colors.js"),
    read("public/css/calendar-entry-ui.css"),
    read("app/version.php"),
)
tests = {
    "Versioned entry script and CSS":
        "loadScript(assetUrl('./js/calendar-entry-ui.js'))" in loader
        and "loadStyle(assetUrl('./css/calendar-entry-ui.css')" in loader,
    "Add and edit forms retained":
        all(f'id="{name}CalendarEventForm"' in modal for name in ("register", "change")),
    "UI layer has no duplicate save API":
        "calendar.color.update" not in entry and "calendar.color.create" not in entry,
    "Duration presets and midnight rollover supported":
        all(f"['{n}'" in entry for n in (30, 60, 120))
        and "getUTCDate()" in entry,
    "Original calendar color request preserved":
        "calendar_event_color: validColor" in colors
        and "['red', 'blue', 'green', 'yellow', 'purple']" in colors,
    "Matte square swatches replace native colored emoji":
        "--calendar-matte-blue" in css
        and ".calendar-color-swatch" in css
        and all(h in css for h in ("#6f8eac", "#ba777b", "#779887", "#c0a469", "#9b86ad"))
        and not any(e in colors for e in ("🔵", "🔴", "🟢", "🟡", "🟣")),
    "Native select fallback retained":
        "window.bootstrap.Dropdown" in colors and "select.hidden = true" in colors,
    "Bootstrap modal event integration retained":
        "shown.bs.modal" in entry and "calendar-color-choice" in colors,
    "Version and asset key match":
        re.search(r"APP_VERSION = '([^']+)'", version) is not None
        and re.search(r"APP_VERSION = '([^']+)'", version).group(1)
        == re.search(r"APP_ASSET_REVISION = '([^']+)'", version).group(1),
}
failed = 0
for label, good in tests.items():
    print(("PASS" if good else "FAIL") + ": " + label)
    failed += int(not good)
print(f"RESULT: PASS {len(tests) - failed} / FAIL {failed}")
raise SystemExit(failed > 0)
