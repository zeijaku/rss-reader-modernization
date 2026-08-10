from __future__ import annotations

from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []

def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

version=(ROOT/'app/version.php').read_text(encoding='utf-8')
readme=(ROOT/'README.md').read_text(encoding='utf-8')
notes=(ROOT/'RELEASE_NOTES.md').read_text(encoding='utf-8')
roadmap=(ROOT/'docs/roadmap.md').read_text(encoding='utf-8')
versioning=(ROOT/'docs/versioning.md').read_text(encoding='utf-8')
gitignore=(ROOT/'.gitignore').read_text(encoding='utf-8')

check("const APP_VERSION = '1.7.0';" in version, 'APP_VERSION is exact 1.7.0')
check("const APP_VERSION_LABEL = 'RSS Reader Modernization 1.7.0';" in version, 'APP_VERSION_LABEL is exact 1.7.0')
check('**Stable release:** `RSS Reader Modernization 1.7.0`' in readme, 'README identifies Version 1.7.0 as stable')
check('Development checkpoint' not in readme[:700], 'README top has no development checkpoint marker')
check('# RSS Reader Modernization 1.7.0 Release Notes' in notes[:100], 'Release Notes target Version 1.7.0')
check('## Verification limits' in notes, 'Release Notes disclose verification limits')
check('- [x] V1.7-I Full回帰・Version 1.7.0 Release／GitHub main反映準備' in roadmap, 'Roadmap marks V1.7-I complete')
check('Git Tag: `v1.7.0`' in versioning, 'Version policy targets v1.7.0')

schema=(ROOT/'database/schema.sql').read_text(encoding='utf-8')
found=set(re.findall(r"SET @t_([a-z_]+) = CONCAT", schema))
expected={'user_info','user_conf','content','content_stock','feed_item_state','dashboard_widget','memo','task','calendar_event','remember_token'}
check(found == expected, 'new-install schema has expected ten logical tables')
check('`widget_height` TINYINT UNSIGNED NOT NULL DEFAULT 1' in schema, 'schema contains widget_height')

for rel in [
    'database/migrations/007_v1_7_remember_token.sql',
    'database/migrations/008_v1_7_widget_height.sql',
    'database/audit/v1_7_e_preflight.sql','database/audit/v1_7_e_postflight.sql',
    'database/audit/v1_7_h_preflight.sql','database/audit/v1_7_h_postflight.sql',
]:
    check((ROOT/rel).is_file(), f'V1.7 SQL exists: {rel}')
    check(('!/'+rel) in gitignore, f'.gitignore explicitly allows V1.7 SQL: {rel}')

for runtime in ['var/session','var/log','var/cache','var/db-migration','var/security/login-throttle','var/m4f-evidence']:
    files=[p for p in (ROOT/runtime).rglob('*') if p.is_file() and p.name != '.gitkeep']
    check(not files, f'generated runtime data is absent: {runtime}')

builder=(ROOT/'tools/build_release_package.py').read_text(encoding='utf-8')
verifier=(ROOT/'tools/verify_release_package.py').read_text(encoding='utf-8')
complete_builder=(ROOT/'tools/build_complete_package.py').read_text(encoding='utf-8')
complete_verifier=(ROOT/'tools/verify_complete_package.py').read_text(encoding='utf-8')
check("INTENDED_RELEASE = '1.7.0'" in builder and "INTENDED_TAG = 'v1.7.0'" in builder, 'Runtime builder targets Version 1.7.0')
check("metadata.get('intended_release') == '1.7.0'" in verifier, 'Runtime verifier targets Version 1.7.0')
check("VERSION = '1.7.0'" in complete_builder, 'Complete builder targets Version 1.7.0')
check("VERSION = '1.7.0'" in complete_verifier, 'Complete verifier targets Version 1.7.0')
check("'var/cache'" in builder and "'var/cache'" in complete_builder, 'Builders exclude all generated var/cache content')

for rel in [
    'docs/v1-7-release-implementation.md','docs/v1-7-release-files.md',
    'docs/test-report-v1-7-release.md','docs/release-gate-v1.7.0.md',
    'docs/release-artifact-inventory-v1.7.0.md','docs/github-v1-7-powershell.md',
    'APPLY_NOTE_V1_7_RELEASE.md','CHECKLIST_FOR_USER_V1_7_RELEASE.md',
]:
    check((ROOT/rel).is_file(), f'release document exists: {rel}')

if not all(checks):
    raise SystemExit(f'{len(checks)-sum(checks)}/{len(checks)} Version 1.7 release checks failed')
print(f'All {len(checks)} Version 1.7 release checks passed.')
