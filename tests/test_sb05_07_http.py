from __future__ import annotations
import http.client
import json
import os
from pathlib import Path
import socket
import subprocess
import time
import urllib.parse

ROOT = Path(__file__).resolve().parents[1]
ROUTER = ROOT / 'tests' / 'api_http_router.php'

def free_port():
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        return s.getsockname()[1]

def request(port, method, path, fields=None, cookie=None):
    body = None
    headers = {}
    if fields is not None:
        body = urllib.parse.urlencode(fields)
        headers['Content-Type'] = 'application/x-www-form-urlencoded'
        headers['Content-Length'] = str(len(body.encode()))
    if cookie:
        headers['Cookie'] = cookie
    conn = http.client.HTTPConnection('127.0.0.1', port, timeout=5)
    conn.request(method, path, body=body, headers=headers)
    resp = conn.getresponse()
    data = resp.read().decode('utf-8', 'replace')
    out = (resp.status, dict(resp.getheaders()), data)
    conn.close()
    return out

def check(cond, msg):
    print(('PASS' if cond else 'FAIL') + ': ' + msg)
    if not cond:
        raise AssertionError(msg)

port = free_port()
env = os.environ.copy()
env.update({
    'APP_ENV':'testing', 'APP_DEBUG':'false',
    'APP_HASH_KEY':'0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
    'DB_DRIVER':'mysql', 'DB_HOST':'test', 'DB_NAME':'test', 'DB_USER':'test', 'DB_PASSWORD':'test',
})
proc = subprocess.Popen(
    ['php', '-S', f'127.0.0.1:{port}', '-t', str(ROOT/'public'), str(ROUTER)],
    cwd=ROOT, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, text=True
)
try:
    for _ in range(60):
        try:
            status, _, _ = request(port, 'GET', '/api_v1.php')
            if status:
                break
        except OSError:
            time.sleep(0.05)
    else:
        raise RuntimeError('server failed to start')

    status, headers, body = request(port, 'GET', '/api_v1.php')
    payload = json.loads(body)
    check(status == 405 and payload.get('ok') is False and payload['error']['code'] == 'method_not_allowed', 'API GET is rejected with JSON 405')
    check(headers.get('Content-Type', '').startswith('application/json'), 'API errors use JSON content type')

    status, _, body = request(port, 'POST', '/api_v1.php', {'action':'unknown.action'})
    payload = json.loads(body)
    check(status == 401 and payload['error']['code'] == 'unauthenticated', 'unauthenticated API request is rejected before CSRF/action')

    status, headers, token = request(port, 'GET', '/__test_login')
    cookie_header = headers.get('Set-Cookie', '')
    cookie = cookie_header.split(';', 1)[0]
    check(status == 200 and len(token) == 64 and cookie.startswith('iguguru_session='), 'test login creates authenticated session and CSRF token')

    status, _, body = request(port, 'POST', '/api_v1.php', {'action':'unknown.action'}, cookie=cookie)
    payload = json.loads(body)
    check(status == 403 and payload['error']['code'] == 'csrf_invalid', 'missing API CSRF token is rejected')

    status, _, body = request(port, 'POST', '/api_v1.php', {'action':'unknown.action','csrf_token':'0'*64}, cookie=cookie)
    payload = json.loads(body)
    check(status == 403 and payload['error']['code'] == 'csrf_invalid', 'wrong API CSRF token is rejected')

    status, _, body = request(port, 'POST', '/api_v1.php', {'csrf_token':token}, cookie=cookie)
    payload = json.loads(body)
    check(status == 400 and payload['error']['code'] == 'invalid_request', 'missing explicit action is rejected')

    status, _, body = request(port, 'POST', '/api_v1.php', {'action':'unknown.action','csrf_token':token}, cookie=cookie)
    payload = json.loads(body)
    check(status == 400 and payload['error']['code'] == 'unknown_action', 'valid CSRF reaches explicit action dispatcher')

    status, _, body = request(port, 'POST', '/api_v1.php', {'action':'content.delete','csrf_token':token}, cookie=cookie)
    payload = json.loads(body)
    check(status == 422 and payload['error']['code'] == 'validation_error', 'valid authenticated request returns structured action validation error')

    status, _, body = request(port, 'POST', '/api_v1.php', {
        'action':'content.create', 'csrf_token':token, 'content_value':'https://example.test/feed', 'content_style':'success', 'content_location':'0'
    }, cookie=cookie)
    payload = json.loads(body)
    check(status == 500 and payload['error']['code'] == 'internal_error', 'unexpected API failure is converted to structured JSON 500')
    check('could not find driver' not in body.lower() and 'DB_PASSWORD' not in body and 'test' not in body, 'API 500 does not expose backend diagnostics or configured DB values')

    print('All SB-05..07 endpoint HTTP checks passed.')
finally:
    proc.terminate()
    try:
        proc.wait(timeout=3)
    except subprocess.TimeoutExpired:
        proc.kill()
