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

csrf_a='a'*64
csrf_b='b'*64
csrf_c='c'*64
html=f'''<!doctype html><html lang="ja"><head><meta name="csrf-token" content="{csrf_a}"></head><body>
<div id="app-notice" hidden></div><div id="accountSettings">
<form id="accountEmailForm"><input class="accountNewEmail" name="new_email" type="email"><input class="accountCurrentPasswordEmail" name="current_password" type="password"><button type="submit">email</button></form>
<form id="accountPasswordForm"><input class="accountCurrentPassword" name="current_password" type="password"><input class="accountNewPassword" name="new_password" type="password"><input class="accountNewPasswordConfirmation" name="new_password_confirmation" type="password"><button type="submit">password</button></form>
</div><main id="main-content" data-dashboard-current-tab="0" data-dashboard-tab-count="4"></main><div id="page-top"></div><nav id="drawerMenu"></nav></body></html>'''

with sync_playwright() as p:
    browser=p.chromium.launch(executable_path=chromium,headless=True,args=['--no-sandbox'])
    page=browser.new_page(locale='ja-JP',timezone_id='Asia/Tokyo')
    page.set_content(html)
    page.add_script_tag(path=str(ROOT/'public/js/jquery-3.7.1.min.js'))
    page.evaluate('''() => {
      window.__requests=[]; window.__modalCalls=[];
      jQuery.fn.popover=function(){return this;}; jQuery.fn.drawer=function(){return this;};
      jQuery.fn.modal=function(action){ window.__modalCalls.push(action); if(action==='hide'){ this.trigger('hidden.bs.modal'); } return this; };
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

    page.fill('.accountNewEmail','next@example.com')
    page.fill('.accountCurrentPasswordEmail','CurrentPass123!')
    page.locator('#accountEmailForm').evaluate('(f)=>f.requestSubmit()')
    page.wait_for_timeout(20)
    check(page.evaluate('window.__requests.length')==1,'email submit sends one API request')
    email_req=page.evaluate('window.__requests[0].options')
    check(email_req['data']['action']=='account.email.update','email request uses correct action')
    check(email_req['data']['new_email']=='next@example.com','email request sends new email')
    check(email_req['data']['current_password']=='CurrentPass123!','email request sends current password')
    check(email_req['data']['csrf_token']==csrf_a,'email request uses current CSRF token')
    check('user_id' not in email_req['data'],'email request sends no user id')
    check(page.locator('#accountEmailForm button').is_disabled(),'email submit button is disabled while pending')
    page.evaluate(f"window.__requests[0].resolve({{ok:true,data:{{csrf_token:'{csrf_b}'}}}})")
    page.wait_for_timeout(20)
    check(page.locator('meta[name="csrf-token"]').get_attribute('content')==csrf_b,'email success installs rotated CSRF token')
    check(page.input_value('.accountNewEmail')=='','email success resets new email field')
    check(page.input_value('.accountCurrentPasswordEmail')=='','email success clears current password')
    check(not page.locator('#accountEmailForm button').is_disabled(),'email submit button is restored')
    check(page.locator('#app-notice').inner_text()=='メールアドレスを変更しました','email success displays notice')
    check('hide' in page.evaluate('window.__modalCalls'),'email success closes modal')

    # Client mismatch should not call the API and must release button.
    page.fill('.accountCurrentPassword','NewPassword123!')
    page.fill('.accountNewPassword','MismatchPass123!')
    page.fill('.accountNewPasswordConfirmation','DifferentPass123!')
    before=page.evaluate('window.__requests.length')
    page.locator('#accountPasswordForm').evaluate('(f)=>f.requestSubmit()')
    page.wait_for_timeout(20)
    check(page.evaluate('window.__requests.length')==before,'password mismatch sends no API request')
    check(page.locator('#app-notice').inner_text()=='新しいパスワードが一致していません','password mismatch displays notice')
    check(not page.locator('#accountPasswordForm button').is_disabled(),'password mismatch releases submit button')

    # Successful password update uses the token rotated by email update.
    page.fill('.accountCurrentPassword','CurrentPass123!')
    page.fill('.accountNewPassword','NewPassword123!')
    page.fill('.accountNewPasswordConfirmation','NewPassword123!')
    page.locator('#accountPasswordForm').evaluate('(f)=>f.requestSubmit()')
    page.wait_for_timeout(20)
    check(page.evaluate('window.__requests.length')==2,'valid password submit sends one request')
    password_req=page.evaluate('window.__requests[1].options')
    check(password_req['data']['action']=='account.password.update','password request uses correct action')
    check(password_req['data']['current_password']=='CurrentPass123!','password request sends current password')
    check(password_req['data']['new_password']=='NewPassword123!' and password_req['data']['new_password_confirmation']=='NewPassword123!','password request sends new password and confirmation')
    check(password_req['data']['csrf_token']==csrf_b,'password request uses rotated email-change CSRF token')
    check('user_id' not in password_req['data'],'password request sends no user id')
    page.evaluate(f"window.__requests[1].resolve({{ok:true,data:{{csrf_token:'{csrf_c}'}}}})")
    page.wait_for_timeout(20)
    check(page.locator('meta[name="csrf-token"]').get_attribute('content')==csrf_c,'password success installs another rotated CSRF token')
    check(all(page.input_value(selector)=='' for selector in ['.accountCurrentPassword','.accountNewPassword','.accountNewPasswordConfirmation']),'password success clears all password fields')
    check(page.locator('#app-notice').inner_text()=='パスワードを変更しました','password success displays notice')

    # Failed email update clears only the secret and preserves the entered email.
    page.fill('.accountNewEmail','retry@example.com')
    page.fill('.accountCurrentPasswordEmail','wrong-password')
    page.locator('#accountEmailForm').evaluate('(f)=>f.requestSubmit()')
    page.wait_for_timeout(20)
    page.evaluate("window.__requests[2].reject({status:403,responseJSON:{error:{message:'現在のパスワードを確認してください。'}}},'error')")
    page.wait_for_timeout(20)
    check(page.locator('#app-notice').inner_text()=='現在のパスワードを確認してください。','API error message is displayed')
    check(page.input_value('.accountCurrentPasswordEmail')=='','failed email request clears current password')
    check(page.input_value('.accountNewEmail')=='retry@example.com','failed email request preserves new email for correction')

    # Pending guard prevents a duplicate request.
    page.fill('.accountCurrentPasswordEmail','CurrentPass123!')
    before=page.evaluate('window.__requests.length')
    page.locator('#accountEmailForm').evaluate('(f)=>{f.requestSubmit();f.requestSubmit();}')
    page.wait_for_timeout(20)
    check(page.evaluate('window.__requests.length')==before+1,'pending email request blocks duplicate submit')
    check(page.locator('#accountEmailForm button').is_disabled(),'pending guard keeps button disabled')
    page.evaluate(f"window.__requests[{before}].resolve({{ok:true,data:{{csrf_token:'{'d'*64}'}}}})")
    page.wait_for_timeout(20)
    check(not page.locator('#accountEmailForm button').is_disabled(),'pending button is restored after completion')

    # Manual modal close clears unfinished credential fields.
    page.fill('.accountNewEmail','unfinished@example.com')
    page.fill('.accountCurrentPasswordEmail','secret-one')
    page.fill('.accountCurrentPassword','secret-two')
    page.fill('.accountNewPassword','secret-three')
    page.fill('.accountNewPasswordConfirmation','secret-three')
    page.locator('#accountSettings').evaluate("el=>jQuery(el).trigger('hidden.bs.modal')")
    page.wait_for_timeout(20)
    check(page.input_value('.accountNewEmail')=='','modal close clears unfinished email value')
    check(all(page.input_value(selector)=='' for selector in ['.accountCurrentPasswordEmail','.accountCurrentPassword','.accountNewPassword','.accountNewPasswordConfirmation']),'modal close clears all password values')
    browser.close()

if failures:
    raise SystemExit(1)
print(f'All {34} V1.1-J real Browser checks passed.')
