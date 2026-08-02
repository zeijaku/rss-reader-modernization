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

readme = (ROOT / 'README.md').read_text(encoding='utf-8')
change = (ROOT / 'CHANGELOG.md').read_text(encoding='utf-8')
roadmap = (ROOT / 'docs/roadmap.md').read_text(encoding='utf-8')
gate = (ROOT / 'docs/release-gate-v1.0.0.md').read_text(encoding='utf-8')
impl = (ROOT / 'docs/m4-b-implementation.md').read_text(encoding='utf-8')
docs_index = (ROOT / 'docs/README.md').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')

check('**Current release:** `RSS Reader Modernization 1.0.0`' in readme, 'README checkpoint has progressed to M4-E')
check('| M4-B | README・CHANGELOG・License・Third-party notice | 完了 |' in readme, 'README marks M4-B complete')
check('- [x] M4-B README・CHANGELOG・License・Third-party notice整理' in roadmap, 'Roadmap marks M4-B complete')
check('## Release M4-B / R1 — 2026-08-02' in change, 'CHANGELOG retains M4-B entry')
check("APP_VERSION = '1.0.0'" in version, 'APP_VERSION has progressed to 1.0.0-rc1')
check("APP_VERSION_LABEL = 'RSS Reader Modernization 1.0.0'" in version, 'APP_VERSION_LABEL has progressed to 1.0.0-rc1')
check('| Third-party notice accuracy | PASS |' in gate, 'Third-party notice gate is PASS')
check('| Installation / Update / Recovery | PASS |' in gate, 'M4-C has progressed installation/update/recovery gate')
check('| Release ZIP / Notes / SHA-256 | PASS |' in gate, 'M4-E has progressed release package gate')
check('| GitHub hosted CI result | DISCLOSED |' in gate, 'final gate discloses hosted CI evidence state')
check('| Real environment evidence | DISCLOSED |' in gate, 'final gate discloses real environment evidence state')
check('| Version / Tag / GitHub Release | PASS / USER ACTION |' in gate, 'final gate separates completed version from user tag action')
check('未実施項目を架空のPASSへ変更していません' in gate, 'final gate does not fabricate manual evidence')
check('Frontend Runtime Assetは変更していない' in impl, 'implementation records unchanged runtime assets')
check('licenses/fontawesome-5.3.1-LICENSE.txt' in impl, 'deleted old license file is documented')
check('M4 release preparation' in docs_index and 'dependencies.md' in docs_index and 'm4-b-implementation.md' in docs_index and 'test-report-m4-b.md' in docs_index, 'documentation index links M4-B records')
check('License and third-party components' in readme, 'README has current license section')
check('jQuery 3.7.1' in readme and 'Font Awesome Free 6.7.2' in readme, 'README states current major vendored versions')
check("APP_VERSION = '1.0.0'" in version, 'application version is final 1.0.0')

# Local Markdown links in current release-facing documents.
link_re = re.compile(r'\[[^\]]+\]\(([^)]+)\)')
for rel in [
    'README.md',
    'THIRD_PARTY_NOTICES.md',
    'docs/README.md',
    'docs/dependencies.md',
    'docs/m4-b-implementation.md',
    'docs/test-report-m4-b.md',
    'docs/release-gate-v1.0.0.md',
    'docs/roadmap.md',
]:
    path = ROOT / rel
    text = path.read_text(encoding='utf-8')
    for target in link_re.findall(text):
        if target.startswith(('http://', 'https://', '#', 'mailto:')):
            continue
        clean = target.split('#', 1)[0]
        if not clean:
            continue
        check((path.parent / clean).resolve().exists(), f'local Markdown link resolves: {rel} -> {target}')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M4-B documentation checks passed.')
