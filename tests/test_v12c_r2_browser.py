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

def check(condition, message):
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failures.append(message)

card = '''<section class="dashboard-widget feed-card search-feed-card" data-dashboard-widget-id="{id}" data-dashboard-widget-type="search" data-search-limit="10" aria-busy="true">
<div class="feed-card-inner"><table class="table table-hover feed-table"><colgroup><col class="feed-stock-column"><col><col class="feed-summary-column"></colgroup>
<thead><tr class="bg-info"><th colspan="3" class="content-header"><div class="content-header-row">
<button type="button" class="widget-drag-handle">＝</button>
<span class="content-title"><span class="feed-title-text">{query}</span></span>
<span class="content-actions"><button type="button" class="search-edit-trigger" data-search-query="{query}"></button><button type="button" class="search-feed-refresh"><i class="fas fa-sync-alt"></i></button></span>
</div></th></tr></thead><tbody class="content-body"><tr><td colspan="3">検索しています</td></tr></tbody></table></div></section>'''

html = '''<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="csrf-token" content="''' + ('a' * 64) + '''"></head><body class="drawer">
<div id="app-notice" hidden></div><main id="main-content">''' + card.format(id=21, query='PHP 広島') + card.format(id=22, query='セキュリティ') + '''</main>
<div id="page-top"></div><nav id="drawerMenu"></nav><div class="save_modal"><input class="informationData"><input class="informationTitle"></div></body></html>'''

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])
    page = browser.new_page(viewport={"width": 420, "height": 900}, locale='ja-JP', timezone_id='Asia/Tokyo')
    page.set_content(html)
    page.add_style_tag(path=str(ROOT / 'public/css/all.css'))
    page.add_style_tag(path=str(ROOT / 'public/css/dashboard.css'))
    page.add_script_tag(path=str(ROOT / 'public/js/jquery-3.7.1.min.js'))
    page.evaluate('''() => {
      window.__requests=[];
      jQuery.fn.popover=function(){return this;};
      jQuery.fn.drawer=function(){return this;};
      jQuery.fn.modal=function(){return this;};
      jQuery.ajax=function(options){
        const req={options,doneFns:[],failFns:[],alwaysFns:[]};
        const chain={done(fn){req.doneFns.push(fn);return chain;},fail(fn){req.failFns.push(fn);return chain;},always(fn){req.alwaysFns.push(fn);return chain;}};
        req.resolve=function(value){req.doneFns.forEach(fn=>fn(value));req.alwaysFns.forEach(fn=>fn());};
        req.reject=function(xhr,status){req.failFns.forEach(fn=>fn(xhr||{},status||'error'));req.alwaysFns.forEach(fn=>fn());};
        window.__requests.push(req); return chain;
      };
    }''')
    page.add_script_tag(path=str(ROOT / 'public/js/dashboard.js'))
    page.wait_for_timeout(100)

    cards = page.locator('.search-feed-card')
    check(page.evaluate('window.__requests.length') == 2, 'two Search Feed cards start two owned widget requests')
    check(cards.nth(0).locator('.content-title').inner_text().strip() == '読み込み中...', 'initial Search Feed title shows loading state')
    check(cards.nth(1).locator('.content-title').inner_text().strip() == '読み込み中...', 'second Search Feed title also shows loading state')

    success = {'ok': True, 'data': {'search_result': {'limit': 10, 'failed_count': 0, 'items': [
        {'title': 'PHP勉強会', 'link': 'https://example.com/php', 'content': '', 'description': '概要'}
    ]}}}
    empty = {'ok': True, 'data': {'search_result': {'limit': 10, 'failed_count': 0, 'items': []}}}
    page.evaluate('(value)=>window.__requests[0].resolve(value)', success)
    page.evaluate('(value)=>window.__requests[1].resolve(value)', empty)
    page.wait_for_timeout(120)

    check(cards.nth(0).locator('.content-title').inner_text().strip() == 'PHP 広島', 'successful Search Feed restores its search query in the header')
    check(cards.nth(0).locator('.feed-item-row').count() == 1, 'successful Search Feed still renders result articles')
    check(cards.nth(1).locator('.content-title').inner_text().strip() == 'セキュリティ', 'zero-result Search Feed also restores its search query')
    check('一致する記事はありません' in cards.nth(1).locator('.content-body').inner_text(), 'zero-result body keeps the existing empty-state message')
    check('読み込み中' not in page.locator('.search-feed-card .content-title').all_inner_texts(), 'no completed Search Feed header remains in loading state')
    check(not cards.nth(0).locator('.search-feed-refresh').is_disabled() and not cards.nth(1).locator('.search-feed-refresh').is_disabled(), 'refresh controls are restored after completion')
    browser.close()

if failures:
    raise SystemExit(1)
print(f'All {9} V1.2-C R2 Browser checks passed.')
