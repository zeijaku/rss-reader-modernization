#!/usr/bin/env python3
from __future__ import annotations

import http.client
import json
import os
from pathlib import Path
import re
import shutil
import socket
import sqlite3
import subprocess
import tempfile
import time
import urllib.parse

ROOT = Path(__file__).resolve().parents[1]
ROUTER = ROOT / 'tests' / 'api_http_router.php'
SESSION_DIR = ROOT / 'var' / 'session'
passed = 0
failed = 0


def check(condition: bool, message: str) -> None:
    global passed, failed
    if condition:
        passed += 1
        print(f'PASS: {message}')
    else:
        failed += 1
        print(f'FAIL: {message}')


def free_port() -> int:
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        return int(sock.getsockname()[1])


def request(port: int, method: str, values=None, cookie: str | None = None):
    body = None if values is None else urllib.parse.urlencode(values)
    headers: dict[str, str] = {}
    if body is not None:
        headers['Content-Type'] = 'application/x-www-form-urlencoded'
        headers['Content-Length'] = str(len(body.encode()))
    if cookie:
        headers['Cookie'] = cookie
    conn = http.client.HTTPConnection('127.0.0.1', port, timeout=8)
    conn.request(method, '/calendar_source_api.php', body=body, headers=headers)
    response = conn.getresponse()
    raw = response.read().decode('utf-8', errors='replace')
    result = response.status, dict(response.getheaders()), raw
    conn.close()
    return result


def create_fixture(path: Path) -> None:
    db = sqlite3.connect(path)
    db.executescript('''
        CREATE TABLE ig_calendar_source (
            calendar_source_id INTEGER PRIMARY KEY AUTOINCREMENT,
            calendar_source_date TEXT NOT NULL,
            calendar_source_updated_at TEXT NOT NULL,
            calendar_source_flag INTEGER NOT NULL DEFAULT 0,
            calendar_source_owner INTEGER NOT NULL,
            calendar_source_name TEXT NOT NULL,
            calendar_source_color TEXT NOT NULL DEFAULT 'blue',
            calendar_source_default INTEGER NOT NULL DEFAULT 0,
            calendar_source_sort_order INTEGER NOT NULL DEFAULT 0
        );
        CREATE TABLE ig_calendar_event (
            calendar_event_id INTEGER PRIMARY KEY AUTOINCREMENT,
            calendar_event_owner INTEGER NOT NULL,
            calendar_event_source_id INTEGER NULL,
            calendar_event_flag INTEGER NOT NULL DEFAULT 0
        );
    ''')
    db.executemany(
        '''INSERT INTO ig_calendar_source (
            calendar_source_id, calendar_source_date, calendar_source_updated_at,
            calendar_source_flag, calendar_source_owner, calendar_source_name,
            calendar_source_color, calendar_source_default, calendar_source_sort_order
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)''',
        [
            (100, '2026-10-04', '2026-10-04', 0, 42, '既定Calendar', 'blue', 1, 0),
            (101, '2026-10-04', '2026-10-04', 0, 42, '仕事', 'green', 0, 1),
            (200, '2026-10-04', '2026-10-04', 0, 99, '他人Calendar', 'red', 1, 0),
        ],
    )
    db.executemany(
        'INSERT INTO ig_calendar_event (calendar_event_id, calendar_event_owner, calendar_event_source_id, calendar_event_flag) VALUES (?, ?, ?, ?)',
        [(1, 42, 101, 0), (2, 99, 200, 0)],
    )
    db.commit()
    db.close()


if shutil.which('php') is None:
    print('SKIP: PHP CLI is not available for the V1.44-A Calendar source HTTP test')
    print('RESULT: PASS 0 / FAIL 0 / SKIP 1')
    raise SystemExit(0)

for session_file in SESSION_DIR.glob('sess_*'):
    session_file.unlink()

port = free_port()
db_path = Path(tempfile.gettempdir()) / f'rss-v144a-source-{port}.sqlite'
for suffix in ('', '-journal', '-wal', '-shm'):
    Path(str(db_path) + suffix).unlink(missing_ok=True)
create_fixture(db_path)

env = os.environ.copy()
env.update({
    'APP_ENV': 'testing',
    'APP_DEBUG': 'false',
    'APP_HASH_KEY': '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
    'DB_DRIVER': 'sqlite',
    'DB_SQLITE_PATH': str(db_path),
    'DB_TABLE_PREFIX': 'ig_',
    'APP_API_MAX_REQUEST_BYTES': '65536',
})
proc = subprocess.Popen(
    ['php', '-S', f'127.0.0.1:{port}', '-t', str(ROOT / 'public'), str(ROUTER)],
    cwd=ROOT,
    env=env,
    stdout=subprocess.DEVNULL,
    stderr=subprocess.PIPE,
    text=True,
)

try:
    for _ in range(80):
        try:
            conn = http.client.HTTPConnection('127.0.0.1', port, timeout=3)
            conn.request('GET', '/__test_login')
            response = conn.getresponse()
            csrf = response.read().decode().strip()
            headers = dict(response.getheaders())
            conn.close()
            if response.status == 200 and re.fullmatch(r'[a-f0-9]{64}', csrf):
                break
        except OSError:
            time.sleep(0.05)
    else:
        raise RuntimeError('V1.44-A Calendar source HTTP server failed to start')

    cookie = headers.get('Set-Cookie', '').split(';', 1)[0]
    check(cookie.startswith('iguguru_session='), 'HTTP fixture establishes authenticated session')

    status, headers, raw = request(port, 'GET')
    payload = json.loads(raw)
    check(status == 405 and payload.get('error', {}).get('code') == 'method_not_allowed',
          'Calendar source endpoint is POST only')
    check('no-store' in headers.get('Cache-Control', ''),
          'method rejection remains non-cacheable')

    status, _, raw = request(port, 'POST', {'action': 'calendar.source.list'})
    payload = json.loads(raw)
    check(status == 401 and payload.get('error', {}).get('code') == 'unauthenticated',
          'anonymous Calendar source request is rejected')

    status, _, raw = request(
        port, 'POST',
        {'action': 'calendar.source.list', 'csrf_token': '0' * 64},
        cookie,
    )
    payload = json.loads(raw)
    check(status == 403 and payload.get('error', {}).get('code') == 'csrf_invalid',
          'forged CSRF token is rejected')

    valid = {'action': 'calendar.source.list', 'csrf_token': csrf}
    status, response_headers, raw = request(port, 'POST', valid, cookie)
    payload = json.loads(raw)
    sources = payload.get('data', {}).get('sources', [])
    check(status == 200 and payload.get('ok') is True,
          'owned Calendar sources load through the real HTTP endpoint')
    check([item.get('source_id') for item in sources] == [100, 101],
          'source list is owner-scoped and excludes another owner')
    check('no-store' in response_headers.get('Cache-Control', ''),
          'successful Calendar source response is non-cacheable')
    check(bool(response_headers.get('X-CSRF-Token')),
          'successful Calendar source response synchronizes CSRF header')

    create = {
        'action': 'calendar.source.create',
        'csrf_token': csrf,
        'calendar_source_name': 'プライベート',
        'calendar_source_color': 'purple',
    }
    status, _, raw = request(port, 'POST', create, cookie)
    created = json.loads(raw)
    new_id = created.get('data', {}).get('source_id')
    check(status == 201 and created.get('ok') is True and isinstance(new_id, int),
          'Calendar source create succeeds')
    check(any(item.get('name') == 'プライベート' for item in created.get('data', {}).get('sources', [])),
          'created Calendar is returned in refreshed source list')

    invalid = dict(create)
    invalid['calendar_source_color'] = 'orange'
    status, _, raw = request(port, 'POST', invalid, cookie)
    payload = json.loads(raw)
    check(status == 422 and payload.get('error', {}).get('code') == 'validation_error',
          'unsupported Calendar source color is rejected')

    foreign = {
        'action': 'calendar.source.update',
        'csrf_token': csrf,
        'calendar_source_id': '200',
        'calendar_source_name': '盗めない',
        'calendar_source_color': 'blue',
    }
    status, _, raw = request(port, 'POST', foreign, cookie)
    payload = json.loads(raw)
    check(status == 404 and payload.get('error', {}).get('code') == 'not_found',
          'another owner Calendar is hidden as not found')

    update = {
        'action': 'calendar.source.update',
        'csrf_token': csrf,
        'calendar_source_id': '101',
        'calendar_source_name': '業務',
        'calendar_source_color': 'red',
    }
    status, _, raw = request(port, 'POST', update, cookie)
    payload = json.loads(raw)
    check(status == 200 and payload.get('ok') is True
          and any(item.get('source_id') == 101 and item.get('name') == '業務'
                  for item in payload.get('data', {}).get('sources', [])),
          'owned Calendar can be renamed and recolored')

    delete = {
        'action': 'calendar.source.delete',
        'csrf_token': csrf,
        'calendar_source_id': '101',
    }
    status, _, raw = request(port, 'POST', delete, cookie)
    payload = json.loads(raw)
    check(status == 200 and payload.get('ok') is True,
          'non-default Calendar can be deleted')

    db = sqlite3.connect(db_path)
    source_id = db.execute(
        'SELECT calendar_event_source_id FROM ig_calendar_event WHERE calendar_event_id = 1'
    ).fetchone()[0]
    source_flag = db.execute(
        'SELECT calendar_source_flag FROM ig_calendar_source WHERE calendar_source_id = 101'
    ).fetchone()[0]
    foreign_name = db.execute(
        'SELECT calendar_source_name FROM ig_calendar_source WHERE calendar_source_id = 200'
    ).fetchone()[0]
    db.close()
    check(source_id == 100 and source_flag == 1,
          'deleting a Calendar moves its active events to the default Calendar before soft delete')
    check(foreign_name == '他人Calendar',
          'foreign owner Calendar remains unchanged after rejected mutation')

    default_delete = dict(delete)
    default_delete['calendar_source_id'] = '100'
    status, _, raw = request(port, 'POST', default_delete, cookie)
    payload = json.loads(raw)
    check(status == 422 and payload.get('error', {}).get('code') == 'validation_error',
          'default Calendar deletion is rejected')

    oversized = dict(valid)
    oversized['padding'] = 'x' * 70000
    status, headers, raw = request(port, 'POST', oversized, cookie)
    payload = json.loads(raw)
    check(status == 413 and payload.get('error', {}).get('code') == 'request_too_large',
          'Calendar source endpoint enforces request byte limit')
    check('no-store' in headers.get('Cache-Control', ''),
          'oversized Calendar source response remains non-cacheable')
finally:
    proc.terminate()
    try:
        proc.wait(timeout=3)
    except subprocess.TimeoutExpired:
        proc.kill()
    for session_file in SESSION_DIR.glob('sess_*'):
        session_file.unlink()
    for suffix in ('', '-journal', '-wal', '-shm'):
        Path(str(db_path) + suffix).unlink(missing_ok=True)

print(f'RESULT: PASS {passed} / FAIL {failed} / SKIP 0')
raise SystemExit(0 if failed == 0 else 1)
