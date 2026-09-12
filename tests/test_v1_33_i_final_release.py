#!/usr/bin/env python3
from pathlib import Path
import re

from version_contract_utils import read_app_version_constants

root = Path(__file__).resolve().parents[1]

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
version_constants = read_app_version_constants(root)
current_version = version_constants.get('APP_VERSION', '')
current_label = version_constants.get('APP_VERSION_LABEL', '')
current_revision = version_constants.get('APP_ASSET_REVISION', '')
version_match = re.fullmatch(r'(\d+)\.(\d+)\.(\d+)(?:-(?:rc\d+|dev\.\d+))?', current_version)
is_formal_release = '-' not in current_version

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


check(version_match is not None and tuple(map(int, version_match.groups()[:3])) >= (1, 33, 1),
      'V1.33-I regression contract runs on V1.33.1 or later')
check(current_label == f'RSS Reader Modernization {current_version}',
      'visible application label follows the current application version')
check(bool(current_revision) and current_revision == current_version,
      'current assets use the current immutable checkpoint/release cache key')
check('var ASSET_RETRY_LIMIT = 1;' in loader
      and 'var ASSET_RETRY_DELAY_MS = 600;' in loader,
      'accepted static asset retry count and delay remain bounded')
check('scriptQueue.push(src);' in loader and 'startScriptQueue();' in loader,
      'accepted ordered JavaScript queue remains active')
check('var STYLE_BATCH_SIZE = 4;' in loader and 'startStyleQueue();' in loader,
      'accepted stylesheet batches remain bounded and declaration-ordered')
if is_formal_release:
    check(f'# RSS Reader Modernization {current_version}' in notes
          and '正式Releaseではありません' not in notes,
          'release notes describe the current formal release without an RC warning')
    check(f'Stable release:** `RSS Reader Modernization {current_version}`' in readme
          and f'Release tag: `v{current_version}`' in readme,
          'README identifies the current stable source and intended tag')
    check(changelog.startswith(f'## {current_version} - '),
          'CHANGELOG starts with the current formal release entry')
    check(release_request == current_version,
          'browser fallback release request matches the current application version')
else:
    check(f'Stable release:** `RSS Reader Modernization {current_version}`' not in readme,
          'development checkpoint does not replace README stable release metadata')
    check(release_request != current_version,
          'development checkpoint does not replace the formal release request')
check('Verification limits' in notes and 'PHP 8.1' in notes and 'PHP 8.4' in notes,
      'release notes disclose runtime verification limits and final gates')
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
