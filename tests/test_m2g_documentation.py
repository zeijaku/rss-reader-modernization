#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
checks = 0


def check(condition: bool, message: str) -> None:
    global checks
    checks += 1
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        raise AssertionError(message)


primary = [
    ROOT / 'README.md', ROOT / 'CHANGELOG.md', ROOT / 'CHECKLIST_FOR_USER.md',
    ROOT / 'docs/roadmap.md', ROOT / 'docs/versioning.md',
    ROOT / 'docs/m2-g-implementation.md', ROOT / 'docs/m2-completion-summary.md',
    ROOT / 'docs/test-report-m2-g.md',
]
for path in primary:
    check(path.is_file() and path.stat().st_size > 300, f'primary M2-G document is present: {path.relative_to(ROOT)}')

readme = (ROOT / 'README.md').read_text(encoding='utf-8')
roadmap = (ROOT / 'docs/roadmap.md').read_text(encoding='utf-8')
summary = (ROOT / 'docs/m2-completion-summary.md').read_text(encoding='utf-8')
checklist = (ROOT / 'CHECKLIST_FOR_USER.md').read_text(encoding='utf-8')
report = (ROOT / 'docs/test-report-m2-g.md').read_text(encoding='utf-8')

check('M2-G | 最終回帰・Documentation | 未着手' not in readme, 'README has no stale M2-G pending status')
check('- [ ] M2-G 最終回帰・Documentation' not in roadmap, 'Roadmap has no stale M2-G checkbox')
check('Frontend M2-F / R1`' not in readme.splitlines()[2], 'README headline no longer reports M2-F')
check('__PASS__' not in report and '__FAIL__' not in report and '__SKIP__' not in report, 'M2-G test report has no result placeholder')

for width in ['320px', '375px', '768px', '992px', '1280px']:
    check(width in summary and width in checklist, f'manual width is documented: {width}')
for theme in ['Normal', 'Yeti', 'Minty', 'Flatly', 'Journal', 'Sketchy', 'Solar', 'Slate']:
    check(theme in summary and theme in checklist, f'manual theme is documented: {theme}')
for feature in ['Login', 'Logout', '4タブ', 'Feed', 'Stock', 'Settings', 'Drawer', 'Modal', 'Popover', 'Page Top']:
    check(feature in summary and feature in checklist, f'manual feature is documented: {feature}')

check('DB migration不要' in checklist, 'Checklist states DB migration is unnecessary')
check('config/local.php' in checklist, 'Checklist preserves private configuration guidance')
check('cleanup helper再実行は不要' in checklist, 'Checklist explains M2-G needs no cleanup rerun')
check('Chromium headless' in report and 'SKIP' in report, 'Browser smoke limitation is recorded')
check('Bootstrap 5' in summary, 'deferred Bootstrap major migration is recorded')
check('npm' in summary and 'Vite' in summary, 'deferred build pipeline is recorded')

md_link = re.compile(r'\[[^\]]+\]\(([^)]+)\)')
link_docs = primary + [ROOT / f'docs/m2-{phase}-implementation.md' for phase in 'abcdefg'] + [ROOT / f'docs/test-report-m2-{phase}.md' for phase in 'abcdefg']
for doc in link_docs:
    text = doc.read_text(encoding='utf-8')
    for target in md_link.findall(text):
        if target.startswith(('http://', 'https://', '#', 'mailto:')):
            continue
        clean = target.split('#', 1)[0]
        if not clean:
            continue
        resolved = (doc.parent / clean).resolve()
        check(resolved.exists(), f'local Markdown link resolves: {doc.relative_to(ROOT)} -> {target}')

secret_patterns = [
    re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'),
    re.compile(r'\bAKIA[0-9A-Z]{16}\b'),
    re.compile(r'\bsk-[A-Za-z0-9_-]{20,}\b'),
]
for doc in primary + [ROOT / 'docs/m2-completion-summary.md']:
    text = doc.read_text(encoding='utf-8')
    check(all(not pattern.search(text) for pattern in secret_patterns), f'no high-signal secret pattern in {doc.relative_to(ROOT)}')

print(f'All {checks} M2-G documentation checks passed.')
