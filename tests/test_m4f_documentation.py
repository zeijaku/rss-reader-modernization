#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import json
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


required = {
    'docs/m4-f-validation.md': [
        '1.0.0-rc1', 'RELEASE_CANDIDATE', 'publishable=no',
        'm4f_environment_probe.php', 'm4f_evidence_gate.py', '--require-pass',
        'MySQL', 'RSS 2.0', 'RSS 1.0', 'Atom', '320px', '1280px',
        'Backup', 'Restore', 'Rollback', 'Credential', 'HOLD',
    ],
    'docs/m4-f-implementation.md': [
        'Release Candidate', 'Application機能', 'DB schema', 'publishable=no',
        'pdo_mysql', 'SimpleXML', 'M4-G', 'DB migration',
    ],
    'docs/release-gate-v1.0.0.md': [
        'M4-F', 'Release Candidate', 'Real environment evidence', 'HOLD', 'M4-G',
    ],
    'RELEASE_NOTES.md': [
        'M4-F release candidate', '正式Releaseではありません', '1.0.0-rc1',
        'Known verification limits at M4-F',
    ],
}
texts: dict[str, str] = {}
for rel, terms in required.items():
    path = ROOT / rel
    check(path.is_file() and path.stat().st_size > 200, f'M4-F document exists: {rel}')
    text = path.read_text(encoding='utf-8')
    texts[rel] = text
    for term in terms:
        check(term.lower() in text.lower(), f'M4-F document contains required term: {rel} -> {term}')

readme = (ROOT / 'README.md').read_text(encoding='utf-8')
change = (ROOT / 'CHANGELOG.md').read_text(encoding='utf-8')
roadmap = (ROOT / 'docs/roadmap.md').read_text(encoding='utf-8')
versioning = (ROOT / 'docs/versioning.md').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
check('**Current checkpoint:** `RSS Reader Modernization 1.0.0-RC1`' in readme, 'README checkpoint is RC1')
check('| M4-F | Release Candidate全回帰・実環境確認 | RC作成済み / 実環境HOLD |' in readme, 'README records M4-F external HOLD')
check('## RSS Reader Modernization 1.0.0-RC1 — 2026-08-02' in change, 'CHANGELOG contains M4-F RC entry')
check(change.find('## RSS Reader Modernization 1.0.0-RC1') < change.find('## Release M4-E / R1'), 'M4-F changelog entry is before M4-E')
check("APP_VERSION = '1.0.0-rc1'" in version, 'APP_VERSION is exact RC1')
check("APP_VERSION_LABEL = 'RSS Reader Modernization 1.0.0-RC1'" in version, 'APP_VERSION_LABEL is exact RC1')
check('Current: `RSS Reader Modernization 1.0.0-RC1`' in versioning, 'Version policy current marker is RC1')
check('- [ ] M4-F Version 1.0.0候補版の全回帰・実環境確認（RC作成済み、実環境Evidence待ち）' in roadmap, 'Roadmap keeps M4-F open until real evidence passes')

validation = json.loads((ROOT / 'docs/m4-f-validation-template.json').read_text(encoding='utf-8'))
check(validation.get('overall_status') == 'HOLD', 'committed validation template remains HOLD')
check(all(item.get('status') == 'PENDING' for item in validation.get('checks', [])), 'committed validation template contains no fabricated PASS')

link_re = re.compile(r'\[[^\]]+\]\(([^)]+)\)')
for rel in ['README.md', 'RELEASE_NOTES.md', 'docs/README.md', 'docs/m4-f-validation.md',
            'docs/m4-f-implementation.md', 'docs/release-package.md',
            'docs/release-gate-v1.0.0.md']:
    path = ROOT / rel
    for target in link_re.findall(path.read_text(encoding='utf-8')):
        if target.startswith(('http://', 'https://', '#', 'mailto:')):
            continue
        clean = target.split('#', 1)[0]
        if clean:
            check((path.parent / clean).resolve().exists(), f'local Markdown link resolves: {rel} -> {target}')

joined = '\n'.join(texts.values())
for pattern in [r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----', r'\bAKIA[0-9A-Z]{16}\b', r'\bsk-[A-Za-z0-9_-]{20,}\b']:
    check(not re.search(pattern, joined), f'M4-F docs contain no secret pattern: {pattern}')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M4-F documentation checks passed.')
