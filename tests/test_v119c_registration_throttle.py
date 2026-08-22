#!/usr/bin/env python3
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


def check(condition: bool, message: str) -> None:
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
    'REGISTRATION_RATE_WINDOW': '900',
    'REGISTRATION_RATE_MAX_IP': '2',
    'REGISTRATION_RATE_BLOCK_SECONDS': '900',
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
            status, headers, page = request(port, 'GET', '/')
            if status == 200:
                break
        except OSError:
            time.sleep(0.05)
    else:
        raise RuntimeError('registration throttle HTTP test server failed to start')

    cookie = headers.get('Set-Cookie', '').split(';', 1)[0]
    csrf = csrf_from(page)
    trap = trap_name_from(page)
    check(len(csrf) == 64 and bool(trap), 'registration page provides CSRF and neutral trap field')

    marker = 'registration-trap-secret-value'
    for attempt in range(1, 4):
        form = urllib.parse.urlencode({
            'token': 'regist',
            'csrf_token': csrf,
            'email': f'new-user-{attempt}@example.test',
            'password': 'correct horse battery staple',
            trap: marker,
        })
        status, response_headers, response_body = request(port, 'POST', '/', form, cookie)
        check(status == 303 and response_headers.get('Location') == './?result=regist_error',
              f'registration attempt {attempt} keeps the existing generic redirect')
        check(marker not in response_body and 'throttl' not in response_body.lower(),
              f'registration attempt {attempt} discloses no trap/throttle detail')

    files = list(THROTTLE_DIR.glob('*.json'))
    check(len(files) == 1, 'registration attempts create one IP-only throttle bucket')
    payload = json.loads(files[0].read_text(encoding='utf-8'))
    check(set(payload) == {'failures', 'blocked_until'}, 'registration throttle persists counters/timestamps only')
    check(len(payload['failures']) == 2, 'configured maximum number of registration attempts is consumed')
    check(int(payload['blocked_until']) > int(time.time()), 'next registration attempt activates the block window')
    raw = files[0].read_text(encoding='utf-8')
    check('new-user-' not in raw and marker not in raw and '127.0.0.1' not in raw,
          'throttle file stores no raw email, trap value, or IP address')

    print('All V1.19-C registration throttle HTTP checks passed.')
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
