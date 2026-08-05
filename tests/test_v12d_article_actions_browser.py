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
count = 0

def check(condition, message):
    global count
    count += 1
    ok = bool(condition)
    print(('PASS' if ok else 'FAIL') + ': ' + message)
    if not ok:
        failures.append(message)

menu = '''
<div id="articleActionsMenu" class="article-actions-menu" role="menu" aria-label="記事Actions" hidden>
<button type="button" class="article-actions-item article-action-stock" role="menuitem"><i class="far fa-bookmark fa-fw"></i><span>Stockへ保存</span></button>
<button type="button" class="article-actions-item article-action-copy" role="menuitem"><i class="far fa-copy fa-fw"></i><span>URLをコピー</span></button>
<button type="button" class="article-actions-item article-action-x" role="menuitem"><i class="fab fa-twitter fa-fw"></i><span>Xへ投稿</span></button>
<button type="button" class="article-actions-item article-action-task" role="menuitem"><i class="fas fa-tasks fa-fw"></i><span>Taskへ追加</span></button>
</div>'''
normal = '''
<section class="dashboard-widget feed-card" style="width:400px" data-dashboard-widget-id="11" data-feed-content-id="5" data-feed-state="loading" aria-busy="true">
<div class="feed-card-inner"><table class="feed-table"><colgroup><col class="feed-stock-column"><col><col class="feed-summary-column"></colgroup>
<thead><tr><th colspan="3" class="feed-card-header"><div class="feed-card-header-inner"><span class="content-title"></span><button type="button" class="feed-refresh-trigger"><i></i></button></div></th></tr></thead>
<tbody class="content-body"></tbody></table></div></section>'''
search = '''
<section class="dashboard-widget feed-card search-feed-card" style="width:400px" data-dashboard-widget-id="22" data-dashboard-widget-type="search" data-search-limit="10" aria-busy="true">
<div class="feed-card-inner"><table class="feed-table"><colgroup><col class="feed-stock-column"><col><col class="feed-summary-column"></colgroup>
<thead><tr><th colspan="3" class="content-header feed-card-header"><div class="content-header-row feed-card-header-inner"><span class="content-title"></span><span class="content-actions"><button class="search-edit-trigger" data-search-query="検索結果"></button><button type="button" class="search-feed-refresh"><i></i></button></span></div></th></tr></thead>
<tbody class="content-body"></tbody></table></div></section>'''
html = f'''<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="csrf-token" content="{'a'*64}"></head><body>
<div id="app-notice" class="alert" hidden></div>{menu}
<main id="main-content" data-dashboard-current-tab="0" data-dashboard-tab-count="4"><div class="content-grid" data-dashboard-widget-location="0">{normal}{search}</div></main>
<div class="modal save_modal" id="saveContent"><button class="information_modal_dbsave"></button></div>
<div id="page-top"></div><nav id="drawerMenu"></nav></body></html>'''

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])
    page = browser.new_page(viewport={"width": 430, "height": 900}, locale='ja-JP', timezone_id='Asia/Tokyo')
    page.set_content(html)
    page.add_style_tag(path=str(ROOT / 'public/css/all.css'))
    page.add_style_tag(path=str(ROOT / 'public/css/dashboard.css'))
    page.add_script_tag(path=str(ROOT / 'public/js/jquery-3.7.1.min.js'))
    page.evaluate('''() => {
      window.__requests=[];
      window.__popup=null;
      window.__popupOpened=false;
      document.execCommand=function(command){ window.__execCommand=command; return command==='copy'; };
      jQuery.fn.popover=function(){return this;};
      jQuery.fn.drawer=function(){return this;};
      jQuery.fn.modal=function(action){
        this.attr('data-modal-action', String(action||''));
        if(action==='show'){this.trigger('show.bs.modal');this.addClass('show');}
        if(action==='hide'){this.removeClass('show');this.trigger('hidden.bs.modal');}
        return this;
      };
      jQuery.ajax=function(options){
        const req={options,doneFns:[],failFns:[],alwaysFns:[]};
        const chain={done(fn){req.doneFns.push(fn);return chain;},fail(fn){req.failFns.push(fn);return chain;},always(fn){req.alwaysFns.push(fn);return chain;}};
        req.resolve=function(value){req.doneFns.forEach(fn=>fn(value));req.alwaysFns.forEach(fn=>fn());};
        req.reject=function(xhr,status){req.failFns.forEach(fn=>fn(xhr||{},status||'error'));req.alwaysFns.forEach(fn=>fn());};
        window.__requests.push(req); return chain;
      };
    }''')
    page.add_script_tag(path=str(ROOT / 'public/js/dashboard.js'))
    page.wait_for_timeout(120)

    requests = page.evaluate('window.__requests.map(r=>r.options.data.action)')
    check(requests.count('feed.fetch') == 1 and requests.count('widget.search.fetch') == 1,
          'normal and Search Feed each start one request')

    normal_result = {'ok': True, 'data': {'result_feed': {'channel': {'title': '通常RSS', 'link': 'https://example.com/'}, 'new_count': 0, 'item': [
        {'title': '通常記事', 'link': 'https://example.com/normal?x=1&y=2', 'description': '通常概要', 'content': ''}
    ]}}}
    long_title = '長' * 260
    search_result = {'ok': True, 'data': {'search_result': {'limit': 10, 'failed_count': 0, 'items': [
        {'title': long_title, 'link': 'https://example.com/search?q=php&sort=new', 'description': '検索概要', 'content': ''}
    ]}}}
    page.evaluate('''([normalValue, searchValue]) => {
      const normalReq=window.__requests.find(r=>r.options.data.action==='feed.fetch');
      const searchReq=window.__requests.find(r=>r.options.data.action==='widget.search.fetch');
      normalReq.resolve(normalValue); searchReq.resolve(searchValue);
    }''', [normal_result, search_result])
    page.wait_for_timeout(120)

    triggers = page.locator('.article-actions-trigger')
    check(triggers.count() == 2, 'normal and Search Feed both render one shared Article Actions trigger')
    check(page.locator('.article-actions-trigger .fa-ellipsis-h').count() == 2, 'both article triggers use ellipsis icons')
    check(page.locator('.feed-item-summary-toggle .fa-plus-square').count() == 2, 'RSS summary plus buttons remain in place')
    check(page.locator('.feed-item-row .fa-bookmark').count() == 0, 'article rows no longer show direct Bookmark icons')

    triggers.nth(0).click()
    menu_locator = page.locator('#articleActionsMenu')
    check(not menu_locator.is_hidden() and menu_locator.locator('.article-actions-item').count() == 4,
          'ellipsis opens the four-item shared menu')
    check(triggers.nth(0).get_attribute('aria-expanded') == 'true', 'opened trigger exposes aria-expanded=true')
    bounds = page.evaluate('''() => {
      const m=document.querySelector('#articleActionsMenu').getBoundingClientRect();
      const c=document.querySelector('.feed-card').getBoundingClientRect();
      return {inside:m.left>=c.left-1 && m.right<=c.right+1 && m.top>=c.top-1 && m.bottom<=c.bottom+1,menu:{left:m.left,right:m.right,top:m.top,bottom:m.bottom,width:m.width,height:m.height},card:{left:c.left,right:c.right,top:c.top,bottom:c.bottom,width:c.width,height:c.height}};
    }''')
    check(bounds['inside'], 'menu is positioned inside the active Feed card')
    trigger_box = triggers.nth(0).bounding_box()
    check(trigger_box is not None and 35 <= trigger_box['width'] <= 37 and trigger_box['height'] >= 43,
          'smartphone ellipsis uses the compact 36px by 44px operation area')

    triggers.nth(0).click()
    page.set_viewport_size({"width": 1024, "height": 900})
    triggers.nth(0).click()
    pc_bounds = page.evaluate('''() => {
      const m=document.querySelector('#articleActionsMenu').getBoundingClientRect();
      const c=document.querySelector('.feed-card').getBoundingClientRect();
      const i=document.querySelector('.article-actions-item').getBoundingClientRect();
      return {inside:m.left>=c.left-1 && m.right<=c.right+1 && m.top>=c.top-1 && m.bottom<=c.bottom+1,itemWidth:i.width,itemHeight:i.height};
    }''')
    check(pc_bounds['inside'], 'PC width also keeps the menu inside the active Feed card')
    check(pc_bounds['itemWidth'] >= 43 and pc_bounds['itemHeight'] >= 43,
          'PC menu items keep the 44px-class operation area')
    page.set_viewport_size({"width": 430, "height": 900})

    triggers.nth(1).click()
    check(triggers.nth(0).get_attribute('aria-expanded') == 'false' and triggers.nth(1).get_attribute('aria-expanded') == 'true',
          'opening another article closes the previous menu state')
    page.mouse.click(425, 895)
    check(menu_locator.is_hidden(), 'outside click closes the menu')

    triggers.nth(0).focus()
    page.keyboard.press('ArrowDown')
    check(not menu_locator.is_hidden() and page.locator('.article-action-stock').evaluate('(e)=>e===document.activeElement'),
          'ArrowDown opens the menu and focuses the first action')
    page.keyboard.press('Escape')
    check(menu_locator.is_hidden() and triggers.nth(0).evaluate('(e)=>e===document.activeElement'),
          'Escape closes the menu and returns focus to the trigger')

    page.evaluate("Object.defineProperty(window,'isSecureContext',{value:false,configurable:true});")
    triggers.nth(0).click()
    page.locator('.article-action-copy').click()
    check(page.evaluate('window.__execCommand') == 'copy', 'insecure context uses the copy fallback')
    check('記事URLをコピーしました' in page.locator('#app-notice').inner_text(), 'copy fallback reports success')

    page.evaluate('''() => {
      Object.defineProperty(window,'isSecureContext',{value:true,configurable:true});
      Object.defineProperty(navigator,'clipboard',{value:{writeText:(value)=>{window.__clipboard=value;return Promise.resolve();}},configurable:true});
    }''')
    triggers.nth(0).click()
    page.locator('.article-action-copy').click()
    page.wait_for_timeout(30)
    check(page.evaluate('window.__clipboard') == 'https://example.com/normal?x=1&y=2', 'secure context uses Clipboard API with the exact URL')

    triggers.nth(0).click()
    page.locator('.article-action-stock').click()
    check(page.locator('#saveContent').get_attribute('data-modal-action') == 'show', 'Stock action opens the existing save modal')
    check(page.locator('.information_modal_dbsave').get_attribute('data-stock-url') == 'https://example.com/normal?x=1&y=2',
          'Stock modal receives the original article URL')
    page.locator('.information_modal_dbsave').click()
    stock_request = page.evaluate('''() => {
      const r=[...window.__requests].reverse().find(x=>x.options.data.action==='stock.create'); return r ? r.options.data : null;
    }''')
    check(stock_request and stock_request['stock_title'] == '通常記事' and stock_request['stock_data'].endswith('y=2'),
          'existing stock.create request receives title and URL')
    page.evaluate('''() => { const r=[...window.__requests].reverse().find(x=>x.options.data.action==='stock.create'); r.resolve({ok:true,data:{stock_id:1}}); }''')
    check('Stockへ保存しました' in page.locator('#app-notice').inner_text(), 'existing Stock success notice is preserved')

    page.evaluate('''() => {
      window.__popup={opener:window,location:{href:''}};
      window.open=function(){window.__popupOpened=true;return window.__popup;};
    }''')
    triggers.nth(1).click()
    page.locator('.article-action-x').click()
    popup = page.evaluate('''() => ({opened:window.__popupOpened,href:window.__popup.location.href,opener:window.__popup.opener})''')
    check(popup['opened'] and popup['href'].startswith('https://x.com/intent/post?'), 'X action opens the Web Intent synchronously')
    query = page.evaluate('''() => { const u=new URL(window.__popup.location.href); return {text:u.searchParams.get('text'),url:u.searchParams.get('url')}; }''')
    check(len(query['text']) == 200 and query['text'].endswith('…'), 'long X title is reduced to 200 Unicode characters')
    check(query['url'] == 'https://example.com/search?q=php&sort=new', 'X Web Intent preserves the exact article URL after encoding')
    check(popup['opener'] is None, 'X popup opener is detached')

    triggers.nth(0).click()
    page.locator('.article-action-task').click()
    check('このタブにTask Widgetがありません' in page.locator('#app-notice').inner_text(), 'Task action reports when no Task Widget exists')

    page.evaluate('''() => {
      const grid=document.querySelector('.content-grid');
      grid.insertAdjacentHTML('beforeend','<section class="dashboard-widget task-card" data-dashboard-widget-id="77" data-task-widget-title="Task A"></section><section class="dashboard-widget task-card" data-dashboard-widget-id="88" data-task-widget-title="Task B"></section>');
    }''')
    triggers.nth(0).click()
    page.locator('.article-action-task').click()
    task_request = page.evaluate('''() => {
      const r=[...window.__requests].reverse().find(x=>x.options.data.action==='task.item.create'); return r ? r.options.data : null;
    }''')
    check(task_request and task_request['widget_id'] == '77', 'Task action uses the first Task Widget in current DOM order')
    check(task_request and task_request['task_title'] == '通常記事', 'Task action sends the article title')
    check(task_request and task_request['task_due_date'] == '' and task_request['task_priority'] == 'normal',
          'Task action uses empty due date and normal priority')
    check(task_request and 'stock_data' not in task_request and 'article_url' not in task_request,
          'Task request does not contain article URL data')

    # Resolve pending Task request only after replacing reload with a marker in this isolated browser test.
    page.evaluate('''() => { const r=[...window.__requests].reverse().find(x=>x.options.data.action==='task.item.create'); r.reject({status:503,responseJSON:{error:{message:'Task test error'}}},'error'); }''')

    triggers.nth(0).click()
    page.locator('.feed-refresh-trigger').click()
    check(menu_locator.is_hidden(), 'normal Feed refresh immediately closes the menu')
    refresh_req = page.evaluate('''() => [...window.__requests].reverse().find(x=>x.options.data.action==='feed.fetch') ? true : false''')
    check(refresh_req, 'normal Feed refresh still uses feed.fetch')

    triggers.nth(1).click()
    page.locator('.search-feed-refresh').click()
    check(menu_locator.is_hidden(), 'Search Feed refresh immediately closes the menu')

    browser.close()

if failures:
    print(f'RESULT: PASS {count-len(failures)} / FAIL {len(failures)}')
    raise SystemExit(1)
print(f'RESULT: PASS {count} / FAIL 0')
