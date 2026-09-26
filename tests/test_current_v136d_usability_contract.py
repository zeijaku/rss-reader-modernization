from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
checks = []

def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")

def check(value: bool, label: str) -> None:
    checks.append(bool(value))
    print(("PASS" if value else "FAIL") + ": " + label)

usability = read("public/js/calendar-usability.js")
loader = read("public/js/calendar.js")
modals = read("app/view/dashboard_modals.php")
details_css = read("public/css/calendar-event-details.css")
recurrence_css = read("public/css/calendar-recurrence.css")
occurrence_css = read("public/css/calendar-occurrence.css")
dashboard_css = read("public/css/dashboard.css")
version = read("app/version.php")

check("calendar-usability.js" in loader, "Calendar loader includes V1.36-D usability layer")
check("oldEnd - oldStart" in usability and "newStart +" in usability,
      "start movement preserves the existing exact duration")
check("endDate.min = values.startDate" in usability,
      "end date gets a client-side minimum equal to start date")
check("endTime.min = values.startTime" in usability,
      "same-day end time gets a client-side minimum equal to start time")
check("calendar-event-more" in usability and "詳細（URL・メモ）" in usability,
      "URL and memo remain available in a compact details section")
check("hasContent" in usability and "details.open = true" in usability,
      "existing URL or memo automatically expands details")
check("modal-dialog-scrollable modal-lg calendar-event-modal-dialog" in modals,
      "Calendar event dialogs are wide and internally scrollable")
check(modals.count('rows="2"') >= 2, "Calendar memo fields use a compact initial height")
check("grid-template-columns: repeat(2" in details_css,
      "Calendar detail controls use two columns on wider screens")
check("@media (max-width: 575.98px)" in details_css
      and "grid-template-columns: minmax(0, 1fr)" in details_css,
      "Calendar modal collapses back to one column on phones")
check("calendar-event-modal .calendar-event-recurrence-fields" in recurrence_css,
      "recurrence controls share the wider modal layout")
check("calendar-occurrence-scope-help" in occurrence_css,
      "Occurrence scope remains visible in the compact layout")
check("position: sticky" in dashboard_css and "z-index: 1020" in dashboard_css,
      "Dashboard navbar is sticky below Bootstrap modal/offcanvas layers")
check("position: fixed" not in dashboard_css[dashboard_css.find(".app-header"):dashboard_css.find(".app-navbar")],
      "navbar avoids fixed-position content offset management")
check("1.36.0" in version and "-dev." not in version, "V1.36 usability is promoted to the formal release version")
check(not list((ROOT / "database" / "migrations").glob("032_v1_36*")),
      "V1.36-D requires no database migration")

failed = len(checks) - sum(checks)
print(f"RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0")
raise SystemExit(1 if failed else 0)
