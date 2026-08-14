from pathlib import Path
import re
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


check('class="feed-title-text text-white" title="' in INDEX,
      'Search Feed initial title uses the same fixed white text class as existing cards')
check(".addClass('feed-title-text text-white')" in JS,
      'Search Feed title restored after fetch keeps the fixed white text class')
check('searchTitleTextColor' not in JS and 'search-title-contrast' not in JS,
      'no Search Feed-only dynamic contrast logic was introduced')

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
</head><body class="drawer">
<div id="app-notice" class="alert app-notice" hidden></div>
<main id="main-content" data-dashboard-current-tab="0" data-dashboard-tab-count="4">
<section class="dashboard-widget feed-card search-feed-card" data-dashboard-widget-id="31" data-dashboard-widget-type="search" data-search-limit="5" aria-busy="true">
<div class="feed-card-inner"><table class="table table-hover feed-table"><colgroup><col class="feed-stock-column"><col><col class="feed-summary-column"></colgroup>
<thead><tr class="bg-dark"><th colspan="3" class="content-header feed-card-header"><div class="content-header-row feed-card-header-inner">
<button type="button" class="widget-drag-handle">＝</button>
<span class="content-title"><span class="feed-title-text text-white">転職</span></span>
<span class="content-actions feed-card-actions">
<button type="button" class="search-edit-trigger" data-search-query="転職"><i class="fas fa-edit text-white"></i></button>
<button type="button" class="search-feed-refresh"><i class="fas fa-sync-alt text-white"></i></button>
</span>
</div></th></tr></thead><tbody class="content-body"><tr><td colspan="3">検索しています</td></tr></tbody></table></div></section>
</main>
<div id="page-top"></div><nav id="drawerMenu"></nav>
<div class="save_modal"><input class="informationData"><input class="informationTitle"><button type="button" class="information_modal_dbsave"></button></div>
</body></html>'''

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])
    page = browser.new_page(viewport={'width': 420, 'height': 800}, locale='ja-JP', timezone_id='Asia/Tokyo')
    page.set_content(html)
    page.add_style_tag(path=str(ROOT / 'public/css/bootstrap.min.css'))
    page.add_style_tag(path=str(ROOT / 'public/css/all.css'))
    page.add_style_tag(path=str(ROOT / 'public/css/dashboard.css'))

    initial_title = page.locator('.search-feed-card .feed-title-text')
    check('text-white' in (initial_title.get_attribute('class') or '').split(),
          'initial Search Feed title contains text-white')
    check(initial_title.evaluate('(el) => getComputedStyle(el).color') == 'rgb(255, 255, 255)',
          'initial Search Feed title is visibly white on a dark header')

    page.add_script_tag(path=str(ROOT / 'public/js/jquery-3.7.1.min.js'))
    page.evaluate('''() => {
      window.__requests = [];
      jQuery.fn.popover = function(){ return this; };
      jQuery.fn.drawer = function(){ return this; };
      jQuery.fn.modal = function(){ return this; };
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
    check('text-white' in (restored_title.get_attribute('class') or '').split(),
          'restored Search Feed title keeps text-white')
    check(restored_title.evaluate('(el) => getComputedStyle(el).color') == 'rgb(255, 255, 255)',
          'restored Search Feed title remains visibly white')

    page.locator('.search-feed-refresh').click()
    page.wait_for_timeout(20)
    check(page.evaluate('window.__requests.length') == 2,
          'individual Search Feed refresh still starts normally')
    page.evaluate("window.__requests[1].resolve({ok:true,data:{search_result:{limit:5,failed_count:0,items:[]}}})")
    page.wait_for_timeout(40)
    refreshed_title = page.locator('.search-feed-card .feed-title-text')
    check('text-white' in (refreshed_title.get_attribute('class') or '').split(),
          'title remains white after individual refresh')
    page.close()

    theme_files = [
        'bootstrap.min.css',
        'bootstrap-flatly.min.css',
        'bootstrap-journal.min.css',
        'bootstrap-minty.min.css',
        'bootstrap-sketchy.min.css',
        'bootstrap-slate.min.css',
        'bootstrap-solar.min.css',
        'bootstrap-yeti.min.css',
    ]
    for theme_file in theme_files:
        theme_css = (ROOT / 'public/css' / theme_file).read_text(encoding='utf-8')
        check(re.search(r'\.text-white\s*\{[^}]*color\s*:\s*#fff(?:fff)?\s*!important', theme_css, re.I) is not None,
              f'{theme_file}: bundled theme keeps text-white fixed to white')

    browser.close()

if failures:
    raise SystemExit(1)
print(f'All {count} V1.2-C R5 title-color checks passed.')
