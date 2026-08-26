#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
checks = []

def text(path):
    return (ROOT / path).read_text(encoding='utf-8')

def check(ok, message):
    ok = bool(ok)
    checks.append(ok)
    print(('PASS' if ok else 'FAIL') + ': ' + message)

version = text('app/version.php')
readme = text('README.md')
changelog = text('CHANGELOG.md')
notes = text('RELEASE_NOTES.md')
ci = text('.github/workflows/ci.yml')

check("APP_VERSION = '1.22.0'" in version, 'APP_VERSION is final 1.22.0')
check("APP_VERSION_LABEL = 'RSS Reader Modernization 1.22.0'" in version, 'APP_VERSION_LABEL is final 1.22.0')
check("APP_ASSET_REVISION = '1.22.0'" in version, 'asset revision is final 1.22.0')
check('**Stable release:** `RSS Reader Modernization 1.22.0`' in readme, 'README stable release is 1.22.0')
check('Release tag: `v1.22.0`' in readme, 'README release tag is v1.22.0')
check('## 1.22.0 - 2026-08-26' in changelog, 'CHANGELOG contains 1.22.0')
check('# RSS Reader Modernization 1.22.0' in notes, 'release notes target 1.22.0')
check('Verification limits' in notes, 'release notes disclose verification limits')

migrations = [
    '014_v1_22_opml_feed_metadata.sql',
    '015_v1_22_feed_health.sql',
    '016_v1_22_rss_rules.sql',
]
for name in migrations:
    check((ROOT / 'database/migrations' / name).is_file(), f'migration exists: {name}')
    check(name in notes, f'release notes list migration: {name}')

for tool in (
    'tools/build_release_package.py',
    'tools/verify_release_package.py',
    'tools/build_complete_package.py',
    'tools/verify_complete_package.py',
):
    check('1.22.0' in text(tool), f'{tool} targets Version 1.22.0')

check('bash tests/run-v122e.sh' in ci, 'CI includes V1.22.0 final gate')
check(not (ROOT / 'config/local.php').exists(), 'repository contains no config/local.php')
check(not re.findall(r"1\.22\.0-[abcd](?![A-Za-z0-9])", version), 'formal version marker contains no checkpoint suffix')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed}')
sys.exit(1 if failed else 0)
