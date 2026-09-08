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
SESSION_NAME = 'iguguru_v132c6_http'


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


def seed(mode: str) -> dict[str, object]:
    output = subprocess.check_output(
        ['php', str(ROOT / 'tests' / 'v132c6_seed_session.php'), mode],
        cwd=ROOT,
        text=True,
    ).strip()
    data = json.loads(output)
    check(data.get('session_name') == SESSION_NAME, f'{mode} fixture uses the dedicated C6 session cookie name')
    return data


def csrf_from(body: str) -> str:
    match = re.search(r'name="csrf_token" value="([a-f0-9]{64})"', body)
    return match.group(1) if match else ''


def set_cookie_sid(headers: dict[str, str]) -> str | None:
    value = headers.get('Set-Cookie', '')
    match = re.search(rf'{re.escape(SESSION_NAME)}=([^;]+)', value)
    return match.group(1) if match else None


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
    'APP_TOTP_SECRET_KEY_ID': 'test-key',
    'APP_TOTP_SECRET_KEY_B64': 'AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=',
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
        raise RuntimeError('C6 HTTP test server failed to start')

    # Session fixation: an attacker-chosen unknown SID cannot become a trusted session.
    attacker_sid = 'attackerfixedsessionid1234567890'
    status, headers, body = request(port, 'GET', '/', cookie=f'{SESSION_NAME}={attacker_sid}')
    check(status == 200 and 'data-auth-panel="login"' in body, 'attacker-chosen unknown SID remains anonymous')
    replacement_sid = set_cookie_sid(headers)
    check(replacement_sid is not None and replacement_sid != attacker_sid, 'strict session mode replaces an attacker-chosen unknown SID')

    pending = seed('pending')
    pending_cookie = f"{SESSION_NAME}={pending['session_id']}"
    csrf = str(pending['csrf_token'])

    status, _, body = request(port, 'GET', '/', cookie=pending_cookie)
    check(status == 200 and 'data-auth-panel="2fa"' in body, 'fresh pending session renders only the factor challenge')
    check('data-auth-panel="login"' not in body, 'pending session does not expose a parallel password-login form')

    status, headers, body = request(port, 'GET', '/stock.php', cookie=pending_cookie)
    check(status == 302 and headers.get('Location') == './', 'pending session cannot enter Stock directly')
    check('data-auth-panel="login"' not in body and 'data-dashboard-user-id' not in body, 'Stock redirect leaks neither login surface nor owner Dashboard content')

    forged_login = urllib.parse.urlencode({
        'token': 'login', 'csrf_token': csrf, 'email': 'attacker@example.invalid',
        'password': 'not-a-real-password', 'remember_me': '1',
    })
    status, headers, body = request(port, 'POST', '/stock.php', forged_login, pending_cookie)
    check(status == 302 and headers.get('Location') == './', 'posting credentials to legacy Stock path cannot invoke an alternate login flow')
    check(body == '', 'Stock alternate-login rejection returns no authenticated content')

    forged_api = urllib.parse.urlencode({
        'action': 'account.totp.provisioning', 'csrf_token': csrf, 'user_id': '77',
    })
    status, _, body = request(port, 'POST', '/api_v1.php', forged_api, pending_cookie)
    parsed = json.loads(body)
    check(status == 401 and parsed.get('error', {}).get('code') == 'unauthenticated', 'forged API user_id cannot upgrade pending session to authenticated')

    status, _, body = request(port, 'GET', '/file_preview_api.php?id=1', cookie=pending_cookie)
    parsed = json.loads(body)
    check(status == 401 and parsed.get('error', {}).get('code') == 'unauthenticated', 'pending session cannot use File Library preview endpoint')

    status, _, body = request(port, 'GET', '/remote_file_preview_api.php?remote_connection_id=1&path=test.txt&mode=text', cookie=pending_cookie)
    parsed = json.loads(body)
    check(status == 401 and parsed.get('error', {}).get('code') == 'unauthenticated', 'pending session cannot use Remote File preview endpoint')

    # Invalid CSRF must neither complete nor cancel the pending session.
    bad_csrf_body = urllib.parse.urlencode({'token': '2fa', 'csrf_token': '0' * 64, 'totp_code': '123456'})
    status, _, body = request(port, 'POST', '/', bad_csrf_body, pending_cookie)
    check(status == 403 and 'data-auth-panel="2fa"' in body, 'invalid CSRF cannot submit the second factor')
    status, _, body = request(port, 'GET', '/', cookie=pending_cookie)
    check(status == 200 and 'data-auth-panel="2fa"' in body, 'invalid factor CSRF does not mutate pending state')

    bad_cancel = urllib.parse.urlencode({'token': '2fa_cancel', 'csrf_token': 'f' * 64})
    status, _, body = request(port, 'POST', '/', bad_cancel, pending_cookie)
    check(status == 403 and 'data-auth-panel="2fa"' in body, 'invalid CSRF cannot cancel or transform pending state')

    # PHP array / oversized / Unicode code inputs must fail closed and stay pending.
    for encoded, label in [
        (f'token=2fa&csrf_token={csrf}&totp_code%5B%5D=123456', 'array-shaped TOTP input'),
        (urllib.parse.urlencode({'token': '2fa', 'csrf_token': csrf, 'totp_code': '1' * 100}), 'oversized TOTP input'),
        (urllib.parse.urlencode({'token': '2fa', 'csrf_token': csrf, 'totp_code': '１２３４５６'}), 'Unicode-digit TOTP input'),
    ]:
        status, _, body = request(port, 'POST', '/', encoded, pending_cookie)
        check(status == 200 and 'data-auth-panel="2fa"' in body, f'{label} fails closed without authentication')
        check('data-dashboard-user-id' not in body, f'{label} never renders authenticated Dashboard data')

    # Tampered server-side pending metadata must expire before it can be consumed.
    for mode, label in [
        ('invalid-source', 'invalid pending source'),
        ('future-start', 'future pending timestamp'),
        ('missing-start', 'missing pending timestamp'),
    ]:
        data = seed(mode)
        cookie = f"{SESSION_NAME}={data['session_id']}"
        status, headers, body = request(port, 'GET', '/', cookie=cookie)
        check(status == 200 and 'data-auth-panel="login"' in body, f'{label} is invalidated back to normal Login')
        check('data-auth-panel="2fa"' not in body, f'{label} cannot keep a usable factor challenge')
        replaced = set_cookie_sid(headers)
        check(replaced is not None and replaced != str(data['session_id']), f'{label} rotates the stale session id')

    # The pre-G authenticated HTTP fixture has no database-backed Session Registry.
    # Once G is present, full-auth API requests must pass Registry validation first;
    # G's dedicated tests cover that boundary. Keep the legacy unknown-action HTTP
    # assertion only for source trees that do not yet include Session Management.
    if (ROOT / 'app' / 'auth_session_registry.php').exists():
        print('SKIP: legacy C6 full-auth unknown-action HTTP fixture predates the G Session Registry')
    else:
        authenticated = seed('authenticated')
        auth_cookie = f"{SESSION_NAME}={authenticated['session_id']}"
        auth_csrf = str(authenticated['csrf_token'])
        unknown = urllib.parse.urlencode({'action': 'account.totp.bypass', 'csrf_token': auth_csrf})
        status, _, body = request(port, 'POST', '/api_v1.php', unknown, auth_cookie)
        parsed = json.loads(body)
        check(status == 400 and parsed.get('error', {}).get('code') == 'unknown_action', 'unknown TOTP account action fails closed')

    print('All V1.32-C6 HTTP bypass checks passed.')
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
