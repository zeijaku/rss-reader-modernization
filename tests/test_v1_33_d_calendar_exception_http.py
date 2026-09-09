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
    conn.request(method, '/calendar_recurrence_api.php', body=body, headers=headers)
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
    event_sql = '''INSERT INTO ig_calendar_event (
        calendar_event_id, calendar_event_date, calendar_event_updated_at, calendar_event_flag,
        calendar_event_owner, calendar_event_title, calendar_event_start_date, calendar_event_end_date,
        calendar_event_note, calendar_event_color, calendar_event_all_day, calendar_event_start_time,
        calendar_event_end_time, calendar_event_url, calendar_event_repeat_type, calendar_event_repeat_until
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'''
    db.executemany(event_sql, [
        (1, '2026-08-31', '2026-08-31', 0, 42, '毎週', '2026-08-31', '2026-09-01',
         '', 'blue', 1, None, None, None, 'weekly', '2026-12-31'),
        (2, '2026-08-31', '2026-08-31', 0, 99, '他人', '2026-08-31', '2026-08-31',
         '', 'red', 1, None, None, None, 'weekly', '2026-12-31'),
    ])
    db.commit()
    db.close()


def occurrence(events, event_id: int, original_start: str):
    return next((item for item in events if item.get('event_id') == event_id
                 and item.get('original_occurrence_start_date') == original_start), None)


if shutil.which('php') is None:
    print('SKIP: PHP CLI is not available for the Calendar exception HTTP integration test')
    print('RESULT: PASS 0 / FAIL 0 / SKIP 1')
    raise SystemExit(0)

for session_file in SESSION_DIR.glob('sess_*'):
    session_file.unlink()

port = free_port()
db_path = Path(tempfile.gettempdir()) / f'rss-v133d-exception-{port}.sqlite'
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
    cwd=ROOT, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, text=True,
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
        raise RuntimeError('Calendar exception HTTP server failed to start')

    cookie = headers.get('Set-Cookie', '').split(';', 1)[0]
    check(cookie.startswith('iguguru_session='), 'HTTP fixture establishes authenticated session')

    status, headers, raw = request(port, 'GET')
    payload = json.loads(raw)
    check(status == 405 and payload.get('error', {}).get('code') == 'method_not_allowed', 'occurrence endpoint remains POST only')
    check('no-store' in headers.get('Cache-Control', ''), 'method rejection is non-cacheable')

    status, _, raw = request(port, 'POST', {'action': 'calendar.occurrence.cancel'})
    check(status == 401 and json.loads(raw).get('error', {}).get('code') == 'unauthenticated', 'anonymous occurrence write is rejected')

    status, _, raw = request(port, 'POST', {'action': 'calendar.occurrence.cancel', 'csrf_token': '0' * 64}, cookie)
    check(status == 403 and json.loads(raw).get('error', {}).get('code') == 'csrf_invalid', 'forged CSRF token is rejected')

    range_values = {
        'action': 'calendar.range.list', 'csrf_token': csrf, 'widget_id': '10',
        'calendar_range_start': '2026-09-01', 'calendar_range_end': '2026-09-30',
    }
    status, _, raw = request(port, 'POST', range_values, cookie)
    initial = json.loads(raw)['data']
    source = occurrence(initial['events'], 1, '2026-09-07')
    check(status == 200 and source is not None and re.fullmatch(r'[a-f0-9]{64}', source['occurrence_revision']), 'range API supplies editable occurrence and revision')

    update = {
        'action': 'calendar.occurrence.update', 'csrf_token': csrf,
        'event_id': '1', 'original_occurrence_start_date': '2026-09-07',
        'occurrence_revision': source['occurrence_revision'],
        'calendar_event_title': '<b>個別</b>', 'calendar_event_note': '<script>alert(1)</script>',
        'calendar_event_start_date': '2026-09-08', 'calendar_event_end_date': '2026-09-10',
        'calendar_event_color': 'purple', 'calendar_event_all_day': '0',
        'calendar_event_start_time': '09:00', 'calendar_event_end_time': '10:00',
        'calendar_event_url': 'https://example.com/meeting',
    }
    status, response_headers, raw = request(port, 'POST', update, cookie)
    updated = json.loads(raw)
    item = updated.get('data', {}).get('occurrence', {})
    check(status == 200 and updated.get('ok') is True and item.get('exception_kind') == 'override', 'authenticated CSRF-protected occurrence update succeeds')
    check(item.get('occurrence_key') == 'event:1:2026-09-07' and item.get('occurrence_start_date') == '2026-09-08', 'HTTP update keeps stable identity while moving effective date')
    check(item.get('color') == 'purple' and item.get('start_time') == '09:00', 'HTTP update preserves color and time metadata')
    check('<script>' not in raw and '\\u003Cscript\\u003E' in raw, 'occurrence JSON hex-escapes markup delimiters')
    check('no-store' in response_headers.get('Cache-Control', '') and response_headers.get('X-CSRF-Token'), 'successful occurrence response is non-cacheable and synchronizes CSRF')

    status, _, raw = request(port, 'POST', update, cookie)
    conflict = json.loads(raw)
    check(status == 409 and conflict.get('error', {}).get('code') == 'calendar_occurrence_conflict', 'stale HTTP update returns explicit 409 conflict')

    foreign = dict(update)
    foreign.update({'event_id': '2', 'original_occurrence_start_date': '2026-09-07', 'occurrence_revision': 'a' * 64})
    status, _, raw = request(port, 'POST', foreign, cookie)
    check(status == 404 and json.loads(raw).get('error', {}).get('code') == 'not_found', 'another owner occurrence is hidden as 404')

    fake = dict(update)
    fake.update({'original_occurrence_start_date': '2026-09-09', 'occurrence_revision': 'a' * 64})
    status, _, raw = request(port, 'POST', fake, cookie)
    check(status == 404 and json.loads(raw).get('error', {}).get('code') == 'not_found', 'non-occurrence date is hidden as 404')

    malformed = dict(update)
    malformed['occurrence_revision'] = 'not-a-token'
    status, _, raw = request(port, 'POST', malformed, cookie)
    check(status == 422 and json.loads(raw).get('error', {}).get('code') == 'validation_error', 'malformed occurrence token is rejected')

    status, _, raw = request(port, 'POST', range_values, cookie)
    current = occurrence(json.loads(raw)['data']['events'], 1, '2026-09-07')
    cancel = {
        'action': 'calendar.occurrence.cancel', 'csrf_token': csrf, 'event_id': '1',
        'original_occurrence_start_date': '2026-09-07', 'occurrence_revision': current['occurrence_revision'],
    }
    status, _, raw = request(port, 'POST', cancel, cookie)
    cancelled = json.loads(raw)['data']['occurrence']
    check(status == 200 and cancelled.get('is_cancelled') is True, 'HTTP occurrence cancel succeeds')

    status, _, raw = request(port, 'POST', range_values, cookie)
    cancelled_range = json.loads(raw)['data']
    check(occurrence(cancelled_range['events'], 1, '2026-09-07') is None, 'cancelled occurrence is absent from visible HTTP range')
    check(occurrence(cancelled_range['cancelled_occurrences'], 1, '2026-09-07') is not None, 'cancelled occurrence remains available for restore UI')

    restore = dict(cancel)
    restore.update({'action': 'calendar.occurrence.restore', 'occurrence_revision': cancelled['occurrence_revision']})
    status, _, raw = request(port, 'POST', restore, cookie)
    restored = json.loads(raw)['data']['occurrence']
    check(status == 200 and restored.get('is_exception') is False, 'HTTP occurrence restore succeeds')
    status, _, raw = request(port, 'POST', range_values, cookie)
    check(occurrence(json.loads(raw)['data']['events'], 1, '2026-09-07') is not None, 'restored occurrence reappears in HTTP range')

    series = {
        'action': 'calendar.recurrence.update', 'csrf_token': csrf, 'event_id': '1',
        'calendar_event_title': 'shift', 'calendar_event_note': '',
        'calendar_event_start_date': '2026-09-01', 'calendar_event_end_date': '2026-09-02',
        'calendar_event_color': 'blue', 'calendar_event_all_day': '1',
        'calendar_event_start_time': '', 'calendar_event_end_time': '', 'calendar_event_url': '',
        'calendar_event_repeat_type': 'weekly', 'calendar_event_repeat_until': '2026-12-31',
    }
    # The restored row is inactive, so recreate an active override before testing the series guard.
    status, _, raw = request(port, 'POST', range_values, cookie)
    source = occurrence(json.loads(raw)['data']['events'], 1, '2026-09-07')
    update['occurrence_revision'] = source['occurrence_revision']
    status, _, _ = request(port, 'POST', update, cookie)
    check(status == 200, 'active exception is recreated before whole-series compatibility test')
    status, _, raw = request(port, 'POST', series, cookie)
    check(status == 409 and json.loads(raw).get('error', {}).get('code') == 'calendar_occurrence_conflict', 'legacy whole-series endpoint refuses to orphan active exceptions')

    oversized = dict(update)
    oversized['padding'] = 'x' * 70000
    status, headers, raw = request(port, 'POST', oversized, cookie)
    check(status == 413 and json.loads(raw).get('error', {}).get('code') == 'request_too_large', 'occurrence endpoint enforces request byte limit')
    check('no-store' in headers.get('Cache-Control', ''), 'oversized occurrence response remains non-cacheable')

    db = sqlite3.connect(db_path)
    count = db.execute('SELECT COUNT(*) FROM ig_calendar_event_exception WHERE calendar_event_exception_owner = 42').fetchone()[0]
    parent = db.execute('SELECT calendar_event_start_date, calendar_event_title FROM ig_calendar_event WHERE calendar_event_id = 1').fetchone()
    db.close()
    check(count == 1, 'HTTP update/cancel/restore reuses one exception row')
    check(parent == ('2026-08-31', '毎週'), 'rejected whole-series HTTP mutation leaves parent unchanged')
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
