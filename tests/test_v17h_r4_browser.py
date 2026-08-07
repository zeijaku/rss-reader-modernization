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
    if not cond: failures.append(msg)

html='''<!doctype html><html lang="ja"><head><meta name="csrf-token" content="csrf-r4"></head><body>
<div id="app-notice" hidden></div><main id="main-content"></main><div id="page-top"></div><nav id="drawerMenu"></nav>
<section class="dashboard-widget calendar-card" data-dashboard-widget-id="71" data-dashboard-widget-type="calendar" data-dashboard-widget-location="0" data-dashboard-widget-sort-order="10">
<div class="calendar-toolbar"><button class="calendar-prev-month"></button><button class="calendar-today"></button><strong class="calendar-month-label"></strong><button class="calendar-next-month"></button></div>
<div class="calendar-days" aria-busy="true"></div></section>
<form id="registerCalendarEventForm"><input class="registerCalendarEventTitleValue"><input class="registerCalendarEventStartDate"><input class="registerCalendarEventEndDate"><textarea class="registerCalendarEventNote"></textarea></form>
<form id="changeCalendarEventForm"><input class="changeCalendarEventId"><input class="changeCalendarEventTitleValue"><input class="changeCalendarEventStartDate"><input class="changeCalendarEventEndDate"><textarea class="changeCalendarEventNote"></textarea></form>
<form id="changeTaskItemForm"><input class="changeTaskItemId"><input class="changeTaskItemTitleValue"><input class="changeTaskItemDueDate"><select class="changeTaskItemPriority"><option value="normal">normal</option></select></form>
</body></html>'''
month={'ok':True,'data':{'year':2026,'month':8,'month_start':'2026-08-01','month_end':'2026-08-31','events':[],'tasks':[],'holidays':{'2026-08-11':'山の日'},'holiday_refresh_due':True}}
refresh={'ok':True,'data':{'refreshed':True,'count':300}}

with sync_playwright() as p:
    browser=p.chromium.launch(executable_path=chromium,headless=True,args=['--no-sandbox'])
    page=browser.new_page(locale='ja-JP',timezone_id='Asia/Tokyo')
    page.set_content(html)
    page.add_script_tag(path=str(ROOT/'public/js/jquery-3.7.1.min.js'))
    page.evaluate('''payload => {
        window.__ajaxCalls=[]; window.__month=payload.month; window.__refresh=payload.refresh;
        jQuery.fn.popover=function(){return this;}; jQuery.fn.drawer=function(){return this;}; jQuery.fn.modal=function(){return this;};
        jQuery.ajax=function(options){
            window.__ajaxCalls.push(options);
            const chain={done(fn){
                if(options.data.action==='calendar.month.list'){setTimeout(()=>fn(window.__month),0);}
                if(options.data.action==='calendar.holiday.refresh'){setTimeout(()=>fn(window.__refresh),0);}
                return chain;
            },fail(){return chain;},always(){return chain;}};
            return chain;
        };
    }''', {'month':month,'refresh':refresh})
    page.add_script_tag(path=str(ROOT/'public/js/dashboard.js'))
    page.add_script_tag(path=str(ROOT/'public/js/calendar.js'))
    page.wait_for_timeout(250)
    calls=page.evaluate('window.__ajaxCalls.map(c => c.data.action)')
    check(calls.count('calendar.holiday.refresh') == 1, 'stale snapshot triggers exactly one background holiday refresh request')
    check(calls.count('calendar.month.list') == 2, 'successful background refresh reloads the visible Calendar once')
    check(page.locator('.calendar-day[data-calendar-date="2026-08-11"]').get_attribute('class').find('calendar-day-holiday') >= 0, 'holiday styling remains after background reload')
    browser.close()

if failures:
    raise SystemExit(1)
print('All V1.7-H/R4 holiday Browser checks passed.')
