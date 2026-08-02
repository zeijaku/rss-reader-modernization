from __future__ import annotations

from pathlib import Path
import shutil
import sys

ROOT = Path(__file__).resolve().parents[1]
try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('SKIP: Playwright Python package is unavailable.')
    raise SystemExit(0)

chromium = shutil.which('chromium') or shutil.which('chromium-browser') or shutil.which('google-chrome')
if chromium is None:
    print('SKIP: Chromium executable is unavailable.')
    raise SystemExit(0)

failures: list[str] = []


def check(condition: bool, message: str) -> None:
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failures.append(message)


html = '''<!doctype html><html lang="ja"><head>
<meta name="csrf-token" content="csrf-v11f">
</head><body>
<div id="app-notice" hidden></div><main id="main-content"></main><div id="page-top"></div>
<nav id="drawerMenu"></nav>
<div class="feed-grid" data-dashboard-widget-location="0" aria-busy="false">
<section class="dashboard-widget clock-card" data-dashboard-widget-id="41" data-dashboard-widget-type="clock" data-dashboard-widget-location="0" data-dashboard-widget-sort-order="10" data-clock-hour-format="24" data-clock-show-seconds="0" data-clock-show-date="1">
  <button type="button" class="widget-drag-handle"><span class="widget-title-text">Clock</span></button>
  <button type="button" class="clock-edit-trigger" data-widget-id="41" data-widget-style="primary" data-widget-width="1" data-clock-title="Clock" data-clock-hour-format="24" data-clock-show-seconds="0" data-clock-show-date="1"></button>
  <time class="clock-time"></time><div class="clock-date"></div>
</section>
</div>
<form id="registerClockForm"><input class="registerClockName" value="Office"><select class="registerClockHourFormat"><option value="24" selected>24</option></select><input type="checkbox" class="registerClockShowSeconds"><input type="checkbox" class="registerClockShowDate" checked><select class="registerClockStyle"><option value="primary" selected>primary</option></select><select class="registerClockWidth"><option value="1" selected>1</option></select><input class="registerClockLocation" value="0"><button type="submit">add</button></form>
<form id="changeClockForm"><input class="changeClockId"><input class="changeClockName"><select class="changeClockHourFormat"><option value="12">12</option><option value="24">24</option></select><input type="checkbox" class="changeClockShowSeconds"><input type="checkbox" class="changeClockShowDate"><select class="changeClockStyle"><option value="primary">primary</option><option value="info">info</option></select><select class="changeClockWidth"><option value="1">1</option><option value="2">2</option></select><button type="submit">change</button><button type="button" class="delete_clock">delete</button></form>
</body></html>'''

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])
    page = browser.new_page(locale='ja-JP', timezone_id='Asia/Tokyo')
    page.set_content(html)
    page.add_script_tag(path=str(ROOT / 'public/js/jquery-3.7.1.min.js'))
    page.evaluate('''() => {
      window.__ajaxCalls = [];
      window.confirm = () => true;
      jQuery.fn.popover = function(){ return this; };
      jQuery.fn.drawer = function(){ return this; };
      jQuery.fn.modal = function(){ return this; };
      jQuery.ajax = function(options){
        window.__ajaxCalls.push(options);
        const chain = {done(){return chain;}, fail(){return chain;}, always(){return chain;}};
        return chain;
      };
    }''')
    page.add_script_tag(path=str(ROOT / 'public/js/dashboard.js'))
    page.wait_for_timeout(100)

    time_text = page.locator('.clock-time').inner_text()
    date_text = page.locator('.clock-date').inner_text()
    datetime_value = page.locator('.clock-time').get_attribute('datetime') or ''
    check(':' in time_text and len(time_text) >= 5, 'real Chromium renders Clock time immediately')
    check('年' in date_text and '月' in date_text, 'real Chromium renders localized Japanese date')
    check(datetime_value.endswith('Z') and 'T' in datetime_value, 'real Chromium writes ISO datetime')

    page.locator('.clock-card').evaluate("el => { el.dataset.clockHourFormat='12'; el.dataset.clockShowSeconds='1'; el.dataset.clockShowDate='0'; }")
    page.wait_for_timeout(1100)
    changed_time = page.locator('.clock-time').inner_text()
    date_hidden = page.locator('.clock-date').evaluate('el => el.hidden')
    check(changed_time.count(':') >= 2, 'real Chromium updates the seconds display')
    check(date_hidden is True, 'real Chromium hides the date when configured')

    page.locator('.clock-edit-trigger').click()
    check(page.locator('.changeClockId').input_value() == '41', 'Clock edit trigger fills the owner-scoped Widget id')
    check(page.locator('.changeClockName').input_value() == 'Clock', 'Clock edit trigger fills the title')

    page.locator('#registerClockForm').evaluate('form => form.requestSubmit()')
    page.wait_for_timeout(20)
    create = page.evaluate('window.__ajaxCalls[0]')
    check(create.get('data', {}).get('action') == 'widget.clock.create', 'Clock create form uses the expected API action')
    check(create.get('data', {}).get('csrf_token') == 'csrf-v11f', 'Clock create request includes CSRF')
    check(create.get('data', {}).get('clock_title') == 'Office', 'Clock create request carries the title')

    page.locator('.changeClockName').fill('Updated')
    page.locator('.changeClockShowSeconds').check()
    page.locator('#changeClockForm').evaluate('form => form.requestSubmit()')
    page.wait_for_timeout(20)
    update = page.evaluate('window.__ajaxCalls[1]')
    check(update.get('data', {}).get('action') == 'widget.clock.update', 'Clock edit form uses the expected API action')
    check(update.get('data', {}).get('widget_id') == '41', 'Clock edit request carries the selected Widget id')

    page.locator('.delete_clock').click()
    page.wait_for_timeout(20)
    delete = page.evaluate('window.__ajaxCalls[2]')
    check(delete.get('data', {}).get('action') == 'widget.clock.delete', 'Clock delete uses the expected API action')
    check(delete.get('data', {}).get('widget_id') == '41', 'Clock delete remains scoped to the selected Widget id')

    browser.close()

if failures:
    raise SystemExit(1)
print('All V1.1-F real Browser checks passed.')
