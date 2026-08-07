from __future__ import annotations

from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []

def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
readme = (ROOT / 'README.md').read_text(encoding='utf-8')
notes = (ROOT / 'RELEASE_NOTES.md').read_text(encoding='utf-8')
roadmap = (ROOT / 'docs/roadmap.md').read_text(encoding='utf-8')
versioning = (ROOT / 'docs/versioning.md').read_text(encoding='utf-8')

check("const APP_VERSION = '1.5.0';" in version, 'APP_VERSION is exact 1.5.0')
check("const APP_VERSION_LABEL = 'RSS Reader Modernization 1.5.0';" in version, 'APP_VERSION_LABEL is exact 1.5.0')
check('**Stable release:** `RSS Reader Modernization 1.5.0`' in readme, 'README identifies Version 1.5.0 as stable')
check('Development checkpoint' not in readme[:500], 'README top has no development checkpoint marker')
check('# RSS Reader Modernization 1.5.0 Release Notes' in notes, 'Release Notes target Version 1.5.0')
check('正式Releaseではありません' not in notes, 'Release Notes have no preview or RC warning')
check('## Verification limits' in notes, 'Release Notes disclose verification limits')
check('- [x] V1.5-D Full回帰・Version 1.5.0 Release' in roadmap, 'Roadmap marks Version 1.5 release complete')
check('Git Tag: `v1.5.0`' in versioning, 'Version policy targets v1.5.0')
check('Clock Timer' in readme and 'Clock Timer' in notes, 'Clock Timer is documented in stable entry points')

check(not any((ROOT / 'database').rglob('*v1_5*')), 'Version 1.5 adds no migration or SQL file')
schema = (ROOT / 'database/schema.sql').read_text(encoding='utf-8')
expected_tables = {'user_info','user_conf','content','content_stock','feed_item_state','dashboard_widget','memo','task','calendar_event'}
found_tables = set(re.findall(r"SET @t_([a-z_]+) = CONCAT", schema))
check(found_tables == expected_tables, 'new-install schema remains the expected nine logical tables')

for runtime in ['var/session','var/log','var/cache/feed','var/db-migration','var/security/login-throttle','var/m4f-evidence']:
    files = [p for p in (ROOT / runtime).glob('*') if p.is_file() and p.name != '.gitkeep']
    check(not files, f'generated runtime data is absent: {runtime}')

builder=(ROOT/'tools/build_release_package.py').read_text(encoding='utf-8')
verifier=(ROOT/'tools/verify_release_package.py').read_text(encoding='utf-8')
complete_builder=(ROOT/'tools/build_complete_package.py').read_text(encoding='utf-8')
complete_verifier=(ROOT/'tools/verify_complete_package.py').read_text(encoding='utf-8')
check("INTENDED_RELEASE = '1.5.0'" in builder and "INTENDED_TAG = 'v1.5.0'" in builder, 'Runtime package builder targets Version 1.5.0')
check("metadata.get('intended_release') == '1.5.0'" in verifier, 'Runtime package verifier targets Version 1.5.0')
check("VERSION = '1.5.0'" in complete_builder, 'Complete package builder targets Version 1.5.0')
check("VERSION = '1.5.0'" in complete_verifier, 'Complete package verifier targets Version 1.5.0')

for rel in ['docs/v1-5-release-implementation.md','docs/v1-5-release-files.md','docs/test-report-v1-5-release.md','docs/release-gate-v1.5.0.md','docs/release-artifact-inventory-v1.5.0.md','APPLY_NOTE_V1_5_RELEASE.md','CHECKLIST_FOR_USER_V1_5_RELEASE.md']:
    check((ROOT / rel).is_file(), f'release document exists: {rel}')

if not all(checks):
    raise SystemExit(f'{len(checks)-sum(checks)}/{len(checks)} Version 1.5 release checks failed')
print(f'All {len(checks)} Version 1.5 release checks passed.')
