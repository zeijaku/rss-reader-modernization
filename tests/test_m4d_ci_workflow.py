#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
WORKFLOW = ROOT / '.github/workflows/ci.yml'
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


check(WORKFLOW.is_file(), 'CI workflow exists')
text = WORKFLOW.read_text(encoding='utf-8')
check(len(text) > 800, 'CI workflow is substantive')
check('\t' not in text, 'CI workflow uses no tab indentation')
check(all((len(line) - len(line.lstrip(' '))) % 2 == 0 for line in text.splitlines() if line.strip()), 'CI workflow indentation uses two-space levels')
check(text.count('${{') == text.count('}}'), 'CI workflow expression delimiters are balanced')
check(re.search(r'^name:\s+CI$', text, re.M) is not None, 'CI workflow has the expected name')

for event in ['push:', 'pull_request:', 'workflow_dispatch:']:
    check(re.search(rf'^  {re.escape(event)}$', text, re.M) is not None, f'CI trigger includes {event[:-1]}')
check('pull_request_target:' not in text, 'CI does not use pull_request_target')
check(re.search(r'^permissions:\n  contents: read$', text, re.M) is not None, 'Workflow token is limited to contents: read')
check('write-all' not in text and not re.search(r':\s*write\s*$', text, re.M), 'Workflow requests no write permission')
check('cancel-in-progress: true' in text, 'Superseded CI runs are cancelled')
check(text.count('\n  regression:\n') == 1, 'CI has one focused regression job')
check('runs-on: ubuntu-latest' in text, 'CI uses ubuntu-latest')
check('timeout-minutes: 25' in text, 'CI job has a bounded timeout')
check("- '8.1'" in text and "- '8.4'" in text, 'CI covers PHP 8.1 and PHP 8.4')
check('fail-fast: false' in text, 'PHP matrix reports both results')

for action in ['actions/checkout@v4', 'shivammathur/setup-php@v2', 'actions/setup-python@v5', 'actions/setup-node@v4']:
    check(f'uses: {action}' in text, f'CI uses expected action: {action}')
check('persist-credentials: false' in text, 'Checkout does not persist credentials')
for extension in ['curl', 'mbstring', 'pdo_mysql', 'pdo_sqlite', 'simplexml']:
    check(extension in text, f'CI enables PHP extension: {extension}')
check('coverage: none' in text, 'CI disables unused coverage instrumentation')
check('bash tests/run.sh' in text, 'CI executes the full regression runner')
check('php -v' in text and 'python --version' in text and 'node --version' in text, 'CI records runtime versions')

lower = text.lower()
for forbidden in ['secrets.', 'continue-on-error', 'pull_request_target', 'actions/upload-artifact', 'gh release', 'git push', 'deploy']:
    check(forbidden not in lower, f'CI excludes privileged or masking behavior: {forbidden}')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M4-D CI workflow checks passed.')
