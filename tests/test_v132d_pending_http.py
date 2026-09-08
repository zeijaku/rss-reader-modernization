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


def csrf_from(body: str) -> str:
    match = re.search(r'name="csrf_token" value="([a-f0-9]{64})"', body)
    return match.group(1) if match else ''


def seed_pending() -> dict[str, str]:
    output = subprocess.check_output(['php', str(ROOT / 'tests' / 'v132c5_seed_pending.php')], cwd=ROOT, text=True).strip()
    return json.loads(output)


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
        raise RuntimeError('D HTTP test server failed to start')

    pending = seed_pending()
    cookie = f"{SESSION_NAME}={pending['session_id']}"
    status, headers, body = request(port, 'GET', '/', cookie=cookie)
    check(status == 200, 'pending session renders the second-factor challenge')
    check('name="totp_code"' in body and 'name="recovery_code"' in body, 'challenge exposes TOTP and Recovery Code choices together')
    check('name="token" value="2fa_recovery"' in body, 'Recovery Code fallback uses its dedicated POST token')
    check('一度使用すると再利用できません' in body, 'challenge warns that a Recovery Code is one-time use')
    check('no-store' in headers.get('Cache-Control', '').lower(), 'Recovery Code challenge remains no-store')

    csrf = csrf_from(body)
    check(len(csrf) == 64, 'Recovery Code form shares the CSRF-protected pending session')

    malformed = urllib.parse.urlencode({'token': '2fa_recovery', 'csrf_token': csrf, 'recovery_code': 'not-a-valid-code'})
    status, _, body = request(port, 'POST', '/', malformed, cookie=cookie)
    check(status == 200, 'malformed Recovery Code fails closed without granting authentication')
    check('data-auth-panel="2fa"' in body and 'name="recovery_code"' in body, 'failed Recovery Code stays inside the pending second-factor challenge')
    check('data-auth-panel="login"' not in body, 'failed Recovery Code does not reset or bypass the password-verified pending state')

    bad_csrf = urllib.parse.urlencode({'token': '2fa_recovery', 'csrf_token': '0' * 64, 'recovery_code': 'ABCD-EFGH-JKLM-NPQR'})
    status, _, body = request(port, 'POST', '/', bad_csrf, cookie=cookie)
    check(status == 403, 'Recovery Code POST rejects an invalid CSRF token before verification')
    check('data-auth-panel="2fa"' in body, 'CSRF failure never grants Dashboard authentication')

    print('All V1.32-D pending Recovery Code HTTP checks passed.')
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
