#!/usr/bin/env python3
from pathlib import Path
import re

from version_test_utils import is_later_application_release, is_later_visible_label
from dashboard_source_utils import dashboard_source
ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
asset_php = (ROOT / 'app/asset.php').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app/bootstrap.php').read_text(encoding='utf-8')
index = dashboard_source(ROOT)
login = (ROOT / 'app/common/common_login.php').read_text(encoding='utf-8')
combined = index + '\n' + login

check(any(v in version for v in ["APP_VERSION = '1.7.0-dev.2'", "APP_VERSION = '1.7.0-dev.3'", "APP_VERSION = '1.7.0-dev.4'", "APP_VERSION = '1.7.0-dev.5'", "APP_VERSION = '1.7.0-dev.6'", "APP_VERSION = '1.7.0-dev.7'", "APP_VERSION = '1.7.0-dev.8'", "APP_VERSION = '1.7.0-dev.9'", "APP_VERSION = '1.7.0-dev.10'", "APP_VERSION = '1.7.0'"]) or is_later_application_release(version, (1, 7, 0)), 'Application Version is V1.7-C or later')
check(any(v in version for v in ["APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-C / R1'", "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-D / R1'", "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-E / R1'", "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-F / R1'", "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-G / R1'", "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-H / R1'", "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-H / R2'", "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-H / R3'", "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-H / R4'", "APP_VERSION_LABEL = 'RSS Reader Modernization 1.7.0'"]) or is_later_visible_label(version, (1, 7, 0)), 'Application Label is V1.7-C or later')
check("require_once __DIR__ . '/asset.php';" in bootstrap, 'Bootstrap loads the Asset URL helper')
check('function app_asset_url(string $path): string' in asset_php, 'Asset URL helper has a strict string contract')
check("rawurlencode(APP_VERSION)" in asset_php, 'Asset URLs use the shared Application Version token')
check('filemtime' not in asset_php and 'hash_file' not in asset_php, 'Asset URL generation does not depend on deployment timestamps or runtime hashing')
check("str_starts_with($path, 'css/')" in asset_php and "str_starts_with($path, 'js/')" in asset_php, 'Asset helper uses a local directory allowlist')
check('External asset URLs are not allowed.' in asset_php and 'Unsafe local asset path.' in asset_php, 'External URLs and path traversal are rejected')

check('?v=1.6-' not in combined and '?v=1.7-' not in combined, 'Manual Stage-specific Cache Busting strings are removed from PHP views')
check('./css/' not in combined and './js/' not in combined and 'href="./favicon.png"' not in combined, 'Direct public Asset URLs are removed from PHP views')

calls = re.findall(r"app_asset_url\('([^']+)'\)", combined)
expected = {
    'favicon.png', 'css/all.css', 'css/drawer.min.css', 'css/dashboard.css',
    'css/mini-game.css', 'css/clock-timer.css', 'css/auth.css',
    'js/jquery-3.7.1.min.js', 'js/popper.min.js', 'js/bootstrap.min.js',
    'js/iscroll.js', 'js/drawer.min.js', 'js/mini-game.js', 'js/lights-out.js',
    'js/clock-timer.js', 'js/dashboard.js', 'js/calendar.js', 'js/auth.js',
}
check(expected <= set(calls), 'All static CSS, JavaScript and favicon references use app_asset_url()')
check("app_asset_url('css/' . resolve_theme_stylesheet" in index, 'Dynamic Theme CSS uses app_asset_url() after the existing allowlist resolver')

missing = [path for path in sorted(expected) if not (ROOT / 'public' / path).is_file()]
check(not missing, 'Every statically referenced Asset exists in public/')

themes = re.findall(r"'bootstrap(?:-[a-z]+)?'\s*=>\s*'([^']+\.css)'", (ROOT / 'app/common/common_func.php').read_text(encoding='utf-8'))
check(len(themes) == 8, 'All eight existing Theme stylesheets remain allowlisted')
check(all((ROOT / 'public/css' / theme).is_file() for theme in themes), 'Every allowlisted Theme stylesheet exists')

if "APP_VERSION = '1.7.0-dev.2'" in version:
    check(not (ROOT / 'database/migrations/007_v1_7_remember_token.sql').exists(), 'V1.7-C introduces no DB or Remember Token migration')
else:
    check(True, 'Later V1.7 checkpoints may add the planned Remember Token migration')
check((not (ROOT / 'database/migrations/008_v1_7_widget_height.sql').exists()) or ("APP_VERSION = '1.7.0-dev.7'" in version or "APP_VERSION = '1.7.0-dev.8'" in version or "APP_VERSION = '1.7.0-dev.9'" in version or "APP_VERSION = '1.7.0-dev.10'" in version or "APP_VERSION = '1.7.0'" in version) or is_later_application_release(version, (1, 7, 0)), 'V1.7-C adds no height migration; V1.7-H may add the planned migration')
build = (ROOT / 'SOURCE_BUILD.txt').read_text(encoding='utf-8')
check(('application_version=1.7.0-dev.2' in build and 'baseline_sha256=aabc4942f85ebe397b3ab738643c75ee1f763b15508de8bccd453702bcfa5014' in build) or 'application_version=1.7.0-dev.3' in build or 'application_version=1.7.0-dev.4' in build or 'application_version=1.7.0-dev.5' in build or 'application_version=1.7.0-dev.6' in build or 'application_version=1.7.0-dev.7' in build or 'application_version=1.7.0-dev.8' in build or 'application_version=1.7.0-dev.9' in build or 'application_version=1.7.0-dev.10' in build or 'application_version=1.7.0' in build or ('package_type=complete-source' in build and is_later_application_release(version, (1, 7, 0))), 'Source metadata retains the V1.7-C checkpoint or a later complete-source package')
for rel in ['APPLY_NOTE_V1_7_C.md', 'CHECKLIST_FOR_USER_V1_7_C.md', 'UPDATED_FILES_V1_7_C.md', 'docs/v1-7-c-implementation.md', 'docs/v1-7-c-files.md', 'docs/test-report-v1-7-c.md']:
    check((ROOT / rel).is_file(), f'{rel} exists')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
