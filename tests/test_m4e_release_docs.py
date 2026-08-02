#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


required = {
    'RELEASE_NOTES.md': ['M4-E preview', '正式Releaseではありません', 'Main changes', 'Known verification limits at M4-E', 'publishable=no'],
    'docs/release-package.md': ['deterministic', 'RELEASE_MANIFEST.sha256', 'preview', 'rc', 'final', 'allowlist'],
    'docs/tag-and-github-release.md': ['Annotated Tag', 'git push origin v1.0.0', 'git push --tags', 'v1.0.1', 'Force push'],
    'docs/m4-e-implementation.md': ['Application機能', 'publishable=no', 'M4-F', 'M4-G', 'GitHub hosted CI'],
    'docs/release-gate-v1.0.0.md': ['Release ZIP / Notes / SHA-256', 'Tag / GitHub Release procedure', 'PASS', 'Real environment / RC', 'HOLD'],
    'docs/release-artifact-inventory-v1.0.0.md': ['Runtime Release ZIP', 'Checkpoint ZIP', 'tests/', 'RELEASE_BUILD.txt', 'RELEASE_MANIFEST.sha256'],
}
texts: dict[str, str] = {}
for rel, terms in required.items():
    path = ROOT / rel
    check(path.is_file() and path.stat().st_size > 200, f'M4-E release document exists: {rel}')
    text = path.read_text(encoding='utf-8')
    texts[rel] = text
    for term in terms:
        check(term.lower() in text.lower(), f'M4-E release document contains required term: {rel} -> {term}')

readme = (ROOT / 'README.md').read_text(encoding='utf-8')
change = (ROOT / 'CHANGELOG.md').read_text(encoding='utf-8')
roadmap = (ROOT / 'docs/roadmap.md').read_text(encoding='utf-8')
versioning = (ROOT / 'docs/versioning.md').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
check('**Current checkpoint:** `Release M4-E / R1`' in readme, 'README checkpoint is M4-E')
check('| M4-E | 配布ZIP・Release Notes・SHA-256・Tag手順 | 完了 |' in readme, 'README marks M4-E complete')
check('Release package preview' in readme, 'README explains preview package')
check('- [x] M4-E 配布ZIP・Release Notes・SHA-256・Tag / Release手順' in roadmap, 'Roadmap marks M4-E complete')
check('## Release M4-E / R1 — 2026-08-02' in change, 'CHANGELOG contains M4-E entry')
check(change.find('## Release M4-E / R1') < change.find('## Release M4-D / R1'), 'M4-E changelog entry is before M4-D')
check("APP_VERSION = 'M4-E R1'" in version, 'APP_VERSION is M4-E R1')
check("APP_VERSION_LABEL = 'Release M4-E / R1'" in version, 'APP_VERSION_LABEL is M4-E R1')
check('Current: `Release M4-E / R1`' in versioning, 'Version policy current marker is M4-E')
check('M4-E完了はVersion 1.0.0 Release可を意味しない' in texts['docs/release-gate-v1.0.0.md'], 'Release gate does not claim final readiness')
check("const APP_VERSION = '1.0.0';" not in texts['RELEASE_NOTES.md'], 'Preview notes do not falsely present final source assignment')

# Local Markdown links in release-facing docs.
link_re = re.compile(r'\[[^\]]+\]\(([^)]+)\)')
for rel in ['README.md', 'RELEASE_NOTES.md', 'docs/README.md', 'docs/release-package.md',
            'docs/tag-and-github-release.md', 'docs/m4-e-implementation.md',
            'docs/release-gate-v1.0.0.md', 'docs/release-artifact-inventory-v1.0.0.md']:
    path = ROOT / rel
    for target in link_re.findall(path.read_text(encoding='utf-8')):
        if target.startswith(('http://', 'https://', '#', 'mailto:')):
            continue
        clean = target.split('#', 1)[0]
        if clean:
            check((path.parent / clean).resolve().exists(), f'local Markdown link resolves: {rel} -> {target}')

# Release docs must not invent a contact address or include high-signal secret material.
joined = '\n'.join(texts.values())
check(not re.search(r'\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b', joined, re.I), 'M4-E docs invent no contact email address')
for pattern in [r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----', r'\bAKIA[0-9A-Z]{16}\b', r'\bsk-[A-Za-z0-9_-]{20,}\b']:
    check(not re.search(pattern, joined), f'M4-E docs contain no secret pattern: {pattern}')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M4-E release documentation checks passed.')
