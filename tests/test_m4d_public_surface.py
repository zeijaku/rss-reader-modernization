#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import hashlib
import json
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


# M2-G critical runtime contract remains unchanged.
baseline = json.loads((ROOT / 'docs/m4-a-baseline.json').read_text(encoding='utf-8'))
for rel, expected in baseline['critical_file_sha256'].items():
    actual = hashlib.sha256((ROOT / rel).read_bytes()).hexdigest()
    check(actual == expected, f'M2-G critical runtime file remains unchanged: {rel}')

for rel in ['.github/workflows/ci.yml', '.github/ISSUE_TEMPLATE/bug_report.yml',
            '.github/ISSUE_TEMPLATE/config.yml', 'SECURITY.md', 'CONTRIBUTING.md']:
    check((ROOT / rel).is_file(), f'GitHub public surface file exists: {rel}')

ignore = (ROOT / '.gitignore').read_text(encoding='utf-8')
for token in ['/config/local.php', '*.zip', '*.sql', '*.db', '*.log', '/var/*', '.env']:
    check(token in ignore, f'.gitignore protects public repository data: {token}')

forbidden_exact = ['config/local.php', '.env', 'rss.sql', 'rss.zip']
check(not any((ROOT / rel).exists() for rel in forbidden_exact), 'Public tree excludes exact private and Legacy files')
for pattern in ['*.sqlite', '*.sqlite3', '*.db', '*.dump', '*.bak', '*.backup', '*.log', '*.pid', '*.zip']:
    hits = [p for p in ROOT.rglob(pattern) if p.is_file()]
    check(not hits, f'Public tree excludes file pattern: {pattern}')

for runtime in ['var/session', 'var/log', 'var/cache/feed', 'var/db-migration', 'var/security/login-throttle']:
    directory = ROOT / runtime
    generated = [p for p in directory.iterdir() if p.is_file() and p.name != '.gitkeep'] if directory.exists() else []
    check(not generated, f'Runtime directory contains no generated public data: {runtime}')

workflow = (ROOT / '.github/workflows/ci.yml').read_text(encoding='utf-8').lower()
check('secrets.' not in workflow, 'CI does not reference repository secrets')
check('permissions:\n  contents: read' in workflow, 'CI uses read-only token permission')
check('services:' not in workflow and 'mysql:' not in workflow, 'M4-D CI does not claim real MySQL integration')
check('bash tests/run.sh' in workflow, 'CI uses maintained local regression runner')

issue_config = (ROOT / '.github/ISSUE_TEMPLATE/config.yml').read_text(encoding='utf-8')
check('https://github.com/zeijaku/rss-reader-modernization/security/policy' in issue_config, 'Issue template links to repository security policy')

# High-signal secret scan over new public metadata and application-facing text.
patterns = [
    re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'),
    re.compile(r'\bAKIA[0-9A-Z]{16}\b'),
    re.compile(r'\bsk-[A-Za-z0-9_-]{20,}\b'),
]
hits: list[str] = []
for rel in ['.github', 'SECURITY.md', 'CONTRIBUTING.md', 'docs/ci.md', 'docs/github-publication.md',
            'docs/portfolio.md', 'docs/m4-d-implementation.md']:
    path = ROOT / rel
    paths = [path] if path.is_file() else [p for p in path.rglob('*') if p.is_file()]
    for item in paths:
        text = item.read_text(encoding='utf-8')
        if any(pattern.search(text) for pattern in patterns):
            hits.append(item.relative_to(ROOT).as_posix())
check(not hits, 'M4-D public metadata contains no high-signal secret pattern')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M4-D public surface checks passed.')
