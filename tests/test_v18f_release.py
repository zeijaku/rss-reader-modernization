from __future__ import annotations
from pathlib import Path
import re

ROOT=Path(__file__).resolve().parents[1]
checks=[]
def check(condition: bool, message: str) -> None:
    checks.append(bool(condition)); print(('PASS' if condition else 'FAIL')+': '+message)

def text(rel: str) -> str:
    return (ROOT/rel).read_text(encoding='utf-8')

version=text('app/version.php'); readme=text('README.md'); notes=text('RELEASE_NOTES.md')
changelog=text('CHANGELOG.md'); roadmap=text('docs/roadmap.md'); versioning=text('docs/versioning.md')
update=text('docs/update.md'); apply=text('APPLY_NOTE.md'); checklist=text('CHECKLIST_FOR_USER.md')

check("const APP_VERSION = '1.8.0';" in version, 'APP_VERSION is exact 1.8.0')
check("const APP_VERSION_LABEL = 'RSS Reader Modernization 1.8.0';" in version, 'APP_VERSION_LABEL is exact 1.8.0')
check('**Stable release:** `RSS Reader Modernization 1.8.0`' in readme, 'README identifies Version 1.8.0 as stable')
check('Release tag: `v1.8.0`' in readme, 'README targets v1.8.0 tag')
check('## Version 1.8 progress' in readme and 'V1.8-F' in readme, 'README contains completed V1.8 progress')
check('# RSS Reader Modernization 1.8.0 Release Notes' in notes[:100], 'Release Notes target Version 1.8.0')
check('## Verification limits' in notes, 'Release Notes disclose verification limits')
check(changelog.startswith('## RSS Reader Modernization 1.8.0'), 'CHANGELOG starts with Version 1.8.0')
check('- [x] V1.8-F Full回帰・Version 1.8.0 Release' in roadmap, 'Roadmap marks V1.8-F complete')
check('Git Tag: `v1.8.0`' in versioning, 'Version policy targets v1.8.0')
check('## Version 1.7.0からVersion 1.8.0' in update, 'Update guide contains Version 1.8.0 procedure')
check('# Version 1.8.0 適用手順' in apply, 'generic apply note targets Version 1.8.0')
check('# Version 1.8.0 確認Checklist' in checklist, 'generic checklist targets Version 1.8.0')

schema=text('database/schema.sql')
check(all(token in schema for token in ['`stock_id` INT NOT NULL AUTO_INCREMENT','`stock_flag` INT NOT NULL DEFAULT 0','`stock_owner` INT UNSIGNED NOT NULL','`stock_data` VARCHAR(512)','`stock_title` VARCHAR(128)','KEY `idx_stock_owner_flag_id`']), 'content_stock keeps Version 1.7-compatible structure/index')
v18_sql=[p for p in (ROOT/'database').rglob('*') if p.is_file() and re.search(r'v1[_-]?8', p.name, re.I)]
check(not v18_sql, 'Version 1.8 adds no DB migration/audit SQL')

builder=text('tools/build_release_package.py'); verifier=text('tools/verify_release_package.py')
cb=text('tools/build_complete_package.py'); cv=text('tools/verify_complete_package.py')
check("INTENDED_RELEASE = '1.8.0'" in builder and "INTENDED_TAG = 'v1.8.0'" in builder, 'Runtime builder targets Version 1.8.0')
check("metadata.get('intended_release') == '1.8.0'" in verifier, 'Runtime verifier targets Version 1.8.0')
check("VERSION = '1.8.0'" in cb and "VERSION = '1.8.0'" in cv, 'Complete package tools target Version 1.8.0')

for runtime in ['var/session','var/log','var/cache','var/db-migration','var/security/login-throttle','var/m4f-evidence']:
    base=ROOT/runtime
    files=[p for p in base.rglob('*') if p.is_file() and p.name!='.gitkeep'] if base.exists() else []
    check(not files, f'generated runtime data is absent: {runtime}')

for rel in [
    'docs/v1-8-release-implementation.md','docs/v1-8-release-files.md','docs/test-report-v1-8-release.md',
    'docs/release-gate-v1.8.0.md','docs/release-artifact-inventory-v1.8.0.md','docs/github-v1-8-powershell.md',
    'docs/github-release-notes-v1.8.0.md','APPLY_NOTE_V1_8_RELEASE.md','CHECKLIST_FOR_USER_V1_8_RELEASE.md',
    'UPDATED_FILES_V1_8_RELEASE.md',
]:
    check((ROOT/rel).is_file(), f'release document exists: {rel}')

for rel in ['tests/test_v18b_stock_static.py','tests/test_v18c_stock_search_static.py','tests/test_v18d_stock_pagination_static.py','tests/test_v18e_stock_ui_static.py']:
    check((ROOT/rel).is_file(), f'V1.8 stage regression exists: {rel}')

if not all(checks):
    raise SystemExit(f'{len(checks)-sum(checks)}/{len(checks)} Version 1.8 release checks failed')
print(f'All {len(checks)} Version 1.8 release checks passed.')
