#!/usr/bin/env python3
from __future__ import annotations
import http.client
import json
import os
from pathlib import Path
import re
import socket
import subprocess
import time
import urllib.parse

ROOT = Path(__file__).resolve().parents[1]
ROUTER = ROOT / 'tests' / 'api_http_router.php'
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
    conn = http.client.HTTPConnection('127.0.0.1', port, timeout=8)
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
    'APP_API_MAX_REQUEST_BYTES': '65536',
})
proc = subprocess.Popen(
    ['php', '-S', f'127.0.0.1:{port}', str(ROUTER)],
    cwd=ROOT,
    env=env,
    stdout=subprocess.DEVNULL,
    stderr=subprocess.PIPE,
    text=True,
)
try:
    for _ in range(60):
        try:
            status, headers, csrf = request(port, 'GET', '/__test_login')
            if status == 200 and re.fullmatch(r'[a-f0-9]{64}', csrf.strip()):
                break
        except OSError:
            time.sleep(0.05)
    else:
        raise RuntimeError('API request-limit HTTP test server failed to start')

    cookie = headers.get('Set-Cookie', '').split(';', 1)[0]
    csrf = csrf.strip()
    check(cookie.startswith('iguguru_session='), 'test login establishes authenticated session')

    oversized = urllib.parse.urlencode({
        'action': 'feed.fetch',
        'csrf_token': csrf,
        'padding': 'x' * 70000,
    })
    check(len(oversized.encode()) > 65536, 'test request exceeds configured application API byte limit')
    status, response_headers, body = request(port, 'POST', '/api_v1.php', oversized, cookie)
    payload = json.loads(body)
    check(status == 413, 'authenticated oversized API POST returns HTTP 413')
    check(payload.get('ok') is False and payload.get('error', {}).get('code') == 'request_too_large',
          'oversized API POST returns stable request_too_large JSON code')
    check('no-store' in response_headers.get('Cache-Control', ''), 'oversized API response remains non-cacheable')
    check(bool(response_headers.get('X-CSRF-Token')), 'oversized authenticated API response keeps CSRF synchronization header')

    forged = urllib.parse.urlencode({
        'action': 'feed.fetch',
        'csrf_token': '0' * 64,
        'padding': 'x' * 70000,
    })
    status, _, body = request(port, 'POST', '/api_v1.php', forged, cookie)
    payload = json.loads(body)
    check(status == 403 and payload.get('error', {}).get('code') == 'csrf_invalid',
          'CSRF rejection still takes precedence over request-size rejection')

    unauth = urllib.parse.urlencode({'action': 'feed.fetch', 'padding': 'x' * 70000})
    status, _, body = request(port, 'POST', '/api_v1.php', unauth)
    payload = json.loads(body)
    check(status == 401 and payload.get('error', {}).get('code') == 'unauthenticated',
          'authentication boundary still takes precedence for anonymous oversized POST')

    print('All V1.19-C API request-limit HTTP checks passed.')
finally:
    proc.terminate()
    try:
        proc.wait(timeout=3)
    except subprocess.TimeoutExpired:
        proc.kill()
    for path in SESSION_DIR.glob('sess_*'):
        path.unlink()
