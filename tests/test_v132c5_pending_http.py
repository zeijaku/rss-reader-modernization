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
SESSION_DIR = ROOT / 'var' / 'session'
THROTTLE_DIR = ROOT / 'var' / 'security' / 'login-throttle'
SESSION_NAME = 'iguguru_v132c5_http'


def check(condition: bool, message: str) -> None:
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        raise AssertionError(message)


def free_port() -> int:
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        return int(sock.getsockname()[1])


def request(port: int, method: str, path: str, body: str | None = None, cookie: str | None = None):
    headers: dict[str, str] = {}
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


def cookie_from_headers(headers: dict[str, str], fallback: str) -> str:
    value = headers.get('Set-Cookie', '')
    if not value:
        return fallback
    first = value.split(';', 1)[0]
    return first if first.startswith(SESSION_NAME + '=') else fallback


def csrf_from(body: str) -> str:
    match = re.search(r'name="csrf_token" value="([a-f0-9]{64})"', body)
    return match.group(1) if match else ''


def seed_pending(expired: bool = False) -> dict[str, str]:
    args = ['php', str(ROOT / 'tests' / 'v132c5_seed_pending.php')]
    if expired:
        args.append('--expired')
    output = subprocess.check_output(args, cwd=ROOT, text=True).strip()
    payload = json.loads(output)
    check(payload.get('session_name') == SESSION_NAME, 'pending fixture uses the dedicated C5 session cookie name')
    return payload


if THROTTLE_DIR.exists():
    shutil.rmtree(THROTTLE_DIR)
SESSION_DIR.mkdir(parents=True, exist_ok=True)
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
    'SESSION_COOKIE_NAME': SESSION_NAME,
    'AUTH_2FA_PENDING_TIMEOUT': '300',
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
            status, _, _ = request(port, 'GET', '/')
            if status == 200:
                break
        except OSError:
            time.sleep(0.05)
    else:
        raise RuntimeError('C5 HTTP test server failed to start')

    fresh = seed_pending()
    old_sid = str(fresh['session_id'])
    cookie = f'{SESSION_NAME}={old_sid}'

    status, headers, body = request(port, 'GET', '/', cookie=cookie)
    check(status == 200, 'fresh pending session renders an HTTP 200 second-factor challenge')
    check('data-auth-panel="2fa"' in body and 'name="totp_code"' in body, 'pending session renders the TOTP challenge rather than Dashboard')
    check('data-auth-panel="login"' not in body, 'fresh pending session does not fall back to the password form')
    cache_control = headers.get('Cache-Control', '').lower()
    check('no-store' in cache_control, 'second-factor challenge is protected by no-store response caching')

    challenge_csrf = csrf_from(body)
    check(len(challenge_csrf) == 64, 'second-factor challenge includes a CSRF token')

    api_body = urllib.parse.urlencode({'action': 'settings.update', 'csrf_token': challenge_csrf})
    status, _, api_response = request(port, 'POST', '/api_v1.php', api_body, cookie)
    check(status == 401, 'pending session is rejected by API v1 with HTTP 401')
    parsed = json.loads(api_response)
    check(parsed.get('error', {}).get('code') == 'unauthenticated', 'pending API v1 request preserves the unauthenticated contract')

    status, headers, _ = request(port, 'GET', '/settings.php', cookie=cookie)
    check(status == 302 and headers.get('Location') == './', 'pending session cannot open authenticated Settings directly')

    cancel_body = urllib.parse.urlencode({'token': '2fa_cancel', 'csrf_token': challenge_csrf})
    status, headers, _ = request(port, 'POST', '/', cancel_body, cookie)
    check(status == 303 and headers.get('Location') == './', 'CSRF-protected Cancel returns to Login using a redirect')
    rotated_cookie = cookie_from_headers(headers, cookie)
    check(rotated_cookie != cookie, 'Cancel rotates the pending session identifier')

    status, _, body = request(port, 'GET', '/', cookie=rotated_cookie)
    check(status == 200 and 'data-auth-panel="login"' in body, 'cancelled pending session returns to the normal Login form')
    check('data-auth-panel="2fa"' not in body, 'cancelled pending state cannot reappear in the rotated session')

    # Reusing the pre-cancel SID must not restore the old pending state.
    status, _, body = request(port, 'GET', '/', cookie=cookie)
    check(status == 200 and 'data-auth-panel="login"' in body, 'stale pre-cancel session id cannot restore the factor challenge')

    expired = seed_pending(expired=True)
    expired_cookie = f"{SESSION_NAME}={expired['session_id']}"
    status, headers, body = request(port, 'GET', '/', cookie=expired_cookie)
    check(status == 200 and 'data-auth-panel="login"' in body, 'expired pending state returns to the password Login form')
    check('2段階認証の有効期限が切れました' in body, 'expired pending state displays the dedicated timeout notice')
    replaced_cookie = cookie_from_headers(headers, expired_cookie)
    check(replaced_cookie != expired_cookie, 'expired pending state rotates the session identifier before returning to Login')

    stale_post = urllib.parse.urlencode({'token': '2fa', 'csrf_token': str(expired['csrf_token']), 'totp_code': '123456'})
    status, _, body = request(port, 'POST', '/', stale_post, expired_cookie)
    check(status == 403, 'stale expired challenge submission fails CSRF validation')
    check('data-auth-panel="login"' in body and 'data-auth-panel="2fa"' not in body, 'stale challenge cannot regain pending or authenticated state')

    print('All V1.32-C5 pending HTTP checks passed.')
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
