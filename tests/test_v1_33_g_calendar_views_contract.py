#!/usr/bin/env python3
from pathlib import Path

root = Path(__file__).resolve().parents[1]
core = (root / 'public/js/calendar-core.js').read_text(encoding='utf-8')
views = (root / 'public/js/calendar-views.js').read_text(encoding='utf-8')
loader = (root / 'public/js/calendar.js').read_text(encoding='utf-8')
css = (root / 'public/css/calendar-views.css').read_text(encoding='utf-8')
color_css = (root / 'public/css/calendar-colors.css').read_text(encoding='utf-8')
dashboard = (root / 'app/view/dashboard_widgets.php').read_text(encoding='utf-8')
stock = (root / 'public/stock.php').read_text(encoding='utf-8')
version = (root / 'app/version.php').read_text(encoding='utf-8')

passed = 0
failed = 0


def check(condition: bool, message: str) -> None:
    global passed, failed
    if condition:
        passed += 1
        print(f'PASS: {message}')
    else:
        failed += 1
        print(f'FAIL: {message}')


for markup, name in ((dashboard, 'Dashboard'), (stock, 'Stock')):
    check(markup.count('data-calendar-view-mode="day"') == 1
          and markup.count('data-calendar-view-mode="week"') == 1
          and markup.count('data-calendar-view-mode="month"') == 1,
          f'{name} Calendar exposes one day/week/month switch')
    check('aria-label="Calendar表示"' in markup and 'aria-pressed="true"' in markup,
          f'{name} switch exposes pressed state to assistive technology')

check(loader.index('calendar-month-layout.js?v=1.33.1')
      < loader.index('calendar-views.js?v=1.33.1')
      < loader.index('calendar-core.js?v=1.33.1'),
      'pure layout modules load before the Calendar DOM core')
check('calendar-views.css?v=1.33.1-r1' in loader,
      'responsive view CSS has the immutable G-R1 cache key')
check("const APP_VERSION = '1.33.1';" in version
      and "const APP_ASSET_REVISION = '1.33.1';" in version,
      'G checkpoint version and asset revision match')

check("action: 'calendar.range.list'" in core and 'calendar_range_start: period.start' in core
      and 'calendar_range_end: period.end' in core,
      'all modes reuse the secured bounded range API')
check("data-calendar-selected-date" in core and "views.move(mode, anchor, offset)" in core,
      'mode navigation retains the selected date and moves by the active period')
check("for (var hour = 0; hour < 24; hour += 1)" in core,
      'day view keeps the full 00:00 to 24:00 timeline available')
check("calendar-timeline-entry-open-ended" in core and "calendar-timeline-entry-zero-duration" in core,
      'missing end time and equal start/end retain different UI states')
check("window.ResizeObserver" in core and "width < 720" in core,
      'compact week fallback is based on actual card width')
check(".text(item.title)" in core and '.html(item.title' not in core and '.html(item.note' not in core,
      'Calendar title/note rendering does not add an HTML sink')

check('.calendar-day-timeline' in css and 'height: 1152px' in css
      and '.calendar-timeline-entry' in css,
      'day view CSS supplies a bounded scrollable 24-hour timeline')
check('.calendar-view-compact .calendar-days.calendar-week-view' in css
      and '@media (max-width: 575.98px)' in css,
      'week list has both card-width and Smartphone fallbacks')
check('--calendar-entry-accent:' in color_css and 'border-left-color: var(--calendar-entry-accent' in css,
      'compact week list restores every five-color accent after connected-bar CSS')
check('bootstrap-solar' in css and 'bootstrap-slate' in css,
      'new date surfaces include the two supported dark Theme adjustments')
check('innerHTML' not in views and 'eval(' not in views and 'document.write' not in views,
      'pure date/layout module introduces no DOM or code-evaluation sink')
check('CALENDAR_RANGE_MAX_DAYS = 42' not in core,
      'G does not duplicate or weaken the server range limit in the client')

print(f'RESULT: PASS {passed} / FAIL {failed} / SKIP 0')
raise SystemExit(0 if failed == 0 else 1)
