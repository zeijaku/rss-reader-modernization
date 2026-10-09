#!/usr/bin/env python3
"""Exercise rate-limit responses through the existing authenticated API fixture."""
from __future__ import annotations
import http.client
import json
import os
from pathlib import Path
import re
import socket
import subprocess
import tempfile
import time
from urllib.parse import urlencode

ROOT = Path(__file__).resolve().parents[1]
ROUTER = ROOT / 'tests' / 'api_http_router.php'


def check(condition: bool, message: str) -> None:
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        raise AssertionError(message)


def call(port: int, method: str, path: str, fields: dict | None = None, cookie: str = ''):
    body = urlencode(fields) if fields is not None else None
    headers = {'Content-Type': 'application/x-www-form-urlencoded'} if body is not None else {}
    if cookie:
        headers['Cookie'] = cookie
    conn = http.client.HTTPConnection('127.0.0.1', port, timeout=10)
    try:
        conn.request(method, path, body=body, headers=headers)
        response = conn.getresponse()
        return response.status, {k.lower(): v for k, v in response.getheaders()}, response.read().decode()
    finally:
        conn.close()


with socket.socket() as sock:
    sock.bind(('127.0.0.1', 0))
    port = sock.getsockname()[1]
db = Path(tempfile.gettempdir()) / f'rss-v147b-rate-{port}.sqlite'
limit_dir = ROOT / 'var' / 'security' / 'api-throttle'
for scope in ('all', 'network'):
    (limit_dir / f'{scope}-42.json').unlink(missing_ok=True)
env = os.environ.copy()
env.update({'APP_ENV': 'testing', 'APP_DEBUG': 'false',
            'APP_HASH_KEY': '0123456789abcdef' * 4, 'DB_DRIVER': 'sqlite',
            'DB_SQLITE_PATH': str(db), 'APP_API_RATE_TOTAL_MAX': '7',
            'APP_API_RATE_NETWORK_MAX': '2', 'APP_API_RATE_WINDOW': '60'})
proc = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', str(ROUTER)],
                        cwd=ROOT, env=env, stdout=subprocess.DEVNULL,
                        stderr=subprocess.DEVNULL)
try:
    for _ in range(80):
        try:
            status, headers, csrf = call(port, 'GET', '/__test_login')
            if status == 200 and re.fullmatch('[a-f0-9]{64}', csrf.strip()):
                break
        except OSError:
            time.sleep(0.05)
    else:
        raise AssertionError('fixture did not start')
    cookie = headers['set-cookie'].split(';', 1)[0]
    csrf = csrf.strip()
    check(cookie.startswith('iguguru_session='), 'authenticated fixture session ready')

    status, _, body = call(port, 'POST', '/api_v1.php', {'action': 'feed.fake'})
    check(status == 401, 'unauthenticated call rejected first')
    status, _, body = call(port, 'POST', '/api_v1.php', {'action': 'feed.fake', 'csrf_token': '0'*64}, cookie)
    check(status == 403, 'invalid CSRF rejected first')
    status, _, body = call(port, 'GET', '/api_v1.php', cookie=cookie)
    check(status == 405, 'invalid method rejected first')

    fields = {'action': 'mail.account.fake', 'csrf_token': csrf}
    for i in range(2):
        status, _, body = call(port, 'POST', '/api_v1.php', fields, cookie)
        check(status == 400 and json.loads(body)['error']['code'] == 'unknown_action',
              f'network attempt {i + 1} dispatched normally')
    status, headers, body = call(port, 'POST', '/api_v1.php', fields, cookie)
    data = json.loads(body)
    check(status == 429 and data['error']['code'] == 'rate_limited', 'network bucket returns HTTP 429 JSON')
    check(1 <= int(headers['retry-after']) <= 60, '429 has bounded Retry-After')
    check('no-store' in headers.get('cache-control', ''), '429 is non-cacheable')
    check(bool(headers.get('x-csrf-token')), '429 keeps CSRF sync header')

    local = {'action': 'feed.fake', 'csrf_token': csrf}
    for i in range(4):
        status, _, body = call(port, 'POST', '/api_v1.php', local, cookie)
        check(status == 400, f'local API attempt {i+1} does not consume network bucket')
    status, _, body = call(port, 'POST', '/api_v1.php', local, cookie)
    check(status == 429 and json.loads(body)['error']['code'] == 'rate_limited',
          'aggregate per-user limit rejects eighth request')
    print('All current API rate-limit HTTP checks passed.')
finally:
    proc.terminate()
    try:
        proc.wait(timeout=3)
    except subprocess.TimeoutExpired:
        proc.kill()
    for scope in ('all', 'network'):
        (limit_dir / f'{scope}-42.json').unlink(missing_ok=True)
    for suffix in ('', '-journal', '-wal', '-shm'):
        Path(str(db) + suffix).unlink(missing_ok=True)
