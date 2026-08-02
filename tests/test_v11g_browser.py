from __future__ import annotations
from pathlib import Path
import shutil

ROOT=Path(__file__).resolve().parents[1]
try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('SKIP: Playwright Python package is unavailable.')
    raise SystemExit(0)
chromium=shutil.which('chromium') or shutil.which('chromium-browser') or shutil.which('google-chrome')
if chromium is None:
    print('SKIP: Chromium executable is unavailable.')
    raise SystemExit(0)
failures=[]
def check(cond,msg): print(('PASS' if cond else 'FAIL')+': '+msg); failures.append(msg) if not cond else None
html='''<!doctype html><html lang="ja"><head><meta name="csrf-token" content="csrf-v11g"><style>.memo-body{white-space:pre-wrap}</style></head><body>
<div id="app-notice" hidden></div><main id="main-content"></main><div id="page-top"></div><nav id="drawerMenu"></nav>
<div class="feed-grid" data-dashboard-widget-location="0" aria-busy="false">
<section class="dashboard-widget memo-card" data-dashboard-widget-id="51" data-dashboard-widget-type="memo" data-dashboard-widget-location="0" data-dashboard-widget-sort-order="10" data-memo-id="501">
<button type="button" class="widget-drag-handle"><span class="widget-title-text memo-title">連絡</span></button>
<button type="button" class="memo-edit-trigger" data-widget-id="51" data-memo-id="501" data-widget-style="warning" data-widget-width="2"></button>
<div class="memo-body">一行目\n二行目 &lt;script&gt;</div></section></div>
<form id="registerMemoForm"><input class="registerMemoTitleValue" value="新規"><textarea class="registerMemoBody">本文</textarea><select class="registerMemoStyle"><option value="success" selected>success</option></select><select class="registerMemoWidth"><option value="1" selected>1</option></select><input class="registerMemoLocation" value="0"><button type="submit">add</button></form>
<form id="changeMemoForm"><input class="changeMemoWidgetId"><input class="changeMemoId"><input class="changeMemoTitleValue"><textarea class="changeMemoBody"></textarea><select class="changeMemoStyle"><option value="warning">warning</option><option value="info">info</option></select><select class="changeMemoWidth"><option value="1">1</option><option value="2">2</option></select><button type="submit">change</button><button type="button" class="delete_memo">delete</button></form>
</body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path=chromium,headless=True,args=['--no-sandbox'])
    page=browser.new_page(locale='ja-JP',timezone_id='Asia/Tokyo')
    page.set_content(html)
    page.add_script_tag(path=str(ROOT/'public/js/jquery-3.7.1.min.js'))
    page.evaluate('''() => { window.__ajaxCalls=[]; window.confirm=()=>true; jQuery.fn.popover=function(){return this;}; jQuery.fn.drawer=function(){return this;}; jQuery.fn.modal=function(){return this;}; jQuery.ajax=function(options){window.__ajaxCalls.push(options); const chain={done(){return chain;},fail(){return chain;},always(){return chain;}}; return chain;}; }''')
    page.add_script_tag(path=str(ROOT/'public/js/dashboard.js'))
    page.wait_for_timeout(100)
    memo_text=page.locator('.memo-body').inner_text(); check(memo_text=='一行目\n二行目 <script>','real Chromium renders Memo text and line breaks')
    page.locator('.memo-edit-trigger').click()
    check(page.locator('.changeMemoWidgetId').input_value()=='51','Memo edit fills Widget id')
    check(page.locator('.changeMemoId').input_value()=='501','Memo edit fills Memo id')
    check(page.locator('.changeMemoTitleValue').input_value()=='連絡','Memo edit fills title from text')
    check(page.locator('.changeMemoBody').input_value()=='一行目\n二行目 <script>','Memo edit fills body as text')
    page.locator('#registerMemoForm').evaluate('form=>form.requestSubmit()'); page.wait_for_timeout(20)
    create=page.evaluate('window.__ajaxCalls[0]')
    check(create.get('data',{}).get('action')=='widget.memo.create','Memo create uses expected API action')
    check(create.get('data',{}).get('csrf_token')=='csrf-v11g','Memo create includes CSRF')
    check(create.get('data',{}).get('memo_body')=='本文','Memo create carries body')
    page.locator('.changeMemoTitleValue').fill('更新'); page.locator('.changeMemoBody').fill('更新本文')
    page.locator('#changeMemoForm').evaluate('form=>form.requestSubmit()'); page.wait_for_timeout(20)
    update=page.evaluate('window.__ajaxCalls[1]')
    check(update.get('data',{}).get('action')=='widget.memo.update','Memo update uses expected API action')
    check(update.get('data',{}).get('widget_id')=='51','Memo update remains Widget scoped')
    check(update.get('data',{}).get('memo_title')=='更新','Memo update carries changed title')
    page.locator('.delete_memo').click(); page.wait_for_timeout(20)
    delete=page.evaluate('window.__ajaxCalls[2]')
    check(delete.get('data',{}).get('action')=='widget.memo.delete','Memo delete uses expected API action')
    check(delete.get('data',{}).get('widget_id')=='51','Memo delete sends only selected Widget id')
    browser.close()
if failures: raise SystemExit(1)
print('All V1.1-G real Browser checks passed.')
