#!/usr/bin/env python3
from pathlib import Path
import http.client
import socket
import subprocess
import sys
import time

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []

def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

def free_port() -> int:
    sock = socket.socket()
    sock.bind(('127.0.0.1', 0))
    port = sock.getsockname()[1]
    sock.close()
    return port

def request(port: int, path: str):
    conn = http.client.HTTPConnection('127.0.0.1', port, timeout=5)
    conn.request('GET', path)
    response = conn.getresponse()
    body = response.read().decode('utf-8', errors='replace')
    headers = {key.lower(): value for key, value in response.getheaders()}
    status = response.status
    conn.close()
    return status, headers, body

port = free_port()
proc = subprocess.Popen(
    ['php', '-S', f'127.0.0.1:{port}', str(ROOT / 'tests/v17d_http_router.php')],
    cwd=ROOT,
    stdout=subprocess.DEVNULL,
    stderr=subprocess.DEVNULL,
)
try:
    for _ in range(50):
        try:
            request(port, '/private')
            break
        except OSError:
            time.sleep(0.05)
    else:
        raise RuntimeError('PHP test server did not start')

    status, headers, _ = request(port, '/private')
    check(status == 200, 'Private HTML test endpoint returns HTTP 200')
    check(headers.get('cache-control') == 'private, no-store, max-age=0', 'Private HTML sends exact private no-store Cache-Control')
    check(headers.get('pragma') == 'no-cache' and headers.get('expires') == '0', 'Private HTML sends Pragma and Expires safeguards')

    status, headers, body = request(port, '/api')
    check(status == 200 and body == '{"ok":true}', 'API test endpoint returns JSON payload')
    check(headers.get('cache-control') == 'no-store, max-age=0', 'API sends exact no-store Cache-Control')
    check(headers.get('pragma') == 'no-cache' and headers.get('expires') == '0', 'API sends Pragma and Expires safeguards')

    status, headers, body = request(port, '/error')
    check(status == 404 and 'ページを表示できませんでした' in body, 'Error endpoint retains the common HTTP 404 page')
    check(headers.get('cache-control') == 'no-store, max-age=0', 'Error page sends exact no-store Cache-Control')
    check(headers.get('x-robots-tag') == 'noindex, nofollow', 'Error page retains noindex/nofollow protection')
finally:
    proc.terminate()
    try:
        proc.wait(timeout=3)
    except subprocess.TimeoutExpired:
        proc.kill()

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
