from __future__ import annotations
import http.client
import json
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import time
import urllib.parse

ROOT = Path(__file__).resolve().parents[1]
PUBLIC = ROOT / 'public'
THROTTLE_DIR = ROOT / 'var' / 'security' / 'login-throttle'
SESSION_DIR = ROOT / 'var' / 'session'


def free_port() -> int:
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        return int(sock.getsockname()[1])


def request(port: int, method: str, path: str, body: str | None = None, cookie: str | None = None):
    headers = {}
    if body is not None:
        headers['Content-Type'] = 'application/x-www-form-urlencoded'
        headers['Content-Length'] = str(len(body.encode()))
    if cookie:
        headers['Cookie'] = cookie
    conn = http.client.HTTPConnection('127.0.0.1', port, timeout=6)
    conn.request(method, path, body=body, headers=headers)
    response = conn.getresponse()
    data = response.read().decode('utf-8', errors='replace')
    result = response.status, dict(response.getheaders()), data
    conn.close()
    return result


def check(condition: bool, message: str):
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        raise AssertionError(message)


def csrf_from(body: str) -> str:
    match = re.search(r'name="csrf_token" value="([a-f0-9]{64})"', body)
    return match.group(1) if match else ''


def trap_name_from(body: str) -> str:
    match = re.search(r'id="loginContactReference" name="([^"]+)"', body)
    return match.group(1) if match else ''


if THROTTLE_DIR.exists():
    shutil.rmtree(THROTTLE_DIR)
for path in SESSION_DIR.glob('sess_*'):
    path.unlink()

port = free_port()
env = os.environ.copy()
env.update({
    'APP_ENV': 'testing',
    'APP_DEBUG': 'false',
    'APP_HASH_KEY': '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
    'DB_DRIVER': 'mysql',
    'DB_HOST': 'test',
    'DB_NAME': 'test',
    'DB_USER': 'test',
    'DB_PASSWORD': 'test',
    'REGISTRATION_ENABLED': 'true',
    'LOGIN_RATE_MAX_PAIR': '5',
    'LOGIN_RATE_MAX_IP': '30',
})
proc = subprocess.Popen(
    ['php', '-S', f'127.0.0.1:{port}', '-t', str(PUBLIC)],
    cwd=ROOT,
    env=env,
    stdout=subprocess.DEVNULL,
    stderr=subprocess.PIPE,
    text=True,
)
try:
    for _ in range(60):
        try:
            status, headers, body = request(port, 'GET', '/')
            if status == 200:
                break
        except OSError:
            time.sleep(0.05)
    else:
        raise RuntimeError('authentication HTTP test server failed to start')

    check('ログアウトしました。' not in body and 'セッションの有効期限' not in body, 'direct anonymous login-page access shows no logout or expiry warning')
    check('data-auth-panel="login"' in body and 'data-auth-panel="register"' in body, 'login and registration panels both render')
    check(body.count('tabindex="-1" autocomplete="off" aria-hidden="true" inputmode="none"') == 2, 'both form traps avoid keyboard, autofill and screen-reader flow')

    cookie = headers.get('Set-Cookie', '').split(';', 1)[0]
    csrf = csrf_from(body)
    trap_name = trap_name_from(body)
    check(len(csrf) == 64 and cookie.startswith('iguguru_session='), 'authentication page provides CSRF token and secure session boundary')
    check(bool(trap_name) and all(word not in trap_name.lower() for word in ['honeypot', 'bot', 'trap']), 'rendered form trap name is neutral')

    marker = 'should-never-be-reflected-or-logged-9f76'
    form = urllib.parse.urlencode({
        'token': 'login',
        'csrf_token': csrf,
        'email': 'person@example.test',
        'password': 'not-a-real-password',
        trap_name: marker,
    })
    status, _, login_body = request(port, 'POST', '/', form, cookie)
    check(status == 200, 'filled login form trap returns the normal login screen')
    check('Login failed. Please check your email address and password.' in login_body, 'filled login form trap uses generic authentication failure wording')
    check(marker not in login_body and 'Bot' not in login_body, 'filled login form trap reveals neither its value nor detection reason')

    csrf2 = csrf_from(login_body)
    form2 = urllib.parse.urlencode({
        'token': 'login',
        'csrf_token': csrf2,
        'email': 'person@example.test',
        'password': 'not-a-real-password',
        trap_name: marker,
    })
    status, _, _ = request(port, 'POST', '/', form2, cookie)
    check(status == 200, 'repeated suspicious login remains a generic authentication failure')
    throttle_files = list(THROTTLE_DIR.glob('*.json'))
    check(len(throttle_files) >= 2, 'filled login form trap is still counted by Login Throttle')
    throttle_text = ''.join(path.read_text(encoding='utf-8') for path in throttle_files)
    check(marker not in throttle_text and 'person@example.test' not in throttle_text, 'throttle storage contains neither form-trap value nor raw email address')
    for path in throttle_files:
        payload = json.loads(path.read_text(encoding='utf-8'))
        check(set(payload).issubset({'failures', 'blocked_until'}), 'throttle state contains only counters and timestamps')

    no_csrf = urllib.parse.urlencode({
        'token': 'login',
        'email': 'person@example.test',
        'password': 'not-a-real-password',
        trap_name: marker,
    })
    status, _, csrf_body = request(port, 'POST', '/', no_csrf, cookie)
    check(status == 403 and 'form expired' in csrf_body.lower(), 'CSRF rejection still takes precedence over form-trap handling')

    status, headers, fresh_body = request(port, 'GET', '/')
    fresh_cookie = headers.get('Set-Cookie', '').split(';', 1)[0]
    registration_form = urllib.parse.urlencode({
        'token': 'regist',
        'csrf_token': csrf_from(fresh_body),
        'email': 'new-user@example.test',
        'password': 'correct horse battery staple',
        trap_name_from(fresh_body): marker,
    })
    status, headers, registration_body = request(port, 'POST', '/', registration_form, fresh_cookie)
    check(status == 303 and headers.get('Location') == './?result=regist_error', 'filled registration form trap redirects to the existing generic registration error')
    check(marker not in registration_body and 'Bot' not in registration_body, 'registration trap response does not disclose the submitted value or reason')

    print('All V1.2-A authentication HTTP checks passed.')
finally:
    proc.terminate()
    try:
        proc.wait(timeout=3)
    except subprocess.TimeoutExpired:
        proc.kill()
    if THROTTLE_DIR.exists():
        shutil.rmtree(THROTTLE_DIR)
    for path in SESSION_DIR.glob('sess_*'):
        path.unlink()
