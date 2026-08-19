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
api_php = text('app/api.php')
x_js = text('public/js/x-widget.js')
local_example = text('config/local.php.example')
env_example = text('config/.env.example')
builder = text('tools/build_release_package.py')
complete_builder = text('tools/build_complete_package.py')
verify_runtime = text('tools/verify_release_package.py')
verify_complete = text('tools/verify_complete_package.py')
ci = text('.github/workflows/ci.yml')
release_workflow = text('.github/workflows/v1.17.2-release.yml') if (ROOT / '.github/workflows/v1.17.2-release.yml').exists() else ''

check('APP_VERSION finalized at 1.17.2', "const APP_VERSION = '1.17.2';" in version)
check('APP_VERSION_LABEL finalized at 1.17.2', "const APP_VERSION_LABEL = 'RSS Reader Modernization 1.17.2';" in version)
check('APP_ASSET_REVISION finalized at 1.17.2', "const APP_ASSET_REVISION = '1.17.2';" in version)
check('X Widget JS uses the final asset revision', './js/x-widget.js?v=1.17.2' in calendar)
check('X Widget CSS uses the final asset revision', './css/x-widget.css?v=1.17.2' in calendar)
check('dynamic V1.17 assets use the final cache key', '?v=1.17.1' not in calendar)

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
check('connection state cache stores a SHA-256 fingerprint rather than raw token', "hash('sha256', $token)" in x_php and "'token_fingerprint'" in x_php)
check('public connection status shape excludes fingerprint/token fields', "return ['state' => 'unverified', 'configured' => true, 'can_add' => true, 'checked_at' => null]" in x_php)

check('configuration examples use placeholder token only', 'replace-with-your-x-api-bearer-token' in local_example and 'replace-with-your-x-api-bearer-token' in env_example)
check('real local.php is not part of repository source', not (ROOT / 'config/local.php').exists())
check('X feature adds no SQL migration', not any('1_17_2' in p.name for p in (ROOT / 'database/migrations').glob('*')) if (ROOT / 'database/migrations').is_dir() else True)

for content, name in [(builder, 'runtime builder'), (complete_builder, 'complete builder'), (verify_runtime, 'runtime verifier'), (verify_complete, 'complete verifier')]:
    check(f'{name} targets 1.17.2', '1.17.2' in content and '1.17.1' not in content)
check('release builders exclude all generated var/cache data', "'var/cache'" in builder and "'var/cache'" in complete_builder)
check('CI runs V1.17.2 focused tests', 'bash tests/run-v1172.sh' in ci)
check('V1.17.2 release workflow exists', bool(release_workflow))
check('V1.17.2 release workflow runs current and focused suites', all(cmd in release_workflow for cmd in [
    'bash tests/run-current.sh', 'bash tests/run-v117.sh', 'bash tests/run-v1171.sh', 'bash tests/run-v1172.sh'
]))
check('V1.17.2 release workflow builds final runtime and complete ZIPs', 'rss-reader-modernization-1.17.2.zip' in release_workflow and 'rss-reader-modernization-1.17.2-complete.zip' in release_workflow)

failed = [name for name, ok in checks if not ok]
for name, ok in checks:
    print(f"[{'PASS' if ok else 'FAIL'}] {name}")
print(f"RESULT: PASS {len(checks)-len(failed)} / FAIL {len(failed)} / SKIP 0")
sys.exit(1 if failed else 0)
