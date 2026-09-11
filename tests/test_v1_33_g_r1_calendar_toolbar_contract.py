#!/usr/bin/env python3
from pathlib import Path
import re

from version_contract_utils import read_app_version_constants

root = Path(__file__).resolve().parents[1]
dashboard = (root / 'app/view/dashboard_widgets.php').read_text(encoding='utf-8')
stock = (root / 'public/stock.php').read_text(encoding='utf-8')
css = (root / 'public/css/calendar-views.css').read_text(encoding='utf-8')
loader = (root / 'public/js/calendar.js').read_text(encoding='utf-8')
version_constants = read_app_version_constants(root)
current_version = version_constants.get('APP_VERSION', '')
current_revision = version_constants.get('APP_ASSET_REVISION', '')
version_match = re.fullmatch(r'(\d+)\.(\d+)\.(\d+)(?:-(?:rc\d+|dev\.\d+))?', current_version)

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
    toolbar_start = markup.index('<div class="calendar-toolbar">')
    weekdays_start = markup.index('<div class="calendar-weekdays"', toolbar_start)
    toolbar_markup = markup[toolbar_start:weekdays_start]

    check(toolbar_markup.count('class="calendar-toolbar"') == 1
          and toolbar_markup.count('class="btn-group btn-group-sm calendar-view-switch"') == 1,
          f'{name} keeps the view switch inside the Calendar toolbar')
    check(toolbar_markup.index('calendar-prev-month')
          < toolbar_markup.index('calendar-today')
          < toolbar_markup.index('calendar-month-label')
          < toolbar_markup.index('calendar-view-switch')
          < toolbar_markup.index('calendar-next-month')
          < toolbar_markup.index('calendar-event-add-trigger'),
          f'{name} toolbar keeps navigation, period, view and add controls in visual order')
    check(toolbar_markup.count('data-calendar-view-mode="day"') == 1
          and toolbar_markup.count('data-calendar-view-mode="week"') == 1
          and toolbar_markup.count('data-calendar-view-mode="month"') == 1,
          f'{name} retains one accessible day/week/month control group')

check('grid-template-areas: "prev today label switch next add";' in css
      and 'grid-template-columns: auto auto minmax(8rem, 1fr) minmax(9rem, 15rem) auto auto;' in css,
      'wide Calendar toolbar uses one six-part row')
check(css.count('"prev today label next add"') >= 2
      and css.count('"switch switch switch switch switch"') >= 2,
      'compact card and Smartphone toolbar use the intended two-row layout')
check('grid-area: switch;' in css and 'width: min(100%, 15rem);' in css,
      'view switch stays centered and bounded in compact layouts')
check(version_match is not None and tuple(map(int, version_match.groups()[:3])) >= (1, 33, 1),
      'G-R1 contract runs on V1.33.1 or later')
check(bool(current_revision) and current_revision == current_version,
      'current asset revision matches the application version')
check(f'calendar-views.css?v={current_revision}' in loader,
      'G-R1 Calendar CSS uses the current cache revision')
check('calendar_range_start' not in css and 'calendar.range.list' not in css,
      'display-only CSS adds no Calendar API behavior')

print(f'RESULT: PASS {passed} / FAIL {failed} / SKIP 0')
raise SystemExit(0 if failed == 0 else 1)
