from __future__ import annotations

import http.client
import os
from pathlib import Path
import socket
import subprocess
import time
import urllib.parse

ROOT = Path(__file__).resolve().parents[1]
PUBLIC = ROOT / 'public'


def free_port() -> int:
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        return int(s.getsockname()[1])


def request(port: int, method: str, path: str, body: str | None = None) -> tuple[int, str]:
    headers: dict[str, str] = {}
    if body is not None:
        headers['Content-Type'] = 'application/x-www-form-urlencoded'
        headers['Content-Length'] = str(len(body.encode()))
    conn = http.client.HTTPConnection('127.0.0.1', port, timeout=5)
    conn.request(method, path, body=body, headers=headers)
    response = conn.getresponse()
    text = response.read().decode('utf-8', 'replace')
    status = response.status
    conn.close()
    return status, text


port = free_port()
env = os.environ.copy()
env.update({
    'APP_ENV': 'development',
    'APP_DEBUG': 'true',
    'APP_LOG_ENABLED': 'false',
    'APP_HASH_KEY': '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
    'DB_DRIVER': 'mysql',
    'DB_HOST': 'test',
    'DB_NAME': 'test',
    'DB_USER': 'test',
    'DB_PASSWORD': 'test',
})

proc = subprocess.Popen(
    ['php', '-S', f'127.0.0.1:{port}', '-t', str(PUBLIC)],
    cwd=ROOT,
    env=env,
    stdout=subprocess.DEVNULL,
    stderr=subprocess.PIPE,
    text=True,
)
responses: list[str] = []
try:
    for _ in range(50):
        try:
            status, body = request(port, 'GET', '/')
            if status == 200:
                responses.append(body)
                break
        except OSError:
            time.sleep(0.05)
    else:
        raise RuntimeError('development-mode smoke server did not start')

    responses.append(request(port, 'GET', '/?tab=not-a-tab')[1])
    responses.append(request(port, 'GET', '/logout.php')[1])
    bad_form = urllib.parse.urlencode({'token': 'login', 'email': 'bad', 'password': 'bad'})
    responses.append(request(port, 'POST', '/', bad_form)[1])
finally:
    proc.terminate()
    try:
        _, stderr = proc.communicate(timeout=3)
    except subprocess.TimeoutExpired:
        proc.kill()
        _, stderr = proc.communicate(timeout=3)

combined = '\n'.join(responses)
for marker in ['Warning:', 'Deprecated:', 'Notice:', 'Fatal error:', 'TypeError:']:
    if marker in combined:
        raise AssertionError(f'PHP diagnostic leaked into development HTTP output: {marker}')

stderr_lower = stderr.lower()
for marker in ['php warning:', 'php deprecated:', 'php notice:', 'php fatal error:', 'uncaught typeerror']:
    if marker in stderr_lower:
        raise AssertionError(f'PHP runtime diagnostic emitted during public smoke: {marker}')

print('PASS: development-mode public smoke emitted no PHP warnings/deprecations/notices/fatal errors')
