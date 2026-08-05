from pathlib import Path
import shutil

ROOT = Path(__file__).resolve().parents[1]
JS = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')
failures = []
count = 0

def check(condition, message):
    global count
    count += 1
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failures.append(message)

check("showNotice('Stockへ保存しました', 'success', 2500)" in JS,
      'Stock success notice has a 2500ms auto-close')
check("$button.closest('[data-feed-content-id], .search-feed-card')" in JS,
      'summary lookup supports both normal Feed and Search Feed cards')
check("showNotice('RSS概要を確認出来ませんでした', 'danger', 4000)" in JS,
      'summary lookup error notice has a 4000ms auto-close')
check(".prop('disabled', summary === '')" in JS,
      'empty summary keeps the existing disabled-button behavior')

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
<meta charset="utf-8"><meta name="csrf-token" content="''' + ('b' * 64) + '''">
</head><body class="drawer">
<div id="app-notice" class="alert app-notice" hidden></div>
<div id="articleActionsMenu" class="article-actions-menu" role="menu" aria-label="記事Actions" hidden>
<button type="button" class="article-actions-item article-action-stock" role="menuitem"><i class="far fa-bookmark fa-fw"></i><span>Stockへ保存</span></button>
<button type="button" class="article-actions-item article-action-copy" role="menuitem"><i class="far fa-copy fa-fw"></i><span>URLをコピー</span></button>
<button type="button" class="article-actions-item article-action-x" role="menuitem"><i class="fab fa-x-twitter fa-fw"></i><span>Xへ投稿</span></button>
<button type="button" class="article-actions-item article-action-task" role="menuitem"><i class="fas fa-tasks fa-fw"></i><span>Taskへ追加</span></button>
</div>
<main id="main-content" data-dashboard-current-tab="0" data-dashboard-tab-count="4">
<section class="dashboard-widget feed-card search-feed-card" data-dashboard-widget-id="21" data-dashboard-widget-type="search" data-search-limit="5" aria-busy="true">
<div class="feed-card-inner"><table class="table table-hover feed-table"><colgroup><col class="feed-stock-column"><col><col class="feed-summary-column"></colgroup>
<thead><tr><th colspan="3" class="content-header feed-card-header"><div class="content-header-row feed-card-header-inner">
<span class="content-title"><span class="feed-title-text">PHP</span></span>
<button type="button" class="search-feed-refresh"><i class="fas fa-sync-alt"></i></button>
</div></th></tr></thead><tbody class="content-body"><tr><td colspan="3">検索しています</td></tr></tbody></table></div></section>
</main>
<div id="page-top"></div><nav id="drawerMenu"></nav>
<div class="save_modal"><input class="informationData"><input class="informationTitle"><button type="button" class="information_modal_dbsave"></button></div>
</body></html>'''

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])
    page = browser.new_page(viewport={'width': 420, 'height': 800}, locale='ja-JP', timezone_id='Asia/Tokyo')
    page.set_content(html)
    page.add_style_tag(path=str(ROOT / 'public/css/all.css'))
    page.add_style_tag(path=str(ROOT / 'public/css/dashboard.css'))
    page.add_script_tag(path=str(ROOT / 'public/js/jquery-3.7.1.min.js'))
    page.evaluate('''() => {
      const nativeSetTimeout = window.setTimeout.bind(window);
      window.__timeoutDelays = [];
      window.setTimeout = function(fn, delay, ...args) {
        const numeric = Number(delay || 0);
        window.__timeoutDelays.push(numeric);
        return nativeSetTimeout(fn, numeric >= 2000 ? 25 : numeric, ...args);
      };
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
          'Search Feed starts one initial widget.search.fetch request')
    check(page.evaluate("window.__requests[0].options.data.action") == 'widget.search.fetch',
          'Search Feed initial request uses the expected API action')

    payload = {
        'ok': True,
        'data': {'search_result': {
            'limit': 5,
            'failed_count': 0,
            'items': [
                {'title': '概要あり', 'link': 'https://example.com/with-summary', 'content': 'Search Feedの概要本文', 'description': ''},
                {'title': '概要なし', 'link': 'https://example.com/without-summary', 'content': '', 'description': ''},
            ]
        }}
    }
    page.evaluate('(value) => window.__requests[0].resolve(value)', payload)
    page.wait_for_timeout(60)

    toggles = page.locator('.feed-item-summary-toggle')
    check(toggles.count() == 2 and not toggles.nth(0).is_disabled(),
          'Search Feed renders an enabled summary button when content exists')
    check(toggles.nth(1).is_disabled(),
          'Search Feed keeps the plus button disabled when summary is empty')

    toggles.nth(0).click()
    page.wait_for_timeout(20)
    check(page.locator('.feed-item-detail-row').count() == 1,
          'enabled Search Feed plus button expands the RSS summary')
    check('Search Feedの概要本文' in page.locator('.feed-item-summary').inner_text(),
          'Search Feed summary uses the rendered item data')
    check(page.locator('#app-notice').is_hidden(),
          'successful Search Feed summary expansion does not show an error notice')

    toggles.nth(0).click()
    page.evaluate("jQuery('.search-feed-card').removeData('feed-render-items')")
    toggles.nth(0).click()
    check('RSS概要を確認出来ませんでした' in page.locator('#app-notice').inner_text(),
          'genuine summary lookup failure still gives controlled feedback')
    check(page.evaluate('window.__timeoutDelays.includes(4000)'),
          'summary lookup failure schedules the 4000ms auto-close')
    page.wait_for_timeout(60)
    check(page.locator('#app-notice').is_hidden(),
          'summary lookup failure notice closes automatically')

    page.locator('.article-actions-trigger').first.click()
    page.locator('.article-action-stock').click()
    save = page.locator('.information_modal_dbsave')
    save.click()
    check(page.evaluate('window.__requests.length') == 2 and
          page.evaluate("window.__requests[1].options.data.action") == 'stock.create',
          'Stock save still uses stock.create')
    page.evaluate("window.__requests[1].resolve({ok:true})")
    check('Stockへ保存しました' in page.locator('#app-notice').inner_text(),
          'Stock success notice is displayed')
    check(page.evaluate('window.__timeoutDelays.includes(2500)'),
          'Stock success schedules the 2500ms auto-close')
    page.wait_for_timeout(60)
    check(page.locator('#app-notice').is_hidden(),
          'Stock success notice closes automatically')

    browser.close()

if failures:
    raise SystemExit(1)
print(f'All {count} V1.2-C R4 small-fix checks passed.')
