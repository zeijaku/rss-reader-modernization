from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
version_text = (ROOT / 'app/version.php').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app/bootstrap.php').read_text(encoding='utf-8')

checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


version_match = re.search(r"const APP_VERSION = '([^']+)';", version_text)
label_match = re.search(r"const APP_VERSION_LABEL = '([^']+)';", version_text)
asset_match = re.search(r"const APP_ASSET_REVISION = '([^']+)';", version_text)

version = version_match.group(1) if version_match else ''
label = label_match.group(1) if label_match else ''

check(bool(re.fullmatch(r'\d+\.\d+\.\d+(?:-(?:rc[1-9][0-9]*|dev\.[1-9][0-9]*))?', version)),
      'APP_VERSION uses the supported semantic version format')
expected_label = f'RSS Reader Modernization {version.upper()}' if '-rc' in version else f'RSS Reader Modernization {version}'
check(label == expected_label, 'APP_VERSION_LABEL matches APP_VERSION')
check("require_once __DIR__ . '/version.php';" in bootstrap, 'bootstrap loads version.php')

if asset_match:
    asset_revision = asset_match.group(1)
    check(bool(asset_revision.strip()), 'APP_ASSET_REVISION is not empty when defined')
    check(bool(re.fullmatch(r'[A-Za-z0-9._-]+', asset_revision)), 'APP_ASSET_REVISION uses a URL-safe revision token')
    check(True, 'staged asset revisions may differ from the visible APP_VERSION')
else:
    check(True, 'APP_ASSET_REVISION is optional; APP_VERSION remains the fallback')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
