from __future__ import annotations

from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
check("const APP_VERSION = '1.1.0';" in version, 'APP_VERSION is exact 1.1.0')
check("const APP_VERSION_LABEL = 'RSS Reader Modernization 1.1.0';" in version, 'APP_VERSION_LABEL is exact 1.1.0')

readme = (ROOT / 'README.md').read_text(encoding='utf-8')
notes = (ROOT / 'RELEASE_NOTES.md').read_text(encoding='utf-8')
roadmap = (ROOT / 'docs/roadmap.md').read_text(encoding='utf-8')
versioning = (ROOT / 'docs/versioning.md').read_text(encoding='utf-8')
check('**Stable release:** `RSS Reader Modernization 1.1.0`' in readme, 'README identifies Version 1.1.0 as stable')
check('Current development checkpoint' not in readme[:500], 'README top has no development checkpoint marker')
check('# RSS Reader Modernization 1.1.0 Release Notes' in notes, 'Release Notes target Version 1.1.0')
check('正式Releaseではありません' not in notes, 'Release Notes have no preview or RC warning')
check('## Verification limits' in notes, 'Release Notes disclose verification limits')
check('- [x] V1.1-K 統合回帰・Version 1.1.0 Release' in roadmap, 'Roadmap marks V1.1-K complete')
check('Git Tag: `v1.1.0`' in versioning, 'Version policy targets v1.1.0')

schema = (ROOT / 'database/schema.sql').read_text(encoding='utf-8')
expected_tables = {
    'user_info', 'user_conf', 'content', 'content_stock', 'feed_item_state',
    'dashboard_widget', 'memo', 'task', 'calendar_event',
}
found_tables = set(re.findall(r"SET @t_([a-z_]+) = CONCAT", schema))
check(found_tables == expected_tables, 'new-install schema contains the expected nine logical tables')
check("SET @table_prefix = 'rss_';" in schema, 'new-install schema keeps rss_ example prefix')

migrations = [ROOT / f'database/migrations/{number}_{name}.sql' for number, name in [
    ('002', 'v1_1_feed_item_state'), ('003', 'v1_1_dashboard_widget'),
    ('004', 'v1_1_memo'), ('005', 'v1_1_task'), ('006', 'v1_1_calendar_event'),
]]
for migration in migrations:
    text = migration.read_text(encoding='utf-8')
    check("SET @table_prefix = 'ig_';" in text, f'existing-DB migration defaults to ig_: {migration.name}')
    check('CREATE TABLE IF NOT EXISTS' in text, f'migration is safe to re-run: {migration.name}')
    check(not re.search(r'\b(?:DROP|TRUNCATE)\s+TABLE\b', text, re.I), f'migration has no destructive table operation: {migration.name}')

for runtime in [
    'var/session', 'var/log', 'var/cache/feed', 'var/db-migration',
    'var/security/login-throttle', 'var/m4f-evidence',
]:
    files = [p for p in (ROOT / runtime).glob('*') if p.is_file() and p.name != '.gitkeep']
    check(not files, f'generated runtime data is absent: {runtime}')

public_files = [p for p in (ROOT / 'public').rglob('*') if p.is_file()]
check(len(public_files) == 33, 'public asset inventory contains 33 Version 1.1 runtime files')
check(not (ROOT / 'public/js/jquery-3.3.1.min.js').exists(), 'unused jQuery 3.3.1 asset is absent')
check(not list((ROOT / 'public/webfonts').glob('*.eot')), 'unused EOT fonts are absent')
check(not list((ROOT / 'public/webfonts').glob('*.svg')), 'unused SVG fonts are absent')
check(not list((ROOT / 'public/webfonts').glob('*.woff')), 'unused WOFF1 fonts are absent')

builder = (ROOT / 'tools/build_release_package.py').read_text(encoding='utf-8')
verifier = (ROOT / 'tools/verify_release_package.py').read_text(encoding='utf-8')
check("INTENDED_RELEASE = '1.1.0'" in builder and "INTENDED_TAG = 'v1.1.0'" in builder, 'Runtime package builder targets Version 1.1.0')
check("metadata.get('intended_release') == '1.1.0'" in verifier, 'Runtime package verifier targets Version 1.1.0')
check((ROOT / 'tools/build_complete_package.py').is_file(), 'complete source package builder exists')
check((ROOT / 'tools/verify_complete_package.py').is_file(), 'complete source package verifier exists')

if not all(checks):
    raise SystemExit(f'{len(checks) - sum(checks)}/{len(checks)} V1.1-K release checks failed')
print(f'All {len(checks)} V1.1-K release checks passed.')
