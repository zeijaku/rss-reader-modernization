from __future__ import annotations
import http.client
import json
from pathlib import Path
import re
import socket
import subprocess
import time

ROOT = Path(__file__).resolve().parents[1]
ROUTER = ROOT / 'tests' / 'error_http_router.php'


def free_port() -> int:
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        return int(sock.getsockname()[1])


def request(port: int, path: str):
    conn = http.client.HTTPConnection('127.0.0.1', port, timeout=6)
    conn.request('GET', path)
    response = conn.getresponse()
    body = response.read().decode('utf-8', errors='replace')
    result = response.status, dict(response.getheaders()), body
    conn.close()
    return result


def check(condition: bool, message: str):
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        raise AssertionError(message)


port = free_port()
proc = subprocess.Popen(
    ['php', '-S', f'127.0.0.1:{port}', str(ROUTER)],
    cwd=ROOT,
    stdout=subprocess.DEVNULL,
    stderr=subprocess.PIPE,
    text=True,
)
try:
    for _ in range(60):
        try:
            status, _, _ = request(port, '/__error/404')
            if status == 404:
                break
        except OSError:
            time.sleep(0.05)
    else:
        raise RuntimeError('error test server failed to start')

    labels = {
        403: '403 Forbidden',
        404: '404 Not Found',
        500: '500 Internal Server Error',
        503: '503 Service Unavailable',
    }
    for expected, label in labels.items():
        status, headers, body = request(port, f'/__error/{expected}')
        check(status == expected, f'common error page preserves HTTP {expected}')
        check('ページを表示できませんでした' in body and label in body, f'HTTP {expected} uses shared design and status text')
        check('<meta name="robots" content="noindex,nofollow">' in body, f'HTTP {expected} contains noindex,nofollow meta')
        check(headers.get('X-Robots-Tag') == 'noindex, nofollow', f'HTTP {expected} contains X-Robots-Tag')
        check('href="/"' in body and 'RSS Readerへ戻る' in body, f'HTTP {expected} contains a safe return link')
        check('/srv/' not in body and 'password=' not in body and 'Stack trace' not in body, f'HTTP {expected} does not expose internals')

    status, headers, body = request(port, '/__exception')
    check(status == 500, 'unhandled application exception returns HTTP 500')
    check(headers.get('Content-Type', '').startswith('text/html'), 'unhandled page exception keeps HTML response')
    check('ページを表示できませんでした' in body, 'unhandled page exception uses common error screen')
    check(re.search(r'Reference: [a-f0-9]{12}', body) is not None, 'unhandled page exception includes a safe reference id')
    check('sensitive-test-message' not in body and '/srv/private/' not in body and 'password=secret' not in body, 'unhandled page exception hides sensitive details')

    status, headers, body = request(port, '/__config-exception')
    check(status == 500 and 'ページを表示できませんでした' in body, 'runtime configuration failure still uses the common HTML 500 screen')
    check('bad-prefix' not in body and 'DB_TABLE_PREFIX' not in body, 'runtime configuration failure hides configuration details')

    status, headers, body = request(port, '/__api-config-exception')
    config_payload = json.loads(body)
    check(status == 500 and headers.get('Content-Type', '').startswith('application/json'), 'API runtime configuration failure remains JSON')
    check(config_payload.get('error', {}).get('code') == 'internal_error' and 'bad-prefix' not in body, 'API runtime configuration failure remains generic')

    status, headers, body = request(port, '/__api-exception')
    payload = json.loads(body)
    check(status == 500, 'unhandled API exception returns HTTP 500')
    check(headers.get('Content-Type', '').startswith('application/json'), 'unhandled API exception remains JSON')
    check(payload.get('ok') is False and payload.get('error', {}).get('code') == 'internal_error', 'unhandled API exception keeps structured error shape')
    check(re.search(r'Reference: [a-f0-9]{12}', payload['error']['message']) is not None, 'unhandled API exception includes a safe reference id')
    check('<html' not in body.lower() and 'sensitive-test-message' not in body and 'password=secret' not in body, 'unhandled API exception does not become HTML or leak details')

    print('All V1.2-A common error HTTP checks passed.')
finally:
    proc.terminate()
    try:
        proc.wait(timeout=3)
    except subprocess.TimeoutExpired:
        proc.kill()
