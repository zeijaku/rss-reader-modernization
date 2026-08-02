#!/usr/bin/env python3
from __future__ import annotations

import json
from pathlib import Path
import re
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


probe = ROOT / 'tools/m4f_environment_probe.php'
check(probe.is_file(), 'M4-F PHP environment probe exists')
result = subprocess.run(['php', str(probe)], cwd=ROOT, text=True, capture_output=True)
check(result.returncode == 0, 'environment probe collection exits zero')
try:
    data = json.loads(result.stdout)
    parsed = True
except json.JSONDecodeError:
    data = {}
    parsed = False
check(parsed, 'environment probe emits valid JSON')
check(data.get('schema_version') == 1, 'environment probe schema version is 1')
check(data.get('checkpoint') == '1.0.0', 'environment probe records final checkpoint')
check(data.get('label') == 'RSS Reader Modernization 1.0.0', 'environment probe records final label')
check(data.get('status') in {'PASS', 'HOLD'}, 'environment probe status is PASS or HOLD')
php = data.get('php', {})
check(isinstance(php.get('version'), str) and bool(php.get('version')), 'environment probe records PHP version')
check(php.get('sapi') == 'cli', 'environment probe runs as CLI')
required = data.get('required_extensions', {})
for extension in ['pdo', 'pdo_mysql', 'curl', 'simplexml', 'mbstring']:
    check(extension in required and isinstance(required[extension], bool), f'environment probe reports extension: {extension}')
check(isinstance(data.get('pdo_drivers'), list), 'environment probe reports PDO drivers')
for directory in ['var/session', 'var/log', 'var/cache/feed', 'var/db-migration', 'var/security/login-throttle']:
    state = data.get('runtime_directories', {}).get(directory, {})
    check(isinstance(state.get('exists'), bool), f'environment probe reports directory existence: {directory}')
    check(isinstance(state.get('writable'), bool), f'environment probe reports directory writability: {directory}')

joined = result.stdout + result.stderr
for pattern in [
    r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----',
    r'\bAKIA[0-9A-Z]{16}\b',
    r'\bsk-[A-Za-z0-9_-]{20,}\b',
    r'(?i)DB_PASSWORD\s*[=:]',
    r'(?i)APP_HASH_KEY\s*[=:]',
    r'(?i)Authorization:\s*(?:Basic|Bearer)',
]:
    check(not re.search(pattern, joined), f'environment probe output contains no secret pattern: {pattern}')

ready = subprocess.run(['php', str(probe), '--require-ready'], cwd=ROOT, text=True, capture_output=True)
expected_ready = 0 if data.get('status') == 'PASS' else 2
check(ready.returncode == expected_ready, 'environment probe require-ready exit matches reported status')

if not all(checks):
    print(result.stderr, file=sys.stderr)
    sys.exit(1)
print(f'All {len(checks)} M4-F environment probe checks passed.')
