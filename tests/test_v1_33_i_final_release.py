#!/usr/bin/env python3
from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]

version = (root / 'app/version.php').read_text(encoding='utf-8')
loader = (root / 'public/js/calendar.js').read_text(encoding='utf-8')
readme = (root / 'README.md').read_text(encoding='utf-8')
changelog = (root / 'CHANGELOG.md').read_text(encoding='utf-8')
notes = (root / 'RELEASE_NOTES.md').read_text(encoding='utf-8')
installation = (root / 'docs/installation.md').read_text(encoding='utf-8')
future = (root / 'docs/v1-33-future-calendar.md').read_text(encoding='utf-8')
finalization = (root / 'docs/v1-33-i-final-release.md').read_text(encoding='utf-8')
migration = (root / 'database/migrations/025_v1_33_calendar_event_exception.sql').read_text(encoding='utf-8')
schema = (root / 'database/schema.sql').read_text(encoding='utf-8')
complete_builder = (root / 'tools/build_complete_package.py').read_text(encoding='utf-8')
release_request = (root / '.github/release-request.txt').read_text(encoding='utf-8').strip()

passed = 0
failed = 0


def check(condition: bool, message: str) -> None:
    global passed, failed
    if condition:
        passed += 1
        print(f'PASS: {message}')
    else:
        failed += 1
        print(f'FAIL: {message}')


check("const APP_VERSION = '1.33.0';" in version,
      'formal application version is explicit')
check("const APP_VERSION_LABEL = 'RSS Reader Modernization 1.33.0';" in version,
      'formal visible label matches package policy')
check("const APP_ASSET_REVISION = '1.33.0';" in version,
      'formal assets use the immutable release cache key')
check('var ASSET_RETRY_LIMIT = 1;' in loader
      and 'var ASSET_RETRY_DELAY_MS = 600;' in loader,
      'accepted static asset retry count and delay remain bounded')
check('scriptQueue.push(src);' in loader and 'startScriptQueue();' in loader,
      'accepted ordered JavaScript queue remains active')
check('var STYLE_BATCH_SIZE = 4;' in loader and 'startStyleQueue();' in loader,
      'accepted stylesheet batches remain bounded and declaration-ordered')
check('# RSS Reader Modernization 1.33.0' in notes
      and '正式Releaseではありません' not in notes,
      'release notes describe the formal release without an RC warning')
check('Verification limits' in notes and 'PHP 8.1' in notes and 'PHP 8.4' in notes,
      'release notes disclose runtime verification limits and final gates')
check('Stable release:** `RSS Reader Modernization 1.33.0`' in readme
      and 'Release tag: `v1.33.0`' in readme,
      'README identifies V1.33 as the stable source and intended tag')
check(changelog.startswith('## 1.33.0 - 2026-09-09'),
      'CHANGELOG starts with the formal V1.33 entry')
check('日程のコピー' in future and '日程のDrag & Drop' in future,
      'future Calendar copy and Drag & Drop requests remain recorded')
check('V1.33対象外' in future and 'DB変更は現時点では不要' in future,
      'future requests remain deferred with a provisional DB assessment')
check('## 本番確認手順' in finalization and 'Error log' in finalization,
      'V1.33-I provides an ordered production verification checklist')
check('025_v1_33_calendar_event_exception.sql' in finalization,
      'V1.33-I documents the required additive migration check')
check('CREATE TABLE' in migration and 'DROP ' not in migration.upper()
      and 'TRUNCATE ' not in migration.upper() and 'ALTER TABLE' not in migration.upper(),
      'Migration 025 is additive and does not alter or destroy existing tables')
check('calendar_event_exception' in schema and 'calendar_event_exception' in installation,
      'fresh-install schema and installation guide include the exception table')
check('次の26 table' in installation and 'rss_calendar_event_exception' in installation,
      'fresh-install table inventory remains updated for V1.33')
check("'deliverables'" in complete_builder,
      'Complete Source builder excludes local checkpoint and final deliverables')
check(bool(re.fullmatch(r'\d+\.\d+\.\d+', release_request)),
      'source finalization carries a formal semantic-version release request; workflow independently validates the exact release match')

stale = []
for base in (root / 'app', root / 'public'):
    for path in base.rglob('*'):
        if not path.is_file():
            continue
        try:
            text = path.read_text(encoding='utf-8')
        except UnicodeDecodeError:
            continue
        if any(marker in text for marker in (
            '1.33.0-dev.6', '1.33.0-rc1', '1.33.0-rc2',
            '1.33.0-RC1', '1.33.0-RC2',
        )):
            stale.append(path.relative_to(root).as_posix())
check(not stale, 'production source contains no stale V1.33 checkpoint marker')

print(f'RESULT: PASS {passed} / FAIL {failed} / SKIP 0')
raise SystemExit(0 if failed == 0 else 1)
