#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import os
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


source = (ROOT / 'tools/healthcheck.php').read_text(encoding='utf-8')
installation = (ROOT / 'docs/installation.md').read_text(encoding='utf-8')
check("PHP_SAPI !== 'cli'" in source and 'http_response_code(404)' in source, 'healthcheck remains CLI-only')
check('conn_db(' not in source, 'healthcheck does not pretend to test a DB connection')
check('DatabaseへLoginしません' in installation, 'documentation states healthcheck DB limitation')
for token in ['APP_VERSION_LABEL', 'PDO drivers', 'Session directory writable', 'Feed cache writable', 'Required public assets', 'STATUS:']:
    check(token in source, f'healthcheck exposes required readiness field: {token}')

php = os.environ.get('PHP_BINARY', 'php')
env = os.environ.copy()
env.update({
    'APP_ENV': 'production',
    'APP_DEBUG': 'false',
    'APP_LOG_ENABLED': 'false',
    'APP_HASH_KEY': '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
    'DB_DRIVER': 'mysql',
    'DB_HOST': 'example.invalid',
    'DB_PORT': '3306',
    'DB_NAME': 'rss_reader_example',
    'DB_USER': 'rss_reader_example',
    'DB_PASSWORD': 'dummy-password-not-real',
    'DB_TABLE_PREFIX': 'rss_',
})
proc = subprocess.run(
    [php, str(ROOT / 'tools/healthcheck.php')],
    cwd=ROOT,
    env=env,
    text=True,
    stdout=subprocess.PIPE,
    stderr=subprocess.STDOUT,
    timeout=30,
    check=False,
)
out = proc.stdout
check(proc.returncode in (0, 1), 'healthcheck returns a documented readiness status')
check('Build: Release M4-C / R1' in out, 'healthcheck reports current checkpoint')
check('DB driver: mysql' in out and 'DB table prefix: rss_' in out, 'healthcheck reports non-secret DB configuration')
check('Required public assets: present' in out, 'healthcheck verifies required public assets')
check('STATUS: CONFIGURATION READY' in out or 'STATUS: NOT READY' in out, 'healthcheck emits final status')
for secret in ['dummy-password-not-real', '0123456789abcdef0123456789abcdef']:
    check(secret not in out, 'healthcheck does not disclose secret values')
check('example.invalid' not in out and 'rss_reader_example' not in out, 'healthcheck does not disclose DB host or database name')

if not all(checks):
    print(out)
    sys.exit(1)
print(f'All {len(checks)} M4-C healthcheck contract checks passed.')
