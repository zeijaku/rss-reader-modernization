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
html='''<!doctype html><html lang="ja"><head><meta name="csrf-token" content="csrf-v11h"></head><body>
<div id="app-notice" hidden></div><main id="main-content"></main><div id="page-top"></div><nav id="drawerMenu"></nav>
<div class="feed-grid" data-dashboard-widget-location="0" aria-busy="false">
<section class="dashboard-widget task-card" data-dashboard-widget-id="61" data-dashboard-widget-type="task" data-dashboard-widget-location="0" data-dashboard-widget-sort-order="10" data-task-widget-title="仕事">
<button type="button" class="widget-drag-handle"><span class="widget-title-text task-widget-title">仕事</span></button>
<button type="button" class="task-widget-edit-trigger" data-widget-id="61" data-widget-style="primary" data-widget-width="2" data-task-widget-title="仕事"></button>
<ul class="task-list"><li class="task-item" data-task-id="601" data-task-completed="0"><button type="button" class="task-toggle" data-task-id="601" data-task-completed="0"></button><div class="task-item-title">確認作業</div><button type="button" class="task-item-edit-trigger" data-task-id="601" data-task-title="確認作業" data-task-due-date="2026-08-31" data-task-priority="high"></button></li></ul>
<form class="task-item-create-form" data-widget-id="61"><input class="task-create-title" value="新規Task"><input class="task-create-due" value="2026-09-01"><select class="task-create-priority"><option value="normal">normal</option><option value="high" selected>high</option></select><button type="submit">add</button></form>
</section></div>
<form id="registerTaskWidgetForm"><input class="registerTaskWidgetTitleValue" value="Task"><select class="registerTaskWidgetStyle"><option value="primary" selected>primary</option></select><select class="registerTaskWidgetWidth"><option value="1" selected>1</option></select><input class="registerTaskWidgetLocation" value="0"><button type="submit">add widget</button></form>
<form id="changeTaskWidgetForm"><input class="changeTaskWidgetId"><input class="changeTaskWidgetTitleValue"><select class="changeTaskWidgetStyle"><option value="primary">primary</option><option value="warning">warning</option></select><select class="changeTaskWidgetWidth"><option value="1">1</option><option value="2">2</option></select><button type="submit">change widget</button><button type="button" class="delete_task_widget">delete widget</button></form>
<form id="changeTaskItemForm"><input class="changeTaskItemId"><input class="changeTaskItemTitleValue"><input class="changeTaskItemDueDate"><select class="changeTaskItemPriority"><option value="normal">normal</option><option value="high">high</option><option value="low">low</option></select><button type="submit">change item</button><button type="button" class="delete_task_item">delete item</button></form>
</body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path=chromium,headless=True,args=['--no-sandbox'])
    page=browser.new_page(locale='ja-JP',timezone_id='Asia/Tokyo')
    page.set_content(html)
    page.add_script_tag(path=str(ROOT/'public/js/jquery-3.7.1.min.js'))
    page.evaluate('''() => { window.__ajaxCalls=[]; window.confirm=()=>true; jQuery.fn.popover=function(){return this;}; jQuery.fn.drawer=function(){return this;}; jQuery.fn.modal=function(){return this;}; jQuery.ajax=function(options){window.__ajaxCalls.push(options); const chain={done(){return chain;},fail(){return chain;},always(){return chain;}}; return chain;}; }''')
    page.add_script_tag(path=str(ROOT/'public/js/dashboard.js'))
    page.wait_for_timeout(100)
    page.locator('.task-widget-edit-trigger').click()
    check(page.locator('.changeTaskWidgetId').input_value()=='61','Task Widget edit fills Widget id')
    check(page.locator('.changeTaskWidgetTitleValue').input_value()=='仕事','Task Widget edit fills title')
    page.locator('.task-item-edit-trigger').click()
    check(page.locator('.changeTaskItemId').input_value()=='601','Task item edit fills Task id')
    check(page.locator('.changeTaskItemTitleValue').input_value()=='確認作業','Task item edit fills title')
    check(page.locator('.changeTaskItemDueDate').input_value()=='2026-08-31','Task item edit fills due date')
    check(page.locator('.changeTaskItemPriority').input_value()=='high','Task item edit fills priority')
    page.locator('#registerTaskWidgetForm').evaluate('form=>form.requestSubmit()'); page.wait_for_timeout(20)
    create_widget=page.evaluate('window.__ajaxCalls[0]')
    check(create_widget.get('data',{}).get('action')=='widget.task.create','Task Widget create uses expected API action')
    check(create_widget.get('data',{}).get('csrf_token')=='csrf-v11h','Task Widget create includes CSRF')
    page.locator('.task-item-create-form').evaluate('form=>form.requestSubmit()'); page.wait_for_timeout(20)
    create_item=page.evaluate('window.__ajaxCalls[1]')
    check(create_item.get('data',{}).get('action')=='task.item.create','Task item create uses expected API action')
    check(create_item.get('data',{}).get('widget_id')=='61','Task item create remains Widget scoped')
    check(create_item.get('data',{}).get('task_title')=='新規Task','Task item create carries title')
    check(create_item.get('data',{}).get('task_due_date')=='2026-09-01','Task item create carries due date')
    check(create_item.get('data',{}).get('task_priority')=='high','Task item create carries priority')
    page.locator('.task-toggle').click(); page.wait_for_timeout(20)
    toggle=page.evaluate('window.__ajaxCalls[2]')
    check(toggle.get('data',{}).get('action')=='task.item.toggle','Task completion uses expected API action')
    check(toggle.get('data',{}).get('task_completed')=='1','incomplete Task toggles to completed')
    page.locator('.changeTaskItemTitleValue').fill('更新Task'); page.locator('.changeTaskItemPriority').select_option('low')
    page.locator('#changeTaskItemForm').evaluate('form=>form.requestSubmit()'); page.wait_for_timeout(20)
    update=page.evaluate('window.__ajaxCalls[3]')
    check(update.get('data',{}).get('action')=='task.item.update','Task update uses expected API action')
    check(update.get('data',{}).get('task_id')=='601','Task update sends selected Task id')
    check(update.get('data',{}).get('task_title')=='更新Task','Task update carries changed title')
    page.locator('.delete_task_item').click(); page.wait_for_timeout(20)
    delete_item=page.evaluate('window.__ajaxCalls[4]')
    check(delete_item.get('data',{}).get('action')=='task.item.delete','Task delete uses expected API action')
    page.locator('.delete_task_widget').click(); page.wait_for_timeout(20)
    delete_widget=page.evaluate('window.__ajaxCalls[5]')
    check(delete_widget.get('data',{}).get('action')=='widget.task.delete','Task Widget delete uses expected API action')
    browser.close()
if failures: raise SystemExit(1)
print('All V1.1-H real Browser checks passed.')
