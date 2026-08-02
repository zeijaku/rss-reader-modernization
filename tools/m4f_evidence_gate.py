#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
from pathlib import Path
import re
import sys
from typing import Any

EXPECTED_CHECKPOINT = '1.0.0-rc1'
ALLOWED_STATUS = {'PENDING', 'PASS', 'FAIL', 'BLOCKED'}
REQUIRED_IDS = (
    'local_regression',
    'rc_package_verify',
    'github_ci_php81',
    'github_ci_php84',
    'healthcheck',
    'database_verify',
    'install_or_update',
    'registration',
    'login_logout_session',
    'feed_crud',
    'rss2_live',
    'rss1_live',
    'atom_live',
    'feed_failure_recovery',
    'cache_retry_conditional',
    'stock',
    'settings',
    'browser_desktop',
    'browser_mobile',
    'keyboard_focus_aria',
    'themes',
    'assets_console',
    'backup_restore',
    'rollback',
    'public_secret_scan',
)
FORBIDDEN_KEYS = {
    'password', 'db_password', 'secret', 'app_hash_key', 'token',
    'cookie', 'session_id', 'authorization', 'private_key',
}
SECRET_PATTERNS = (
    re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'),
    re.compile(r'\bAKIA[0-9A-Z]{16}\b'),
    re.compile(r'\bsk-[A-Za-z0-9_-]{20,}\b'),
    re.compile(r'(?i)authorization:\s*(?:basic|bearer)\s+\S+'),
)


class EvidenceError(ValueError):
    pass


def walk(value: Any, path: str = '$'):
    yield path, value
    if isinstance(value, dict):
        for key, item in value.items():
            yield from walk(item, f'{path}.{key}')
    elif isinstance(value, list):
        for index, item in enumerate(value):
            yield from walk(item, f'{path}[{index}]')


def validate(data: dict[str, Any]) -> tuple[dict[str, int], list[str]]:
    errors: list[str] = []
    if data.get('schema_version') != 1:
        errors.append('schema_version must be 1')
    if data.get('checkpoint') != EXPECTED_CHECKPOINT:
        errors.append(f'checkpoint must be {EXPECTED_CHECKPOINT}')
    if data.get('overall_status') not in {'HOLD', 'PASS', 'FAIL'}:
        errors.append('overall_status must be HOLD, PASS or FAIL')

    for path, value in walk(data):
        if isinstance(value, dict):
            for key in value:
                if str(key).lower() in FORBIDDEN_KEYS:
                    errors.append(f'forbidden secret-bearing key: {path}.{key}')
        if isinstance(value, str):
            for pattern in SECRET_PATTERNS:
                if pattern.search(value):
                    errors.append(f'high-signal secret pattern found at {path}')

    checks = data.get('checks')
    if not isinstance(checks, list):
        raise EvidenceError('checks must be a list')

    seen: dict[str, dict[str, Any]] = {}
    counts = {status: 0 for status in ALLOWED_STATUS}
    for index, item in enumerate(checks):
        if not isinstance(item, dict):
            errors.append(f'checks[{index}] must be an object')
            continue
        check_id = item.get('id')
        status = item.get('status')
        if not isinstance(check_id, str) or not check_id:
            errors.append(f'checks[{index}].id is required')
            continue
        if check_id in seen:
            errors.append(f'duplicate check id: {check_id}')
        seen[check_id] = item
        if status not in ALLOWED_STATUS:
            errors.append(f'invalid status for {check_id}: {status}')
        else:
            counts[status] += 1
        if item.get('required') is not True:
            errors.append(f'required flag must be true for {check_id}')
        for field in ('category', 'evidence', 'notes'):
            if field not in item or not isinstance(item[field], str):
                errors.append(f'{field} must be a string for {check_id}')

    missing = [check_id for check_id in REQUIRED_IDS if check_id not in seen]
    extra = [check_id for check_id in seen if check_id not in REQUIRED_IDS]
    if missing:
        errors.append('missing required checks: ' + ', '.join(missing))
    if extra:
        errors.append('unknown checks: ' + ', '.join(extra))

    expected_overall = 'PASS'
    if counts['FAIL']:
        expected_overall = 'FAIL'
    elif counts['PENDING'] or counts['BLOCKED']:
        expected_overall = 'HOLD'
    if data.get('overall_status') != expected_overall:
        errors.append(f'overall_status must be {expected_overall} for current check states')

    return counts, errors


def main() -> int:
    parser = argparse.ArgumentParser(description='Validate M4-F real-environment evidence JSON.')
    parser.add_argument('evidence', type=Path)
    parser.add_argument('--require-pass', action='store_true')
    args = parser.parse_args()

    try:
        data = json.loads(args.evidence.read_text(encoding='utf-8'))
        if not isinstance(data, dict):
            raise EvidenceError('top-level JSON must be an object')
        counts, errors = validate(data)
    except (OSError, json.JSONDecodeError, EvidenceError, ValueError) as exc:
        print(f'ERROR: {exc}', file=sys.stderr)
        return 1

    if errors:
        for error in errors:
            print('ERROR: ' + error, file=sys.stderr)
        return 1

    print('M4-F evidence structure is valid.')
    print('Status counts: ' + ', '.join(f'{key}={counts[key]}' for key in sorted(counts)))
    print('Overall: ' + str(data['overall_status']))

    if args.require_pass and data['overall_status'] != 'PASS':
        print('HOLD: required M4-F checks are not all PASS.', file=sys.stderr)
        return 2
    return 0


if __name__ == '__main__':
    sys.exit(main())
