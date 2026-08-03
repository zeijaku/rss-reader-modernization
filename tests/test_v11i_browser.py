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
html='''<!doctype html><html lang="ja"><head><meta name="csrf-token" content="csrf-v11i"></head><body>
<div id="app-notice" hidden></div><main id="main-content"></main><div id="page-top"></div><nav id="drawerMenu"></nav>
<div class="feed-grid" data-dashboard-widget-location="0" aria-busy="false">
<section class="dashboard-widget calendar-card" data-dashboard-widget-id="71" data-dashboard-widget-type="calendar" data-dashboard-widget-location="0" data-dashboard-widget-sort-order="10" data-calendar-title="予定" data-calendar-show-completed-tasks="1">
<button type="button" class="widget-drag-handle"><span class="widget-title-text calendar-widget-title">予定</span></button>
<button type="button" class="calendar-widget-edit-trigger" data-widget-id="71" data-widget-style="info" data-widget-width="2" data-calendar-title="予定" data-calendar-show-completed-tasks="1"></button>
<div class="calendar-toolbar"><button type="button" class="calendar-prev-month"></button><button type="button" class="calendar-today"></button><strong class="calendar-month-label"></strong><button type="button" class="calendar-next-month"></button><button type="button" class="calendar-event-add-trigger"></button></div>
<div class="calendar-days" aria-busy="true"></div></section></div>
<form id="registerCalendarWidgetForm"><input class="registerCalendarWidgetTitleValue" value="Calendar"><input type="checkbox" class="registerCalendarShowCompletedTasks" checked><select class="registerCalendarWidgetStyle"><option value="info" selected>info</option></select><select class="registerCalendarWidgetWidth"><option value="2" selected>2</option></select><input class="registerCalendarWidgetLocation" value="0"><button type="submit">add widget</button></form>
<form id="changeCalendarWidgetForm"><input class="changeCalendarWidgetId"><input class="changeCalendarWidgetTitleValue"><input type="checkbox" class="changeCalendarShowCompletedTasks"><select class="changeCalendarWidgetStyle"><option value="info">info</option><option value="warning">warning</option></select><select class="changeCalendarWidgetWidth"><option value="1">1</option><option value="2">2</option></select><button type="submit">change widget</button><button type="button" class="delete_calendar_widget">delete widget</button></form>
<form id="registerCalendarEventForm"><input class="registerCalendarEventTitleValue"><input class="registerCalendarEventStartDate"><input class="registerCalendarEventEndDate"><textarea class="registerCalendarEventNote"></textarea><button type="submit">add event</button></form>
<form id="changeCalendarEventForm"><input class="changeCalendarEventId"><input class="changeCalendarEventTitleValue"><input class="changeCalendarEventStartDate"><input class="changeCalendarEventEndDate"><textarea class="changeCalendarEventNote"></textarea><button type="submit">change event</button><button type="button" class="delete_calendar_event">delete event</button></form>
<form id="changeTaskItemForm"><input class="changeTaskItemId"><input class="changeTaskItemTitleValue"><input class="changeTaskItemDueDate"><select class="changeTaskItemPriority"><option value="normal">normal</option><option value="high">high</option><option value="low">low</option></select></form>
</body></html>'''
month_response={'ok':True,'data':{'year':2026,'month':8,'month_start':'2026-08-01','month_end':'2026-08-31','events':[{'event_id':801,'title':'<予定>','start_date':'2026-08-10','end_date':'2026-08-11','note':'安全なメモ'}],'tasks':[{'task_id':901,'title':'締切Task','due_date':'2026-08-12','priority':'high','completed':False}]}}
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path=chromium,headless=True,args=['--no-sandbox'])
    page=browser.new_page(locale='ja-JP',timezone_id='Asia/Tokyo')
    page.set_content(html)
    page.add_script_tag(path=str(ROOT/'public/js/jquery-3.7.1.min.js'))
    page.evaluate('''response => { window.__ajaxCalls=[]; window.__monthResponse=response; window.confirm=()=>true; jQuery.fn.popover=function(){return this;}; jQuery.fn.drawer=function(){return this;}; jQuery.fn.modal=function(){return this;}; jQuery.ajax=function(options){window.__ajaxCalls.push(options); const chain={done(fn){if(options.data && options.data.action==='calendar.month.list'){setTimeout(()=>fn(window.__monthResponse),0);} return chain;},fail(){return chain;},always(){return chain;}}; return chain;}; }''', month_response)
    page.add_script_tag(path=str(ROOT/'public/js/dashboard.js'))
    page.add_script_tag(path=str(ROOT/'public/js/calendar.js'))
    page.wait_for_timeout(150)
    calls=page.evaluate('window.__ajaxCalls')
    month_calls=[c for c in calls if c.get('data',{}).get('action')=='calendar.month.list']
    check(len(month_calls)==1,'Calendar loads current month once on initialization')
    check(month_calls[0].get('data',{}).get('widget_id')=='71','month request is Widget scoped')
    check(month_calls[0].get('data',{}).get('csrf_token')=='csrf-v11i','month request includes CSRF')
    check(page.locator('.calendar-month-label').inner_text()=='2026年8月','month label renders API month')
    check(page.locator('.calendar-day').count()==42,'August 2026 renders six complete weeks')
    check(page.locator('.calendar-event-entry').count()==2,'two-day event renders on each date')
    check(page.locator('.calendar-event-entry').first.inner_text().strip().endswith('<予定>'),'HTML-like event title remains text')
    check(page.locator('body script').count()==0,'HTML-like event title does not inject a script node')
    check(page.locator('.calendar-task-entry').count()==1,'Task deadline renders in Calendar')
    check('task-priority-high' in page.locator('.calendar-task-entry').get_attribute('class'),'Task priority class is retained')
    page.locator('.calendar-widget-edit-trigger').click()
    check(page.locator('.changeCalendarWidgetId').input_value()=='71','Calendar Widget edit fills Widget id')
    check(page.locator('.changeCalendarWidgetTitleValue').input_value()=='予定','Calendar Widget edit fills title')
    check(page.locator('.changeCalendarShowCompletedTasks').is_checked(),'Calendar Widget edit fills completed Task setting')
    page.locator('.calendar-event-entry').first.click()
    check(page.locator('.changeCalendarEventId').input_value()=='801','Calendar event edit fills event id')
    check(page.locator('.changeCalendarEventTitleValue').input_value()=='<予定>','Calendar event edit fills text title')
    check(page.locator('.changeCalendarEventStartDate').input_value()=='2026-08-10','Calendar event edit fills start date')
    check(page.locator('.changeCalendarEventEndDate').input_value()=='2026-08-11','Calendar event edit fills end date')
    page.locator('.calendar-task-entry').click()
    check(page.locator('.changeTaskItemId').input_value()=='901','Calendar Task entry reuses Task edit id')
    check(page.locator('.changeTaskItemDueDate').input_value()=='2026-08-12','Calendar Task entry reuses Task due date')
    page.locator('.calendar-day[data-calendar-date="2026-08-15"] .calendar-day-number').click()
    check(page.locator('.registerCalendarEventStartDate').input_value()=='2026-08-15','day click pre-fills event start date')
    check(page.locator('.registerCalendarEventEndDate').input_value()=='2026-08-15','day click pre-fills event end date')
    page.locator('.registerCalendarEventTitleValue').fill('新規予定')
    page.locator('.registerCalendarEventNote').fill('メモ')
    page.locator('#registerCalendarEventForm').evaluate('form=>form.requestSubmit()'); page.wait_for_timeout(20)
    create_event=[c for c in page.evaluate('window.__ajaxCalls') if c.get('data',{}).get('action')=='calendar.event.create'][-1]
    check(create_event.get('data',{}).get('calendar_event_title')=='新規予定','event create sends title')
    check(create_event.get('data',{}).get('calendar_event_start_date')=='2026-08-15','event create sends selected start date')
    page.locator('#registerCalendarWidgetForm').evaluate('form=>form.requestSubmit()'); page.wait_for_timeout(20)
    create_widget=[c for c in page.evaluate('window.__ajaxCalls') if c.get('data',{}).get('action')=='widget.calendar.create'][-1]
    check(create_widget.get('data',{}).get('calendar_show_completed_tasks')=='1','Calendar Widget create sends completed Task setting')
    before=len([c for c in page.evaluate('window.__ajaxCalls') if c.get('data',{}).get('action')=='widget.calendar.update'])
    page.locator('#changeCalendarWidgetForm').evaluate('form=>form.requestSubmit()'); page.wait_for_timeout(20)
    after_calls=[c for c in page.evaluate('window.__ajaxCalls') if c.get('data',{}).get('action')=='widget.calendar.update']
    check(len(after_calls)==before+1,'Calendar Widget update sends exactly one request')
    page.locator('.changeCalendarEventTitleValue').fill('更新予定')
    page.locator('#changeCalendarEventForm').evaluate('form=>form.requestSubmit()'); page.wait_for_timeout(20)
    update_event=[c for c in page.evaluate('window.__ajaxCalls') if c.get('data',{}).get('action')=='calendar.event.update'][-1]
    check(update_event.get('data',{}).get('event_id')=='801','event update sends selected id')
    check(update_event.get('data',{}).get('calendar_event_title')=='更新予定','event update sends changed title')
    page.locator('.delete_calendar_event').click(); page.wait_for_timeout(20)
    delete_event=[c for c in page.evaluate('window.__ajaxCalls') if c.get('data',{}).get('action')=='calendar.event.delete'][-1]
    check(delete_event.get('data',{}).get('event_id')=='801','event delete sends selected id')
    page.locator('.delete_calendar_widget').click(); page.wait_for_timeout(20)
    delete_widget=[c for c in page.evaluate('window.__ajaxCalls') if c.get('data',{}).get('action')=='widget.calendar.delete'][-1]
    check(delete_widget.get('data',{}).get('widget_id')=='71','Calendar Widget delete sends selected id')
    page.locator('.calendar-next-month').click(); page.wait_for_timeout(30)
    next_calls=[c for c in page.evaluate('window.__ajaxCalls') if c.get('data',{}).get('action')=='calendar.month.list']
    check(len(next_calls)>=2,'next month control requests another month')
    browser.close()
if failures: raise SystemExit(1)
print('All V1.1-I real Browser checks passed.')
