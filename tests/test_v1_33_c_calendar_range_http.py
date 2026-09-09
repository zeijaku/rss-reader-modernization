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


def request(port: int, method: str, path: str, values=None, cookie: str | None = None):
    body = None if values is None else urllib.parse.urlencode(values)
    headers: dict[str, str] = {}
    if body is not None:
        headers['Content-Type'] = 'application/x-www-form-urlencoded'
        headers['Content-Length'] = str(len(body.encode()))
    if cookie:
        headers['Cookie'] = cookie
    conn = http.client.HTTPConnection('127.0.0.1', port, timeout=8)
    conn.request(method, path, body=body, headers=headers)
    response = conn.getresponse()
    raw = response.read().decode('utf-8', errors='replace')
    result = response.status, dict(response.getheaders()), raw
    conn.close()
    return result


def create_fixture(path: Path) -> None:
    db = sqlite3.connect(path)
    db.executescript('''
        CREATE TABLE ig_dashboard_widget (
            widget_id INTEGER PRIMARY KEY, widget_owner INTEGER NOT NULL,
            widget_type TEXT NOT NULL, widget_flag INTEGER NOT NULL DEFAULT 0,
            widget_config TEXT NOT NULL
        );
        CREATE TABLE ig_calendar_event (
            calendar_event_id INTEGER PRIMARY KEY AUTOINCREMENT,
            calendar_event_date TEXT NOT NULL, calendar_event_updated_at TEXT NOT NULL,
            calendar_event_flag INTEGER NOT NULL DEFAULT 0, calendar_event_owner INTEGER NOT NULL,
            calendar_event_title TEXT NOT NULL, calendar_event_start_date TEXT NOT NULL,
            calendar_event_end_date TEXT NOT NULL, calendar_event_note TEXT NOT NULL,
            calendar_event_color TEXT NOT NULL DEFAULT 'blue', calendar_event_all_day INTEGER NOT NULL DEFAULT 1,
            calendar_event_start_time TEXT NULL, calendar_event_end_time TEXT NULL,
            calendar_event_url TEXT NULL, calendar_event_repeat_type TEXT NOT NULL DEFAULT 'none',
            calendar_event_repeat_until TEXT NULL
        );
        CREATE TABLE ig_calendar_event_exception (
            calendar_event_exception_id INTEGER PRIMARY KEY AUTOINCREMENT,
            calendar_event_exception_owner INTEGER NOT NULL,
            calendar_event_exception_event_id INTEGER NOT NULL,
            calendar_event_exception_original_start_date TEXT NOT NULL,
            calendar_event_exception_kind TEXT NOT NULL,
            calendar_event_exception_revision INTEGER NOT NULL DEFAULT 1,
            calendar_event_exception_flag INTEGER NOT NULL DEFAULT 0,
            calendar_event_exception_start_date TEXT NULL,
            calendar_event_exception_end_date TEXT NULL,
            calendar_event_exception_title TEXT NULL,
            calendar_event_exception_note TEXT NULL,
            calendar_event_exception_color TEXT NULL,
            calendar_event_exception_all_day INTEGER NULL,
            calendar_event_exception_start_time TEXT NULL,
            calendar_event_exception_end_time TEXT NULL,
            calendar_event_exception_url TEXT NULL,
            calendar_event_exception_created_at TEXT NOT NULL,
            calendar_event_exception_updated_at TEXT NOT NULL,
            UNIQUE (calendar_event_exception_owner, calendar_event_exception_event_id, calendar_event_exception_original_start_date)
        );
        CREATE TABLE ig_task (
            task_id INTEGER PRIMARY KEY AUTOINCREMENT, task_date TEXT NOT NULL,
            task_updated_at TEXT NOT NULL, task_flag INTEGER NOT NULL DEFAULT 0,
            task_owner INTEGER NOT NULL, task_widget_id INTEGER NOT NULL,
            task_title TEXT NOT NULL, task_due_date TEXT NULL,
            task_priority TEXT NOT NULL DEFAULT 'normal', task_completed INTEGER NOT NULL DEFAULT 0,
            task_completed_at TEXT NULL, task_sort_order INTEGER NOT NULL DEFAULT 0
        );
    ''')
    db.execute('INSERT INTO ig_dashboard_widget VALUES (?, ?, ?, ?, ?)',
               (10, 42, 'calendar', 0, json.dumps({'schema': 1, 'title': 'Calendar', 'show_completed_tasks': False})))
    db.execute('INSERT INTO ig_dashboard_widget VALUES (?, ?, ?, ?, ?)',
               (20, 99, 'calendar', 0, json.dumps({'schema': 1, 'title': 'Other', 'show_completed_tasks': False})))
    db.execute('INSERT INTO ig_dashboard_widget VALUES (?, ?, ?, ?, ?)', (30, 42, 'task', 0, '{}'))
    event_sql = '''INSERT INTO ig_calendar_event (
        calendar_event_id, calendar_event_date, calendar_event_updated_at, calendar_event_flag,
        calendar_event_owner, calendar_event_title, calendar_event_start_date, calendar_event_end_date,
        calendar_event_note, calendar_event_color, calendar_event_all_day, calendar_event_start_time,
        calendar_event_end_time, calendar_event_url, calendar_event_repeat_type, calendar_event_repeat_until
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'''
    rows = [
        (1, '2026-09-01', '2026-09-01', 0, 42, '<b>会議</b>', '2026-09-10', '2026-09-10',
         '<script>alert(1)</script>', 'purple', 0, '09:00:00', '10:00:00', 'https://example.com', 'none', None),
        (2, '2026-08-31', '2026-08-31', 0, 42, '毎週', '2026-08-31', '2026-09-01',
         '', 'yellow', 1, None, None, None, 'weekly', '2026-09-21'),
        (3, '2026-09-01', '2026-09-01', 0, 99, '他人', '2026-09-12', '2026-09-12',
         '', 'red', 1, None, None, None, 'none', None),
    ]
    db.executemany(event_sql, rows)
    db.execute('''INSERT INTO ig_task (
        task_date, task_updated_at, task_flag, task_owner, task_widget_id, task_title,
        task_due_date, task_priority, task_completed, task_completed_at, task_sort_order
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)''',
               ('2026-09-01', '2026-09-01', 0, 42, 30, 'Task', '2026-09-15', 'high', 0, None, 0))
    db.commit()
    db.close()


if shutil.which('php') is None:
    print('SKIP: PHP CLI is not available for the Calendar range HTTP integration test')
    print('RESULT: PASS 0 / FAIL 0 / SKIP 1')
    raise SystemExit(0)

for session_file in SESSION_DIR.glob('sess_*'):
    session_file.unlink()

port = free_port()
db_path = Path(tempfile.gettempdir()) / f'rss-v133c-range-{port}.sqlite'
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
            status, headers, csrf = request(port, 'GET', '/__test_login')
            if status == 200 and re.fullmatch(r'[a-f0-9]{64}', csrf.strip()):
                break
        except OSError:
            time.sleep(0.05)
    else:
        raise RuntimeError('Calendar range HTTP server failed to start')

    cookie = headers.get('Set-Cookie', '').split(';', 1)[0]
    csrf = csrf.strip()
    check(cookie.startswith('iguguru_session='), 'HTTP fixture establishes authenticated session')

    status, headers, raw = request(port, 'GET', '/calendar_recurrence_api.php')
    payload = json.loads(raw)
    check(status == 405 and payload.get('error', {}).get('code') == 'method_not_allowed', 'range endpoint remains POST only')
    check('no-store' in headers.get('Cache-Control', ''), 'method rejection remains non-cacheable')

    status, _, raw = request(port, 'POST', '/calendar_recurrence_api.php', {'action': 'calendar.range.list'})
    payload = json.loads(raw)
    check(status == 401 and payload.get('error', {}).get('code') == 'unauthenticated', 'anonymous range request is rejected')

    forged = {
        'action': 'calendar.range.list', 'csrf_token': '0' * 64, 'widget_id': '10',
        'calendar_range_start': '2026-09-01', 'calendar_range_end': '2026-09-30',
    }
    status, _, raw = request(port, 'POST', '/calendar_recurrence_api.php', forged, cookie)
    payload = json.loads(raw)
    check(status == 403 and payload.get('error', {}).get('code') == 'csrf_invalid', 'forged CSRF token is rejected')

    valid = {
        'action': 'calendar.range.list', 'csrf_token': csrf, 'widget_id': '10',
        'calendar_range_start': '2026-09-01', 'calendar_range_end': '2026-09-30',
        'calendar_event_owner': '99',
    }
    status, response_headers, raw = request(port, 'POST', '/calendar_recurrence_api.php', valid, cookie)
    payload = json.loads(raw)
    data = payload.get('data', {})
    events = data.get('events', [])
    keys = [item.get('occurrence_key') for item in events]
    check(status == 200 and payload.get('ok') is True, 'owned Calendar range loads through real HTTP endpoint')
    check(data.get('range_start') == '2026-09-01' and data.get('range_end') == '2026-09-30', 'HTTP response preserves requested range')
    check(len(keys) == len(set(keys)), 'HTTP response contains no duplicate occurrence keys')
    check('event:1:2026-09-10' in keys and 'event:2:2026-09-07' in keys, 'normal and recurring events share one HTTP response')
    check(all(item.get('event_id') != 3 for item in events), 'client owner field cannot expose another owner')
    check(any(item.get('color') == 'purple' for item in events), 'five-color value survives HTTP range response')
    meeting = next(item for item in events if item.get('event_id') == 1)
    check(meeting.get('title') == '<b>会議</b>' and meeting.get('note') == '<script>alert(1)</script>', 'HTML-like title and note remain JSON data')
    check('<script>' not in raw and '\\u003Cscript\\u003E' in raw, 'JSON encoding hex-escapes markup delimiters')
    check(data.get('tasks', [{}])[0].get('title') == 'Task', 'owned Task is returned with the common range')
    check('no-store' in response_headers.get('Cache-Control', ''), 'successful range response is non-cacheable')
    check(bool(response_headers.get('X-CSRF-Token')), 'successful range response keeps CSRF synchronization header')

    invalid = dict(valid)
    invalid['calendar_range_end'] = '2026-10-13'
    status, _, raw = request(port, 'POST', '/calendar_recurrence_api.php', invalid, cookie)
    payload = json.loads(raw)
    check(status == 422 and payload.get('error', {}).get('code') == 'validation_error', '43-day HTTP range is rejected')

    foreign = dict(valid)
    foreign['widget_id'] = '20'
    status, _, raw = request(port, 'POST', '/calendar_recurrence_api.php', foreign, cookie)
    payload = json.loads(raw)
    check(status == 404 and payload.get('error', {}).get('code') == 'not_found', 'another owner Widget is hidden as not found')

    oversized = dict(valid)
    oversized['padding'] = 'x' * 70000
    status, headers, raw = request(port, 'POST', '/calendar_recurrence_api.php', oversized, cookie)
    payload = json.loads(raw)
    check(status == 413 and payload.get('error', {}).get('code') == 'request_too_large', 'range endpoint enforces request byte limit')
    check('no-store' in headers.get('Cache-Control', ''), 'oversized range response remains non-cacheable')
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
