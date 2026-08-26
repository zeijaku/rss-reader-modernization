#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
checks = []


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def check(ok: bool, message: str) -> None:
    checks.append(bool(ok))
    print(('PASS' if ok else 'FAIL') + ': ' + message)


version = text('app/version.php')
calendar = text('public/js/calendar.js')
readme = text('README.md')
changelog = text('CHANGELOG.md')
notes = text('RELEASE_NOTES.md')
install = text('docs/installation.md')
update = text('docs/update.md')
tag_doc = text('docs/tag-and-github-release.md')
release_doc = text('docs/v1-22-0-release.md')
ci = text('.github/workflows/ci.yml')

check("const APP_VERSION = '1.22.0';" in version, 'APP_VERSION is final 1.22.0')
check("const APP_VERSION_LABEL = 'RSS Reader Modernization 1.22.0';" in version, 'APP_VERSION_LABEL is final 1.22.0')
check("const APP_ASSET_REVISION = '1.22.0';" in version, 'APP_ASSET_REVISION is final 1.22.0')
check('1.22.0-d' not in version + calendar, 'V1.22-D checkpoint asset key is absent from final runtime loader')
for asset in ('feed-health.js', 'rss-rule-display.js', 'drawer-categories.js', 'mail-widget.css'):
    check(f'{asset}?v=1.22.0' in calendar, f'{asset} uses final asset revision')

check('**Stable release:** `RSS Reader Modernization 1.22.0`' in readme, 'README names 1.22.0 as stable')
check('Release tag: `v1.22.0`' in readme, 'README names v1.22.0')
check('## 1.22.0 - 2026-08-26' in changelog, 'CHANGELOG contains 1.22.0 entry')
check('# RSS Reader Modernization 1.22.0' in notes, 'Release Notes target 1.22.0')
check('Verification limits' in notes, 'Release Notes disclose verification limits')
check('v1.22.0' in tag_doc and 'release/v1.22.0-final' in tag_doc, 'Tag procedure targets formal release branch')
check('Version 1.21.0 plus V1.22-A/B/C/D' in release_doc, 'Final release document records baseline and included checkpoints')

migrations = (
    '014_v1_22_opml_feed_metadata.sql',
    '015_v1_22_feed_health.sql',
    '016_v1_22_rss_rules.sql',
)
for name in migrations:
    check((ROOT / 'database/migrations' / name).is_file(), f'migration exists: {name}')
    check(name in install, f'installation documents migration: {name}')
    check(name in update, f'update documents migration: {name}')
check('次の19 table' in install, 'fresh installation documents 19 tables')
check('014→015→016' in update, 'existing DB update requires numeric migration order')
check('Do not rerun `database/schema.sql` against an existing database.' in notes, 'release notes warn against rerunning schema on existing DB')

for tool in (
    'tools/build_release_package.py',
    'tools/verify_release_package.py',
    'tools/build_complete_package.py',
    'tools/verify_complete_package.py',
):
    body = text(tool)
    check('1.22.0' in body, f'{tool} targets Version 1.22.0')
check("r'1\\.22\\.0-rc[1-9][0-9]*'" in text('tools/build_release_package.py'), 'runtime builder accepts V1.22 RC version format')
check("r'1\\.22\\.0-rc[1-9][0-9]*'" in text('tools/verify_release_package.py'), 'runtime verifier validates V1.22 RC version format')
check("'.github/workflows/v1.22.0-release.yml'" in text('tools/verify_complete_package.py'), 'complete source package requires V1.22 release workflow')

check('bash tests/run-v122e.sh' in ci, 'main CI includes V1.22 final gate after formalization')
for gate in ('run-v122b.sh', 'run-v122c.sh', 'run-v122d.sh'):
    check(gate in ci, f'CI retains {gate}')
check(not (ROOT / 'config/local.php').exists(), 'repository does not contain config/local.php')

for migration, marker in (
    ('014_v1_22_opml_feed_metadata.sql', 'content.content_owner'),
    ('015_v1_22_feed_health.sql', 'content.content_owner'),
    ('016_v1_22_rss_rules.sql', 'Rule ownership'),
):
    check(marker in text('database/migrations/' + migration), f'{migration} documents derived ownership')

passed = sum(checks)
failed = len(checks) - passed
print(f'RESULT: PASS {passed} / FAIL {failed}')
sys.exit(1 if failed else 0)
