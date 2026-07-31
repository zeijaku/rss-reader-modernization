from __future__ import annotations
import http.client
import json
import os
from pathlib import Path
import re
import socket
import subprocess
import sys
import time

ROOT = Path(__file__).resolve().parents[1]
ROUTER = ROOT / 'tests' / 'session_http_router.php'


def free_port() -> int:
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        return int(s.getsockname()[1])


def parse_sid(set_cookie: str | None) -> str | None:
    if not set_cookie:
        return None
    m = re.search(r'iguguru_session=([^;]*)', set_cookie)
    return m.group(1) if m else None


def request(port: int, method: str, path: str, sid: str | None = None, body: str | None = None):
    headers = {}
    if sid is not None:
        headers['Cookie'] = f'iguguru_session={sid}'
    if body is not None:
        headers['Content-Type'] = 'application/x-www-form-urlencoded'
        headers['Content-Length'] = str(len(body.encode()))
    conn = http.client.HTTPConnection('127.0.0.1', port, timeout=5)
    conn.request(method, path, body=body, headers=headers)
    res = conn.getresponse()
    data = res.read().decode('utf-8', errors='replace')
    result = (res.status, dict(res.getheaders()), data)
    conn.close()
    return result


def check(cond: bool, msg: str):
    print(('PASS' if cond else 'FAIL') + ': ' + msg)
    if not cond:
        raise AssertionError(msg)

port = free_port()
proc = subprocess.Popen(
    ['php', '-S', f'127.0.0.1:{port}', str(ROUTER)],
    cwd=ROOT,
    stdout=subprocess.DEVNULL,
    stderr=subprocess.PIPE,
    text=True,
)
try:
    for _ in range(50):
        try:
            status, headers, body = request(port, 'GET', '/__test/state')
            if status == 200:
                break
        except OSError:
            time.sleep(0.05)
    else:
        err = proc.stderr.read() if proc.stderr else ''
        raise RuntimeError('PHP test server did not start: ' + err)

    initial_cookie = headers.get('Set-Cookie')
    sid1 = parse_sid(initial_cookie)
    state1 = json.loads(body)
    check(sid1 is not None and sid1 != '', 'anonymous request receives a session cookie')
    check('HttpOnly' in (initial_cookie or ''), 'HTTP session cookie includes HttpOnly')
    check('SameSite=Lax' in (initial_cookie or ''), 'HTTP session cookie includes SameSite=Lax')
    check('Max-Age=' not in (initial_cookie or '') and 'expires=' not in (initial_cookie or '').lower(), 'session cookie has no persistent expiry')
    check(state1['authenticated'] is False, 'initial session is anonymous')

    status, headers, body = request(port, 'POST', '/__test/login', sid1, '')
    check(status == 200, 'test login endpoint succeeds')
    sid2 = parse_sid(headers.get('Set-Cookie')) or sid1
    state2 = json.loads(body)
    check(sid2 != sid1, 'login changes the browser session id')
    check(state2['authenticated'] is True and state2['user_id'] == 42, 'authenticated session survives login request')
    check(sorted(state2['keys']) == sorted(['user_id','authenticated_at','last_activity','csrf_token']), 'HTTP login session contains only minimal keys')

    status, headers, body = request(port, 'GET', '/__test/state', sid2)
    state3 = json.loads(body)
    check(status == 200 and state3['authenticated'] is True, 'authenticated session survives a new HTTP request')
    csrf = state3['csrf_token']

    status, _, _ = request(port, 'GET', '/logout.php', sid2)
    check(status == 405, 'logout rejects GET')

    status, _, _ = request(port, 'POST', '/logout.php', sid2, 'csrf_token=' + ('0' * 64))
    check(status == 403, 'logout rejects an invalid CSRF token')

    status, _, body = request(port, 'GET', '/__test/state', sid2)
    check(status == 200 and json.loads(body)['authenticated'] is True, 'invalid logout does not destroy the authenticated session')

    status, headers, _ = request(port, 'POST', '/logout.php', sid2, 'csrf_token=' + csrf)
    check(status == 303, 'valid logout returns 303 redirect')
    expired_cookie = headers.get('Set-Cookie', '')
    check('iguguru_session=deleted' in expired_cookie or 'iguguru_session=' in expired_cookie, 'valid logout expires the session cookie')

    # Supplying the old id after session_destroy must not restore authentication.
    status, _, body = request(port, 'GET', '/__test/state', sid2)
    state4 = json.loads(body)
    check(status == 200 and state4['authenticated'] is False, 'destroyed session id cannot restore authentication')

    print('All SB-03 HTTP session checks passed.')
finally:
    proc.terminate()
    try:
        proc.wait(timeout=3)
    except subprocess.TimeoutExpired:
        proc.kill()
