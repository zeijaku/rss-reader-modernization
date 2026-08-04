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

long_title = 'とても長いRSS記事タイトルです。' * 12
html = '''<!doctype html><html lang="ja"><head>
<meta charset="utf-8"><meta name="csrf-token" content="''' + ('a' * 64) + '''">
</head><body class="drawer">
<div id="app-notice" class="alert app-notice" hidden></div>
<main id="main-content" data-dashboard-current-tab="0" data-dashboard-tab-count="4">
<section style="width:360px" class="dashboard-widget feed-card" data-dashboard-widget-id="11" data-dashboard-widget-type="feed" data-feed-content-id="7" data-feed-state="loading" aria-busy="true">
<div class="feed-card-inner"><input type="hidden" class="content-value" value="https://example.com/feed.xml">
<table class="table table-hover feed-table"><colgroup><col><col class="feed-actions-column"></colgroup>
<thead><tr><th colspan="2" class="bg-success feed-card-header"><div class="feed-card-header-inner">
<button type="button" class="btn btn-link widget-drag-handle"><i class="fas fa-grip-lines"></i></button>
<small class="content-title widget-title-text"><span>読み込み中...</span></small>
<span class="feed-card-actions"><button type="button" class="btn btn-link content-edit-trigger"><i class="fas fa-edit"></i></button><button type="button" class="btn btn-link feed-refresh-trigger"><i class="fas fa-sync-alt"></i></button></span>
</div></th></tr></thead>
<tbody class="content-body"><tr class="content-state-row feed-state-loading"><td colspan="2">フィードを読み込んでいます</td></tr></tbody></table></div></section>
<section id="other-widget">Clock unchanged</section>
</main><div id="page-top"></div><nav id="drawerMenu"></nav>
<div class="save_modal"><input class="informationData"><input class="informationTitle"><button class="information_modal_dbsave"></button></div>
</body></html>'''

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])
    page = browser.new_page(viewport={"width": 420, "height": 900}, locale='ja-JP', timezone_id='Asia/Tokyo')
    page.set_content(html)
    page.add_style_tag(path=str(ROOT / 'public/css/dashboard.css'))
    page.add_script_tag(path=str(ROOT / 'public/js/jquery-3.7.1.min.js'))
    page.evaluate('''() => {
      window.__requests=[];
      window.__xss=false;
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

    check(page.evaluate('window.__requests.length') == 1, 'initial Feed card starts one request')
    req = page.evaluate('window.__requests[0].options')
    check(req['data']['action'] == 'feed.fetch' and req['data']['content_id'] == '7', 'initial request uses existing feed.fetch and owned content_id')
    check(req['data']['csrf_token'] == 'a' * 64, 'initial request keeps CSRF protection')
    check(page.locator('.feed-refresh-trigger').is_disabled(), 'refresh button is disabled while initial request is pending')
    check(page.locator('.feed-refresh-trigger i').evaluate("el=>el.classList.contains('fa-spin')"), 'refresh icon rotates while pending')

    payload = {
        'ok': True,
        'data': {'result_feed': {
            'channel': {'title': '技術Feed', 'link': 'https://example.com/feed'},
            'new_count': 2,
            'item': [
                {'title': long_title, 'link': 'https://example.com/long', 'content': '<script>window.__xss=true</script>\n本文を表示します', 'description': '説明は使わない', 'item_identity': 'm1i:v1:' + ('a' * 64), 'is_new': True},
                {'title': '短い記事', 'link': 'https://example.com/short', 'content': '', 'description': 'descriptionだけの概要'},
                {'title': '概要なし', 'link': 'https://example.com/empty', 'content': '', 'description': ''},
                {'title': 'URLなし', 'link': '', 'content': 'URLがない記事の概要', 'description': ''},
            ]
        }}
    }
    page.evaluate('(value)=>window.__requests[0].resolve(value)', payload)
    page.wait_for_timeout(160)

    check(page.locator('.feed-item-row').count() == 4, 'Feed renders four article rows')
    check(not page.locator('.feed-refresh-trigger').is_disabled(), 'refresh button is restored after initial success')
    check(not page.locator('.feed-refresh-trigger i').evaluate("el=>el.classList.contains('fa-spin')"), 'refresh icon stops after success')
    check(page.locator('.feed-item-title-text').first.inner_text() == long_title, 'full article title remains in the DOM')
    overflow = page.locator('.feed-item-title-text').first.evaluate('el=>({scroll:el.scrollWidth,client:el.clientWidth,mark:el.dataset.feedTitleTruncated,white:getComputedStyle(el).whiteSpace})')
    check(overflow['scroll'] > overflow['client'] and overflow['mark'] == '1', 'only an actually overflowing title is marked truncated')
    check(overflow['white'] == 'nowrap', 'visual truncation uses a single-line CSS ellipsis')
    check(page.locator('.feed-item-title-text').nth(1).get_attribute('data-feed-title-truncated') == '0', 'short title is not marked truncated')

    page.locator('.feed-item-title-text').first.hover()
    page.wait_for_timeout(300)
    check(page.locator('#feed-title-tooltip').is_visible(), 'Hover shows the delayed full-title tooltip for a truncated title')
    check(page.locator('#feed-title-tooltip').inner_text() == long_title, 'tooltip contains the complete title as text')
    page.locator('.feed-item-title-text').nth(1).hover()
    page.wait_for_timeout(300)
    check(not page.locator('#feed-title-tooltip').is_visible(), 'Hover does not show a tooltip for a non-truncated title')
    page.locator('.feed-item-title-text').first.focus()
    page.wait_for_timeout(300)
    check(page.locator('#feed-title-tooltip').is_visible(), 'keyboard Focus also shows the full-title tooltip')
    check(page.locator('.feed-item-title-text').first.get_attribute('aria-describedby') == 'feed-title-tooltip', 'visible tooltip is associated for accessibility')

    first_toggle = page.locator('.feed-item-summary-toggle').first
    check(page.locator('.feed-item-detail-row').count() == 0, 'summary DOM is not generated during initial rendering')
    first_toggle.click()
    page.wait_for_timeout(30)
    check(first_toggle.get_attribute('aria-expanded') == 'true', 'summary toggle exposes expanded state')
    check(page.locator('.feed-item-detail-row').count() == 1, 'summary row is generated only when expanded')
    summary_text = page.locator('.feed-item-summary').inner_text()
    check('<script>window.__xss=true</script>' in summary_text and '説明は使わない' not in summary_text, 'content is preferred over description and remains literal text')
    check(page.evaluate('window.__xss') is False, 'RSS content cannot execute script')
    check(page.locator('.feed-item-summary script, .feed-item-summary iframe, .feed-item-summary img, .feed-item-summary video').count() == 0, 'summary creates no active media or HTML elements')
    check(page.locator('.feed-item-summary-link').get_attribute('href') == 'https://example.com/long', 'expanded summary keeps a route to the original article')
    first_toggle.click()
    check(page.locator('.feed-item-detail-row').count() == 0 and first_toggle.get_attribute('aria-expanded') == 'false', 'second click closes and removes the generated summary row')

    page.locator('.feed-item-summary-toggle').nth(1).click()
    check('descriptionだけの概要' in page.locator('.feed-item-summary').inner_text(), 'description is used when content is empty')
    page.locator('.feed-item-summary-toggle').nth(1).click()
    check(page.locator('.feed-item-summary-toggle').nth(2).is_disabled(), 'summary button is disabled when both content and description are empty')
    check(page.locator('.feed-item-title-text').nth(3).get_attribute('tabindex') == '0', 'non-link article title remains keyboard focusable')

    stock = page.locator('.infomation_modal_rewrite').first
    check(stock.get_attribute('data-stock-url') == 'https://example.com/long', 'existing Stock action keeps the validated article URL')
    check(stock.get_attribute('data-stock-title') == long_title, 'existing Stock action keeps the full article title')

    before_rows = page.locator('.content-body').inner_text()
    other_before = page.locator('#other-widget').inner_text()
    page.locator('.feed-refresh-trigger').click()
    page.wait_for_timeout(30)
    check(page.evaluate('window.__requests.length') == 2, 'individual refresh starts one additional feed.fetch request')
    check(page.locator('.content-body').inner_text() == before_rows, 'individual refresh keeps current articles visible while loading')
    check(page.locator('.feed-refresh-trigger').is_disabled(), 'individual refresh prevents repeated clicks')
    check(page.locator('.feed-refresh-trigger i').evaluate("el=>el.classList.contains('fa-spin')"), 'individual refresh rotates the refresh icon')
    page.locator('.feed-refresh-trigger').evaluate('el=>el.click()')
    check(page.evaluate('window.__requests.length') == 2, 'pending guard blocks a duplicate refresh request')
    page.evaluate("window.__requests[1].reject({status:502,responseJSON:{error:{message:'upstream'}}},'error')")
    page.wait_for_timeout(30)
    check(page.locator('.content-body').inner_text() == before_rows, 'failed refresh leaves the previous articles intact')
    check('RSSを更新出来ませんでした' in page.locator('#app-notice').inner_text(), 'failed refresh gives a controlled notification')
    check(not page.locator('.feed-refresh-trigger').is_disabled(), 'refresh button is restored after failure')

    page.locator('.feed-refresh-trigger').click()
    page.wait_for_timeout(20)
    check(page.evaluate('window.__requests.length') == 3, 'refresh can be retried after failure')
    refreshed = {'ok': True, 'data': {'result_feed': {'channel': {'title': '技術Feed更新', 'link': 'https://example.com/feed'}, 'new_count': 1, 'item': [{'title': '更新後の記事', 'link': 'https://example.com/new', 'content': '新しい概要', 'description': ''}]}}}
    page.evaluate('(value)=>window.__requests[2].resolve(value)', refreshed)
    page.wait_for_timeout(80)
    check(page.locator('.feed-item-row').count() == 1 and page.locator('.feed-item-title-text').inner_text() == '更新後の記事', 'successful refresh replaces only the target card contents')
    check(page.locator('#other-widget').inner_text() == other_before, 'individual refresh does not alter other Widgets')
    check(page.locator('#app-notice').inner_text() == 'RSSを更新しました', 'successful refresh displays a short completion notice')
    check(page.locator('.feed-new-count').inner_text() == '1', 'NEW count is refreshed with the target Feed')
    browser.close()

if failures:
    raise SystemExit(1)
print(f'All {len(failures) + 43} V1.2-B real Browser checks passed.')
