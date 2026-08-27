#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
checks: list[tuple[str, bool]] = []

def check(name: str, condition: bool) -> None:
    checks.append((name, bool(condition)))

def text(rel: str) -> str:
    return (ROOT / rel).read_text(encoding='utf-8')

version = text('app/version.php')
calendar = text('public/js/calendar.js')
x_php = text('app/x_widget.php')
api_php = text('app/api.php') + ''.join(path.read_text(encoding='utf-8') for path in sorted((ROOT / 'app/api').glob('*.php')))
x_js = text('public/js/x-widget.js')
local_example = text('config/local.php.example')
env_example = text('config/.env.example')
builder = text('tools/build_release_package.py')
complete_builder = text('tools/build_complete_package.py')
verify_runtime = text('tools/verify_release_package.py')
verify_complete = text('tools/verify_complete_package.py')
ci = text('.github/workflows/ci.yml')

version_match = re.search(r"const APP_VERSION = '(\d+)\.(\d+)\.(\d+)(?:-[^']+)?';", version)
version_tuple = tuple(int(part) for part in version_match.groups()) if version_match else (0, 0, 0)
version_value_match = re.search(r"const APP_VERSION = '([^']+)';", version)
version_value = version_value_match.group(1) if version_value_match else ''
label_match = re.search(r"const APP_VERSION_LABEL = '([^']+)';", version)
revision_match = re.search(r"const APP_ASSET_REVISION = '([^']+)';", version)
active_revision = revision_match.group(1) if revision_match else ''

check('APP_VERSION keeps V1.17.2-or-later behavior', version_tuple >= (1, 17, 2))
expected_label = 'RSS Reader Modernization ' + (version_value.upper() if '-rc' in version_value else version_value)
check('APP_VERSION_LABEL matches current version', bool(label_match) and label_match.group(1) == expected_label)
check('APP_ASSET_REVISION is present', bool(active_revision))
check('X Widget JS uses the active release revision', bool(active_revision) and './js/x-widget.js?v=' + active_revision in calendar)
check('X Widget CSS uses the active release revision', bool(active_revision) and './css/x-widget.css?v=' + active_revision in calendar)
check('no staged V1.17.2 asset token remains', re.search(r'1\.17\.2-[a-z]', calendar) is None)

check('X modal explicitly labels the feature as advanced', '上級者向け機能' in x_js)
check('X modal explains Developer Platform and Pay Per Use requirements', 'X Developer Platform' in x_js and 'Pay Per Use' in x_js)
check('X modal has a dedicated API status alert', 'x-widget-api-status' in x_js)
for state in ['missing', 'invalid_format', 'unverified', 'verified', 'auth_failed']:
    check(f'frontend handles X connection state {state}', f"state === '{state}'" in x_js or f"state: '{state}'" in x_js)
check('frontend loads only non-secret connection state through API', "apiRequest('x.config.status'" in x_js)
check('missing or malformed token blocks X Widget registration button', "blockRegister = state === 'missing' || state === 'invalid_format'" in x_js and "data('x-config-disabled'" in x_js)
check('frontend renders status messages with text()', ".x-widget-api-status')" in x_js and '.text(message)' in x_js)

check('server exposes normalized X connection status action', "'x.config.status' => api_x_config_status" in api_php and 'x_widget_connection_status()' in api_php)
check('server blocks create when token is missing', "api_error('x_not_configured'" in api_php and "($connection['state'] ?? '') === 'missing'" in api_php)
check('server blocks create when token format is invalid', "api_error('x_token_invalid_format'" in api_php and "($connection['state'] ?? '') === 'invalid_format'" in api_php)
check('HTTP 401 records auth_failed state', "x_widget_connection_status_mark('auth_failed')" in x_php)
check('valid X API JSON records verified state', "x_widget_connection_status_mark('verified')" in x_php)
check('connection state cache compares token fingerprint with hash_equals', 'x_widget_token_fingerprint' in x_php and 'hash_equals' in x_php)
check('connection state cache stores SHA-256 fingerprint rather than raw token', "hash('sha256', $token)" in x_php and "'token_fingerprint'" in x_php)
check('public connection status excludes secret fields', "return ['state' => 'unverified', 'configured' => true, 'can_add' => true, 'checked_at' => null]" in x_php)

check('configuration examples use placeholder token only', 'replace-with-your-x-api-bearer-token' in local_example and 'replace-with-your-x-api-bearer-token' in env_example)
check('real local.php is not part of repository source', not (ROOT / 'config/local.php').exists())
check('later release adds no V1.17.2 SQL migration', not any('1_17_2' in q.name for q in (ROOT / 'database/migrations').glob('*')) if (ROOT / 'database/migrations').is_dir() else True)

# V1.23 standardized release tooling: current release identity is supplied as
# an explicit independent input instead of being hardcoded in each builder /
# verifier. Keep this V1.17.2 compatibility gate focused on that durable
# contract rather than one historical release string.
for name, body in (
    ('runtime builder', builder),
    ('complete builder', complete_builder),
    ('runtime verifier', verify_runtime),
    ('complete verifier', verify_complete),
):
    check(f'{name} accepts explicit release version', "--release" in body and "required=True" in body)
check('release builders exclude all generated var/cache data', "'var/cache'" in builder and "'var/cache'" in complete_builder)
check('CI continues to run V1.17.2 compatibility tests', 'bash tests/run-v1172.sh' in ci)

failed = [name for name, ok in checks if not ok]
for name, ok in checks:
    print(f"[{'PASS' if ok else 'FAIL'}] {name}")
print(f"RESULT: PASS {len(checks)-len(failed)} / FAIL {len(failed)} / SKIP 0")
sys.exit(1 if failed else 0)
