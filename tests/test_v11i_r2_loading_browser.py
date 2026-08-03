from pathlib import Path
import shutil

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

failures = []
def check(cond, msg):
    print(('PASS' if cond else 'FAIL') + ': ' + msg)
    if not cond:
        failures.append(msg)

html = '''<!doctype html><html lang="ja"><head><meta name="csrf-token" content="csrf-r2"></head>
<body class="drawer drawer--right"><div id="app-notice" hidden></div>
<main id="main-content" data-dashboard-current-tab="" data-dashboard-tab-count="4">
<section class="dashboard-widget feed-card" data-feed-content-id="10" data-feed-state="loading" aria-busy="true">
<span class="content-title"></span><table><tbody class="content-body"></tbody></table></section>
<section class="dashboard-widget calendar-card" data-dashboard-widget-id="20" data-dashboard-widget-type="calendar">
<div class="calendar-month-label"></div><div class="calendar-days" aria-busy="true"></div></section>
</main><div id="page-top"></div><nav id="drawerMenu"></nav></body></html>'''

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])
    page = browser.new_page(locale='ja-JP', timezone_id='Asia/Tokyo')
    page.set_content(html)
    page.add_script_tag(path=str(ROOT / 'public/js/jquery-3.7.1.min.js'))
    page.evaluate('''() => {
      window.__pendingRequests = [];
      jQuery.fn.popover = function(){ return this; };
      jQuery.fn.drawer = function(){ return this; };
      jQuery.fn.modal = function(){ return this; };
      jQuery.ajax = function(options){
        const request = {options, doneFns: [], failFns: [], alwaysFns: []};
        const chain = {
          done(fn){ request.doneFns.push(fn); return chain; },
          fail(fn){ request.failFns.push(fn); return chain; },
          always(fn){ request.alwaysFns.push(fn); return chain; }
        };
        request.reject = function(xhr, status){ request.failFns.forEach(fn => fn(xhr, status)); request.alwaysFns.forEach(fn => fn()); };
        request.resolve = function(value){ request.doneFns.forEach(fn => fn(value)); request.alwaysFns.forEach(fn => fn()); };
        window.__pendingRequests.push(request);
        return chain;
      };
    }''')
    page.add_script_tag(path=str(ROOT / 'public/js/dashboard.js'))
    page.add_script_tag(path=str(ROOT / 'public/js/calendar.js'))
    page.wait_for_timeout(100)

    check(page.locator('.feed-card .content-title .loading-inline .fa-spinner.fa-spin').count() == 1, 'Feed title shows one spinning loading icon')
    check(page.locator('.feed-card .feed-state-message .loading-inline .fa-spinner.fa-spin').count() == 1, 'Feed body shows one spinning loading icon')
    check(page.locator('.feed-card .content-title').inner_text().strip() == '読み込み中...', 'Feed loading text remains visible')
    check(page.locator('.feed-card .feed-state-message').inner_text().strip() == 'フィードを読み込んでいます', 'Feed body loading text remains visible')
    check(page.locator('.calendar-card .calendar-loading .loading-inline .fa-spinner.fa-spin').count() == 1, 'Calendar shows one spinning loading icon')
    check(page.locator('.calendar-card .calendar-loading').inner_text().strip() == 'Calendarを読み込んでいます', 'Calendar loading text remains visible')
    check(page.locator('.feed-card').get_attribute('aria-busy') == 'true', 'Feed remains aria-busy while its request is pending')
    check(page.locator('.calendar-days').get_attribute('aria-busy') == 'true', 'Calendar remains aria-busy while its request is pending')

    page.evaluate('''() => {
      const feed = window.__pendingRequests.find(r => r.options.data.action === 'feed.fetch');
      const calendar = window.__pendingRequests.find(r => r.options.data.action === 'calendar.month.list');
      feed.reject({status: 500}, 'error');
      calendar.reject({status: 500}, 'error');
    }''')
    page.wait_for_timeout(30)
    check(page.locator('.feed-card .fa-spinner').count() == 0, 'Feed spinner is removed after failure')
    check(page.locator('.feed-card').get_attribute('aria-busy') == 'false', 'Feed clears aria-busy after failure')
    check(page.locator('.feed-card .feed-state-message').get_attribute('role') == 'alert', 'Feed failure changes to an alert')
    check(page.locator('.calendar-card .fa-spinner').count() == 0, 'Calendar spinner is removed after failure')
    check(page.locator('.calendar-days').get_attribute('aria-busy') == 'false', 'Calendar clears aria-busy after failure')
    check(page.locator('.calendar-error').get_attribute('role') == 'alert', 'Calendar failure changes to an alert')
    browser.close()

if failures:
    raise SystemExit(1)
print('All V1.1-I / R2 real Browser loading checks passed.')
