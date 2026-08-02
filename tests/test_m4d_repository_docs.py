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
    'SECURITY.md': ['Private Vulnerability Reporting', '公開Issueへ詳細を書かない', 'Safe testing', 'docs/security.md'],
    'CONTRIBUTING.md': ['1 purpose', 'bash tests/run.sh', 'config/local.php', 'SECURITY.md'],
    'docs/ci.md': ['contents: read', 'PHP 8.1', 'PHP 8.4', 'M4-F', 'continue-on-error'],
    'docs/github-publication.md': ['Description', 'Topics', 'Private vulnerability reporting', 'Force push', 'M4-E'],
    'docs/portfolio.md': ['Legacy', 'Secure Baseline', 'Screenshot', 'AI支援', 'Project owner'],
    'docs/m4-d-implementation.md': ['最小CI', 'Application機能を変更する工程ではない', 'GitHub hosted CI', 'HOLD'],
    '.github/ISSUE_TEMPLATE/bug_report.yml': ['Security問題', 'Version / Commit', 'Public data check'],
    '.github/ISSUE_TEMPLATE/config.yml': ['security/policy', 'blank_issues_enabled'],
}
texts: dict[str, str] = {}
for rel, terms in required.items():
    path = ROOT / rel
    check(path.is_file() and path.stat().st_size > 150, f'public repository document exists: {rel}')
    text = path.read_text(encoding='utf-8')
    texts[rel] = text
    for term in terms:
        check(term in text, f'public repository document contains required term: {rel} -> {term}')

security = texts['SECURITY.md']
portfolio = texts['docs/portfolio.md']
ci = texts['docs/ci.md']
github = texts['docs/github-publication.md']
check('Credential、個人情報、実URL' in security, 'Security reporting forbids public sensitive data')
check('第三者のFeed提供元' in security, 'Security scope separates third-party systems')
check('大規模Trafficでの性能を実証したとは書かない' in portfolio, 'Portfolio avoids unsupported scale claims')
check('正式なVersion 1.0.0' in portfolio and 'M4-G完了後' in portfolio, 'Portfolio defers final release claims')
check('GitHub hosted runner' in ci and 'Local static test' in ci, 'CI documentation separates local and hosted evidence')
check('Tag `v1.0.0` とGitHub Releaseを作りません' in github, 'GitHub publication guide does not release early')

readme = (ROOT / 'README.md').read_text(encoding='utf-8')
change = (ROOT / 'CHANGELOG.md').read_text(encoding='utf-8')
roadmap = (ROOT / 'docs/roadmap.md').read_text(encoding='utf-8')
gate = (ROOT / 'docs/release-gate-v1.0.0.md').read_text(encoding='utf-8')
versioning = (ROOT / 'docs/versioning.md').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
check('**Current release:** `RSS Reader Modernization 1.0.0`' in readme, 'README checkpoint is M4-D')
check('actions/workflows/ci.yml/badge.svg' in readme, 'README includes repository CI badge')
check('| M4-D | GitHub公開状態・Repository・Portfolio・最小CI | 完了 |' in readme, 'README marks M4-D complete')
check('- [x] M4-D GitHub公開状態・Repository構成・Portfolio説明・最小CI' in roadmap, 'Roadmap marks M4-D complete')
check('## Release M4-D / R1 — 2026-08-02' in change, 'CHANGELOG contains M4-D entry')
check("APP_VERSION = '1.0.0'" in version and "APP_VERSION_LABEL = 'RSS Reader Modernization 1.0.0'" in version, 'visible version has advanced to 1.0.0-rc1')
check('Current: `RSS Reader Modernization 1.0.0`' in versioning, 'Version policy current marker is M4-E')
check('| GitHub / Portfolio / CI definition | PASS |' in gate, 'M4-D definition gate is PASS')
check('| GitHub hosted CI result | DISCLOSED |' in gate, 'GitHub hosted result remains disclosed until user verification')
check('未実施項目を架空のPASSへ変更していません' in gate, 'final gate preserves M4-D external verification honesty')

# Local links in release-facing Markdown.
link_re = re.compile(r'\[[^\]]+\]\(([^)]+)\)')
link_docs = ['README.md', 'SECURITY.md', 'CONTRIBUTING.md', 'docs/README.md', 'docs/ci.md',
             'docs/github-publication.md', 'docs/portfolio.md', 'docs/m4-d-implementation.md',
             'docs/release-gate-v1.0.0.md', 'docs/roadmap.md']
for rel in link_docs:
    path = ROOT / rel
    for target in link_re.findall(path.read_text(encoding='utf-8')):
        if target.startswith(('http://', 'https://', '#', 'mailto:')):
            continue
        clean = target.split('#', 1)[0]
        if clean:
            check((path.parent / clean).resolve().exists(), f'local Markdown link resolves: {rel} -> {target}')

# New public docs must not invent a direct email address.
new_docs = '\n'.join(texts.values())
check(not re.search(r'\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b', new_docs, re.I), 'M4-D docs invent no contact email address')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M4-D repository documentation checks passed.')
