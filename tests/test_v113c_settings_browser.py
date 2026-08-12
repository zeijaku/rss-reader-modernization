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

failures=[]
def check(cond,msg):
    print(('PASS' if cond else 'FAIL')+': '+msg)
    if not cond:
        failures.append(msg)

csrf='a'*64
html=f'''<!doctype html><html lang="ja"><head><meta name="csrf-token" content="{csrf}"></head><body>
<div id="app-notice" hidden></div>
<main id="main-content" data-dashboard-current-tab="" data-dashboard-tab-count="4">
<form id="settingsForm">
<select class="conf_style"><option value="bootstrap-minty" selected>Minty</option></select>
<select class="conf_style_nav"><option value="primary" selected>Primary</option></select>
<input class="conf_style_navlink1" value="https://example.com/"><input class="conf_style_navlink_view1" value="Example"><input type="radio" name="conf_style_navlink_icon1" value="search" checked>
<input class="conf_style_navlink2" value=""><input class="conf_style_navlink_view2" value=""><input type="radio" name="conf_style_navlink_icon2" value="mail-bulk" checked>
<input class="conf_style_navlink3" value=""><input class="conf_style_navlink_view3" value=""><input type="radio" name="conf_style_navlink_icon3" value="search" checked>
<input class="conf_style_navlink4" value=""><input class="conf_style_navlink_view4" value=""><input type="radio" name="conf_style_navlink_icon4" value="images" checked>
<button type="submit" class="submit_setting">save</button>
</form>
<form id="tabsForm"><input class="conf_style_tabname1" value="Base"><input class="conf_style_tabname2" value="Maint"><input class="conf_style_tabname3" value="IT"><input class="conf_style_tabname4" value="Observe"><button type="submit" class="submit_tab">tabs</button></form>
<form id="rssHighlightKeywordForm"><input id="rssHighlightKeywordInput" value="OpenAI"><button type="submit">add</button></form>
<div id="rssHighlightKeywordStatus" hidden></div><span id="rssHighlightKeywordCount">1</span><div id="rssHighlightKeywordList"><div class="rss-highlight-keyword-item" data-keyword-id="7"><span class="rss-highlight-keyword-value">PHP</span><button type="button" class="rss-highlight-keyword-delete" data-keyword-id="7">delete</button></div></div>
<script type="application/json" id="rssHighlightKeywordData">{{"available":true,"keywords":[{{"keyword_id":7,"keyword_value":"PHP"}}],"max_keywords":50,"max_length":64}}</script>
</main><div id="page-top"></div><nav id="drawerMenu"></nav></body></html>'''

with sync_playwright() as p:
    browser=p.chromium.launch(executable_path=chromium,headless=True,args=['--no-sandbox'])
    page=browser.new_page(locale='ja-JP',timezone_id='Asia/Tokyo')
    page.set_content(html)
    page.add_script_tag(path=str(ROOT/'public/js/jquery-3.7.1.min.js'))
    page.evaluate('''() => {
      window.__requests=[];
      jQuery.fn.popover=function(){return this;}; jQuery.fn.drawer=function(){return this;}; jQuery.fn.modal=function(){return this;};
      jQuery.ajax=function(options){
        const req={options,doneFns:[],failFns:[],alwaysFns:[]};
        const chain={done(fn){req.doneFns.push(fn);return chain;},fail(fn){req.failFns.push(fn);return chain;},always(fn){req.alwaysFns.push(fn);return chain;}};
        req.resolve=function(value){req.doneFns.forEach(fn=>fn(value));req.alwaysFns.forEach(fn=>fn());};
        req.reject=function(xhr,status){req.failFns.forEach(fn=>fn(xhr,status));req.alwaysFns.forEach(fn=>fn());};
        window.__requests.push(req); return chain;
      };
    }''')
    page.add_script_tag(path=str(ROOT/'public/js/dashboard.js'))
    page.wait_for_timeout(80)

    page.locator('#settingsForm').evaluate('(f)=>f.requestSubmit()')
    page.wait_for_timeout(20)
    check(page.evaluate('window.__requests.length')==1,'Display Settings submit sends one API request')
    req=page.evaluate('window.__requests[0].options')
    check(req['data']['action']=='settings.update','Display Settings uses settings.update')
    check(req['data']['csrf_token']==csrf,'Display Settings uses current CSRF token')
    check(req['data']['conf_style']=='bootstrap-minty' and req['data']['conf_style_nav']=='primary','Display Settings sends selected theme and Navbar style')
    check(req['data']['conf_style_navlink1']=='https://example.com/' and req['data']['conf_style_navlink_view1']=='Example','Display Settings sends Navbar link fields')
    check(req['data']['conf_style_navlink_icon1']=='search','Display Settings sends selected Navbar icon')
    check(page.locator('#settingsForm button').is_disabled(),'Display Settings blocks duplicate submit while pending')
    page.evaluate("window.__requests[0].reject({status:500,responseJSON:{error:{message:'test'}}},'error')")
    page.wait_for_timeout(20)
    check(not page.locator('#settingsForm button').is_disabled(),'Display Settings button is restored after request')

    page.locator('#tabsForm').evaluate('(f)=>f.requestSubmit()')
    page.wait_for_timeout(20)
    req=page.evaluate('window.__requests[1].options')
    check(req['data']['action']=='tabs.update','Tab form uses tabs.update')
    check([req['data'][f'conf_style_tabname{i}'] for i in range(1,5)]==['Base','Maint','IT','Observe'],'Tab form sends all four names')
    check(req['data']['csrf_token']==csrf,'Tab form uses current CSRF token')
    page.evaluate("window.__requests[1].reject({status:500,responseJSON:{error:{message:'test'}}},'error')")
    page.wait_for_timeout(20)

    page.fill('#rssHighlightKeywordInput','OpenAI')
    page.locator('#rssHighlightKeywordForm').evaluate('(f)=>f.requestSubmit()')
    page.wait_for_timeout(20)
    req=page.evaluate('window.__requests[2].options')
    check(req['data']['action']=='feed.keyword.create' and req['data']['keyword_value']=='OpenAI','Highlight add uses feed.keyword.create with entered keyword')
    check(req['data']['csrf_token']==csrf,'Highlight add uses current CSRF token')
    page.evaluate("window.__requests[2].resolve({ok:true,data:{keyword:{keyword_id:8,keyword_value:'OpenAI'}}})")
    page.wait_for_timeout(20)
    check(page.locator('#rssHighlightKeywordCount').inner_text()=='2','Highlight add updates manager count without reload')
    check(page.locator('#rssHighlightKeywordList').get_by_text('OpenAI').count()==1,'Highlight add renders returned keyword safely')

    page.locator('.rss-highlight-keyword-delete[data-keyword-id="7"]').click()
    page.wait_for_timeout(20)
    req=page.evaluate('window.__requests[3].options')
    check(req['data']['action']=='feed.keyword.delete' and req['data']['keyword_id']==7,'Highlight delete uses feed.keyword.delete with keyword id')
    check('user_id' not in req['data'],'Settings mutations never send a client user id')
    page.evaluate("window.__requests[3].resolve({ok:true,data:{keyword_id:7}})")
    page.wait_for_timeout(20)
    check(page.locator('#rssHighlightKeywordCount').inner_text()=='1','Highlight delete updates manager count')
    check(page.locator('#rssHighlightKeywordList').get_by_text('PHP').count()==0,'Highlight delete removes only deleted keyword')

    browser.close()

if failures:
    raise SystemExit(1)
print(f'All {19} V1.13-C Settings Browser checks passed.')
