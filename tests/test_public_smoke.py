from __future__ import annotations
import http.client
import os
from pathlib import Path
import re
import socket
import subprocess
import time
import urllib.parse

ROOT = Path(__file__).resolve().parents[1]
PUBLIC = ROOT / 'public'
VERSION_TEXT = (ROOT / 'app' / 'version.php').read_text(encoding='utf-8')
VERSION_MATCH = re.search(r"const APP_VERSION_LABEL = '([^']+)';", VERSION_TEXT)
VERSION_LABEL = VERSION_MATCH.group(1) if VERSION_MATCH else ''

def port():
    with socket.socket() as s:
        s.bind(('127.0.0.1',0))
        return int(s.getsockname()[1])

def req(p, method, path, body=None, cookie=None):
    h={}
    if body is not None:
        h['Content-Type']='application/x-www-form-urlencoded'
        h['Content-Length']=str(len(body.encode()))
    if cookie: h['Cookie']=cookie
    c=http.client.HTTPConnection('127.0.0.1',p,timeout=5)
    c.request(method,path,body=body,headers=h)
    r=c.getresponse(); data=r.read().decode('utf-8','replace'); headers=dict(r.getheaders()); status=r.status; c.close()
    return status,headers,data

def check(cond,msg):
    print(('PASS' if cond else 'FAIL')+': '+msg)
    if not cond: raise AssertionError(msg)

def csrf_from_html(body):
    m=re.search(r'name="csrf_token" value="([a-f0-9]{64})"', body)
    return m.group(1) if m else ''

p=port()
env=os.environ.copy()
env.update({
    'APP_ENV':'testing',
    'APP_DEBUG':'false',
    'APP_HASH_KEY':'0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
    'DB_DRIVER':'mysql','DB_HOST':'test','DB_NAME':'test','DB_USER':'test','DB_PASSWORD':'test',
    'REGISTRATION_ENABLED':'true',
})
proc=subprocess.Popen(['php','-S',f'127.0.0.1:{p}','-t',str(PUBLIC)],cwd=ROOT,env=env,stdout=subprocess.DEVNULL,stderr=subprocess.PIPE,text=True)
try:
    for _ in range(50):
        try:
            status,headers,body=req(p,'GET','/')
            if status==200: break
        except OSError: time.sleep(0.05)
    else: raise RuntimeError('public smoke server failed to start')

    check('ログイン' in body and '新規登録' in body,'login and registration UI renders')
    check(body.lstrip().lower().startswith('<!doctype html>'),'public login response declares HTML5 doctype')
    check('<html lang="ja">' in body,'public login response declares Japanese language')
    check('<a class="skip-link" href="#main-content">' in body,'public login response exposes a skip link')
    check('<main id="main-content" class="auth-shell" tabindex="-1">' in body,'public login response has a focusable main landmark')
    check('<h5 class="h5 mb-3 font-weight-normal text-dark"><p>' not in body,'public login response has valid description structure')
    check('<span class="auth-version" data-app-version>' in body,'public login version marker is visible')
    check('./css/dashboard.css' in body and './css/auth.css' in body,'login page references dashboard and dedicated authentication stylesheets')
    check(re.search(r'<link rel="icon" type="image/png" href="\./favicon\.png\?v=[^"]+">', body) is not None,'login page explicitly references the versioned local favicon')
    check(bool(VERSION_LABEL) and VERSION_LABEL in body,'visible release marker renders on login page')
    cookie_header=headers.get('Set-Cookie','')
    cookie=cookie_header.split(';',1)[0]
    check('iguguru_session=' in cookie_header and 'HttpOnly' in cookie_header and 'SameSite=Lax' in cookie_header,'public login page uses hardened session cookie')
    check('Max-Age=7776000' not in cookie_header,'Legacy 90-day cookie is absent')
    csrf=csrf_from_html(body)
    check(len(csrf)==64,'authentication forms expose per-session CSRF token')

    s,_,favicon=req(p,'GET','/favicon.png')
    check(s==200 and len(favicon)>0,'favicon asset is served without a 404 redirect')

    retained_http_assets = [
        '/css/bootstrap-5.3.8.min.css',
        '/css/bootstrap-yeti-5.3.8.min.css',
        '/css/bootstrap-minty-5.3.8.min.css',
        '/css/bootstrap-flatly-5.3.8.min.css',
        '/css/bootstrap-journal-5.3.8.min.css',
        '/css/bootstrap-sketchy-5.3.8.min.css',
        '/css/bootstrap-solar-5.3.8.min.css',
        '/css/bootstrap-slate-5.3.8.min.css',
        '/css/all.css',
        '/css/auth.css',
        '/js/jquery-3.7.1.min.js',
        '/js/bootstrap.bundle-5.3.8.min.js',
        '/js/auth.js',
        '/webfonts/fa-brands-400.woff2',
        '/webfonts/fa-regular-400.woff2',
        '/webfonts/fa-solid-900.woff2',
        '/webfonts/fa-v4compatibility.woff2',
    ]
    for asset_path in retained_http_assets:
        s,_,asset_body=req(p,'GET',asset_path)
        check(s==200 and len(asset_body)>0,'Version 1.14 asset is served: '+asset_path)

    removed_http_assets = [
        '/css/bootstrap.min.css',
        '/css/bootstrap-yeti.min.css',
        '/css/bootstrap-minty.min.css',
        '/css/bootstrap-flatly.min.css',
        '/css/bootstrap-journal.min.css',
        '/css/bootstrap-sketchy.min.css',
        '/css/bootstrap-solar.min.css',
        '/css/bootstrap-slate.min.css',
        '/css/drawer.min.css',
        '/js/popper.min.js',
        '/js/bootstrap.min.js',
        '/js/iscroll.js',
        '/js/drawer.min.js',
    ]
    for asset_path in removed_http_assets:
        s,_,_=req(p,'GET',asset_path)
        check(s==404,'removed legacy asset is not served: '+asset_path)

    s,_,dashboard_css=req(p,'GET','/css/dashboard.css')
    check(s==200 and '#page-top' in dashboard_css,'dashboard stylesheet is served')
    s,_,dashboard_js=req(p,'GET','/js/dashboard.js')
    check(s==200 and 'function initDashboard()' in dashboard_js,'dashboard JavaScript is served')
    s,_,_=req(p,'GET','/logout.php')
    check(s==405,'direct GET logout is rejected')

    form=urllib.parse.urlencode({'token':'regist','email':'new@example.com','password':'short','csrf_token':csrf})
    s,h,_=req(p,'POST','/',form,cookie=cookie)
    check(s==303 and h.get('Location')=='./?result=regist_password','short registration password is rejected before DB write with valid CSRF')

    form=urllib.parse.urlencode({'token':'login','email':'not-an-email','password':'not-a-real-password','csrf_token':csrf})
    s,_,b=req(p,'POST','/',form,cookie=cookie)
    check(s==200 and 'Login failed.' in b,'invalid login fails safely with valid CSRF')

    form=urllib.parse.urlencode({'token':'login','email':'not-an-email','password':'not-a-real-password'})
    s,_,b=req(p,'POST','/',form,cookie=cookie)
    check(s==403 and 'form expired' in b.lower(),'login without CSRF is rejected before authentication')

    form=urllib.parse.urlencode({'token':'regist','email':'new@example.com','password':'correct horse battery staple','csrf_token':'0'*64})
    s,_,b=req(p,'POST','/',form,cookie=cookie)
    check(s==403 and 'form expired' in b.lower(),'registration with wrong CSRF is rejected before DB access')

    print('All public HTTP smoke checks passed.')
finally:
    proc.terminate()
    try: proc.wait(timeout=3)
    except subprocess.TimeoutExpired: proc.kill()
