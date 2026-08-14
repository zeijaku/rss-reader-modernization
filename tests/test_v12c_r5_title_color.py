from pathlib import Path
import shutil

from dashboard_source_utils import dashboard_source

ROOT = Path(__file__).resolve().parents[1]
INDEX = dashboard_source(ROOT)
JS = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')
failures = []
count = 0


def check(condition, message):
    global count
    count += 1
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failures.append(message)


check('<thead><tr><th colspan="3" class="content-header feed-card-header text-bg-' in INDEX,
      'Search Feed header applies the saved color directly to the Bootstrap 5 table header cell')
check('class="feed-title-text text-white"' not in INDEX,
      'Search Feed initial title no longer forces white text')
check(".addClass('feed-title-text text-white')" not in JS and ".addClass('feed-title-text')" in JS,
      'Search Feed title restored after fetch inherits the header foreground color')
check('searchTitleTextColor' not in JS and 'search-title-contrast' not in JS,
      'no Search Feed-only dynamic contrast logic was introduced')

styles = ['primary', 'secondary', 'success', 'info', 'warning', 'danger', 'dark']
theme_files = [
    'bootstrap-5.3.8.min.css',
    'bootstrap-flatly-5.3.8.min.css',
    'bootstrap-journal-5.3.8.min.css',
    'bootstrap-minty-5.3.8.min.css',
    'bootstrap-sketchy-5.3.8.min.css',
    'bootstrap-slate-5.3.8.min.css',
    'bootstrap-solar-5.3.8.min.css',
    'bootstrap-yeti-5.3.8.min.css',
]
for theme_file in theme_files:
    theme_css = (ROOT / 'public/css' / theme_file).read_text(encoding='utf-8')
    for style in styles:
        check(f'.text-bg-{style}' in theme_css,
              f'{theme_file}: Bootstrap 5 text-bg-{style} contrast utility is available')

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('SKIP: Playwright Python package is unavailable.')
    raise SystemExit(1 if failures else 0)

chromium = shutil.which('chromium') or shutil.which('chromium-browser') or shutil.which('google-chrome')
if chromium is None:
    print('SKIP: Chromium executable is unavailable.')
    raise SystemExit(1 if failures else 0)

html = '''<!doctype html><html lang="ja"><head>
<meta charset="utf-8"><meta name="csrf-token" content="''' + ('c' * 64) + '''">
</head><body>
<div id="app-notice" class="alert app-notice" hidden></div>
<main id="main-content" data-dashboard-current-tab="0" data-dashboard-tab-count="4">
<section class="dashboard-widget feed-card search-feed-card" data-dashboard-widget-id="31" data-dashboard-widget-type="search" data-search-limit="5" aria-busy="true">
<div class="feed-card-inner"><table class="table table-hover feed-table"><colgroup><col class="feed-stock-column"><col><col class="feed-summary-column"></colgroup>
<thead><tr><th colspan="3" class="content-header feed-card-header text-bg-warning"><div class="content-header-row feed-card-header-inner">
<button type="button" class="widget-drag-handle">＝</button>
<span class="content-title"><span class="feed-title-text">転職</span></span>
<span class="content-actions feed-card-actions">
<button type="button" class="search-edit-trigger" data-search-query="転職"><i class="fas fa-edit"></i></button>
<button type="button" class="search-feed-refresh"><i class="fas fa-sync-alt"></i></button>
</span>
</div></th></tr></thead><tbody class="content-body"><tr><td colspan="3">検索しています</td></tr></tbody></table></div></section>
</main>
<div id="page-top"></div>
<nav class="offcanvas offcanvas-end drawer-nav" id="drawerMenu" tabindex="-1" aria-labelledby="drawerMenuLabel"><span id="drawerMenuLabel">Menu</span></nav>
<div class="save_modal"><input class="informationData"><input class="informationTitle"><button type="button" class="information_modal_dbsave"></button></div>
</body></html>'''

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])
    page = browser.new_page(viewport={'width': 420, 'height': 800}, locale='ja-JP', timezone_id='Asia/Tokyo')
    page.set_content(html)
    page.add_style_tag(path=str(ROOT / 'public/css/bootstrap-5.3.8.min.css'))
    page.add_style_tag(path=str(ROOT / 'public/css/all.css'))
    page.add_style_tag(path=str(ROOT / 'public/css/dashboard.css'))

    initial_title = page.locator('.search-feed-card .feed-title-text')
    header = page.locator('.search-feed-card .feed-card-header')
    check('text-white' not in (initial_title.get_attribute('class') or '').split(),
          'initial Search Feed title does not force text-white')
    check(initial_title.evaluate('(el) => getComputedStyle(el).color') == header.evaluate('(el) => getComputedStyle(el).color'),
          'initial Search Feed title inherits the Bootstrap text-bg foreground color')
    check(header.evaluate('(el) => getComputedStyle(el).backgroundColor') != 'rgba(0, 0, 0, 0)',
          'Search Feed header visibly applies the selected Bootstrap background color')

    page.add_script_tag(path=str(ROOT / 'public/js/jquery-3.7.1.min.js'))
    page.add_script_tag(path=str(ROOT / 'public/js/bootstrap.bundle-5.3.8.min.js'))
    page.evaluate('''() => {
      window.__requests = [];
      jQuery.fn.popover = function(){ return this; };
      jQuery.ajax = function(options){
        const req = {options, doneFns:[], failFns:[], alwaysFns:[]};
        const chain = {
          done(fn){ req.doneFns.push(fn); return chain; },
          fail(fn){ req.failFns.push(fn); return chain; },
          always(fn){ req.alwaysFns.push(fn); return chain; }
        };
        req.resolve = function(value){ req.doneFns.forEach(fn => fn(value)); req.alwaysFns.forEach(fn => fn()); };
        req.reject = function(xhr, status){ req.failFns.forEach(fn => fn(xhr || {}, status || 'error')); req.alwaysFns.forEach(fn => fn()); };
        window.__requests.push(req);
        return chain;
      };
    }''')
    page.add_script_tag(path=str(ROOT / 'public/js/dashboard.js'))
    page.wait_for_timeout(80)

    check(page.evaluate('window.__requests.length') == 1,
          'Search Feed starts its initial fetch normally')
    page.evaluate("window.__requests[0].resolve({ok:true,data:{search_result:{limit:5,failed_count:0,items:[]}}})")
    page.wait_for_timeout(40)

    restored_title = page.locator('.search-feed-card .feed-title-text')
    check(restored_title.inner_text() == '転職',
          'Search Feed title is restored from the saved query after fetch')
    check('text-white' not in (restored_title.get_attribute('class') or '').split(),
          'restored Search Feed title continues to inherit automatic contrast')
    check(restored_title.evaluate('(el) => getComputedStyle(el).color') == header.evaluate('(el) => getComputedStyle(el).color'),
          'restored Search Feed title keeps the Bootstrap text-bg foreground color')

    page.locator('.search-feed-refresh').click()
    page.wait_for_timeout(20)
    check(page.evaluate('window.__requests.length') == 2,
          'individual Search Feed refresh still starts normally')
    page.evaluate("window.__requests[1].resolve({ok:true,data:{search_result:{limit:5,failed_count:0,items:[]}}})")
    page.wait_for_timeout(40)
    refreshed_title = page.locator('.search-feed-card .feed-title-text')
    check(refreshed_title.evaluate('(el) => getComputedStyle(el).color') == header.evaluate('(el) => getComputedStyle(el).color'),
          'title keeps automatic contrast after individual refresh')

    browser.close()

if failures:
    raise SystemExit(1)
print(f'All {count} V1.2-C R5 title-color checks passed.')
