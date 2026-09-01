from pathlib import Path
import json
import subprocess
import time
import urllib.request
import urllib.error
import socket

HARNESS = Path('/mnt/data/v130d-api-harness')

pass_count = 0
fail_count = 0

def check(condition, label):
    global pass_count, fail_count
    if condition:
        pass_count += 1
        print('PASS: ' + label)
    else:
        fail_count += 1
        print('FAIL: ' + label)


def free_port():
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        return s.getsockname()[1]


def request(base, method='GET', path='/remote_file_editor_api.php', body=None, headers=None):
    data = body.encode('utf-8') if isinstance(body, str) else body
    req = urllib.request.Request(base + path, data=data, method=method, headers=headers or {})
    try:
        with urllib.request.urlopen(req, timeout=5) as response:
            return response.status, dict(response.headers), response.read().decode('utf-8')
    except urllib.error.HTTPError as e:
        return e.code, dict(e.headers), e.read().decode('utf-8')

port = free_port()
server = subprocess.Popen(
    ['php', '-S', f'127.0.0.1:{port}', '-t', str(HARNESS / 'public')],
    stdout=subprocess.DEVNULL,
    stderr=subprocess.DEVNULL,
)
base = f'http://127.0.0.1:{port}'
try:
    for _ in range(50):
        try:
            urllib.request.urlopen(base + '/remote_file_editor_api.php', timeout=0.2)
        except Exception:
            time.sleep(0.03)
        else:
            break

    status, headers, body = request(base, path='/remote_file_editor_api.php?remote_connection_id=1&path=%2Fdanger.txt', headers={'X-Test-Auth': 'yes'})
    payload = json.loads(body)
    check(status == 200 and payload.get('ok') is True, 'authenticated GET returns editor read data')
    check('<script>' not in body and '\\u003Cscript\\u003E' in body, 'GET response HEX-escapes HTML-sensitive source text')
    check('no-store' in headers.get('Cache-Control', ''), 'GET response is no-store')
    check(headers.get('Cross-Origin-Resource-Policy') == 'same-origin', 'GET response is same-origin protected')

    status, _, body = request(base, path='/remote_file_editor_api.php?remote_connection_id=1&path=%2Fa.txt')
    check(status == 401 and json.loads(body)['error']['code'] == 'unauthenticated', 'unauthenticated request is rejected')

    status, headers, body = request(base, method='PUT', headers={'X-Test-Auth': 'yes'})
    check(status == 405 and 'GET, POST' in headers.get('Allow', ''), 'non-GET/POST method is rejected with Allow header')

    status, _, body = request(base, method='POST', body='x=1', headers={'X-Test-Auth': 'yes', 'Content-Type': 'application/x-www-form-urlencoded'})
    check(status == 415 and json.loads(body)['error']['code'] == 'unsupported_media_type', 'POST requires application/json')

    status, _, body = request(base, method='POST', body='{}', headers={'X-Test-Auth': 'yes', 'Content-Type': 'application/jsonevil'})
    check(status == 415 and json.loads(body)['error']['code'] == 'unsupported_media_type', 'JSON-like media type prefix is not accepted')

    bad_csrf = json.dumps({'csrf_token':'bad','remote_connection_id':1,'path':'/a.txt','text':'x','expected_sha256':'a'*64})
    status, _, body = request(base, method='POST', body=bad_csrf, headers={'X-Test-Auth':'yes','Content-Type':'application/json'})
    check(status == 403 and json.loads(body)['error']['code'] == 'csrf_invalid', 'POST save enforces CSRF')

    status, _, body = request(base, method='POST', body='{bad json', headers={'X-Test-Auth':'yes','Content-Type':'application/json'})
    check(status == 422 and json.loads(body)['error']['code'] == 'editor_state_invalid', 'malformed JSON fails closed')

    good = json.dumps({'csrf_token':'csrf-good','remote_connection_id':1,'path':'/a.txt','text':'saved\n','expected_sha256':'a'*64}, ensure_ascii=False)
    status, headers, body = request(base, method='POST', body=good, headers={'X-Test-Auth':'yes','Content-Type':'application/json;charset=UTF-8'})
    payload = json.loads(body)
    check(status == 200 and payload.get('ok') is True and payload['data']['text'] == 'saved\n', 'valid JSON POST reaches dedicated save backend')
    check(headers.get('X-CSRF-Token') == 'csrf-next', 'save response exposes refreshed CSRF token header')

    conflict = json.dumps({'csrf_token':'csrf-good','remote_connection_id':1,'path':'/conflict.txt','text':'mine','expected_sha256':'a'*64})
    status, _, body = request(base, method='POST', body=conflict, headers={'X-Test-Auth':'yes','Content-Type':'application/json'})
    payload = json.loads(body)
    check(status == 409 and payload['error']['code'] == 'editor_conflict', 'backend conflict is returned as HTTP 409')
    check('Reload before saving' in payload['error']['message'], 'conflict response instructs reload rather than force overwrite')

    missing = json.dumps({'csrf_token':'csrf-good','remote_connection_id':1,'path':'/a.txt','text':'x'})
    status, _, body = request(base, method='POST', body=missing, headers={'X-Test-Auth':'yes','Content-Type':'application/json'})
    check(status == 422 and json.loads(body)['error']['code'] == 'editor_state_invalid', 'missing expected SHA is rejected')

    traversal = json.dumps({'csrf_token':'csrf-good','remote_connection_id':1,'path':'/../a.txt','text':'x','expected_sha256':'a'*64})
    status, _, body = request(base, method='POST', body=traversal, headers={'X-Test-Auth':'yes','Content-Type':'application/json'})
    check(status == 422 and json.loads(body)['error']['code'] == 'editor_state_invalid', 'traversal path is rejected before save')

    generic = json.dumps({'csrf_token':'csrf-good','remote_connection_id':1,'path':'/generic.txt','text':'SECRET_SOURCE_CONTENT','expected_sha256':'a'*64})
    status, _, body = request(base, method='POST', body=generic, headers={'X-Test-Auth':'yes','Content-Type':'application/json'})
    check(status == 500 and json.loads(body)['error']['code'] == 'remote_operation_failed', 'unexpected save exception is mapped to generic error')
    check('SECRET_SOURCE_CONTENT' not in body, 'unexpected save error never echoes source content')

    huge = json.dumps({'csrf_token':'csrf-good','remote_connection_id':1,'path':'/a.txt','text':'x'*70000,'expected_sha256':'a'*64})
    status, _, body = request(base, method='POST', body=huge, headers={'X-Test-Auth':'yes','Content-Type':'application/json'})
    check(status == 413 and json.loads(body)['error']['code'] == 'editor_request_too_large', 'dedicated request envelope rejects oversized POST')

    status, _, body = request(base, path='/remote_file_editor_api.php?remote_connection_id=1&path=%2F', headers={'X-Test-Auth': 'yes'})
    check(status == 404, 'editor root path is not treated as a file')

finally:
    server.terminate()
    try:
        server.wait(timeout=3)
    except subprocess.TimeoutExpired:
        server.kill()

print(f'RESULT: PASS {pass_count} / FAIL {fail_count} / SKIP 0')
raise SystemExit(1 if fail_count else 0)
