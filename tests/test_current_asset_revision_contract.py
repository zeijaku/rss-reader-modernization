#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
VERSION = (ROOT / 'app/version.php').read_text(encoding='utf-8')
CALENDAR = (ROOT / 'public/js/calendar.js').read_text(encoding='utf-8')
CAMERA = (ROOT / 'public/js/camera-video-streaming.js').read_text(encoding='utf-8')
RSS = (ROOT / 'public/js/rss-management.js').read_text(encoding='utf-8')
RUNNER = (ROOT / 'tests/run-current-features.sh').read_text(encoding='utf-8')
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


app_match = re.search(r"const APP_VERSION = '([^']+)';", VERSION)
revision_match = re.search(r"const APP_ASSET_REVISION = '([^']+)';", VERSION)
app_version = app_match.group(1) if app_match else ''
asset_revision = revision_match.group(1) if revision_match else ''

check(bool(app_version) and asset_revision == app_version,
      'app/version.php remains the single release and asset revision input')

dynamic_loaders = {
    'Calendar loader': CALENDAR,
    'Camera streaming loader': CAMERA,
    'RSS management loader': RSS,
}
for label, source in dynamic_loaders.items():
    check('document.currentScript' in source,
          f'{label} derives its revision from the PHP-versioned entry script')
    check("v=([A-Za-z0-9._-]+)" in source,
          f'{label} accepts only the supported revision token characters')
    check('function assetUrl(path)' in source,
          f'{label} has one child-asset URL builder')
    check(asset_revision not in source and re.search(r"\?v=\d+\.\d+\.\d+", source) is None,
          f'{label} contains no copied release revision')

check(CALENDAR.count("loadScript(assetUrl('./js/") >= 20,
      'every Calendar child JavaScript request uses the derived revision')
check(CALENDAR.count("loadStyle(assetUrl('./css/") >= 10,
      'every Calendar child stylesheet request uses the derived revision')
check("assetUrl('./css/camera-video-streaming.css')" in CAMERA,
      'Camera fallback stylesheet uses the derived revision')
check("$.getScript(assetUrl('./js/rss-rules.js'))" in RSS
      and "$.getScript(assetUrl('./js/rss-rules-integration.js'))" in RSS,
      'both RSS management child scripts use the derived revision')

entry_contracts = {
    'public/index.php': "app_asset_url('js/calendar.js')",
    'public/stock.php': "app_asset_url('js/calendar.js')",
    'public/rss-management.php': "app_asset_url('js/rss-management.js')",
}
for relative, token in entry_contracts.items():
    source = (ROOT / relative).read_text(encoding='utf-8')
    check(token in source, f'{relative} versions its dynamic-loader entry through app_asset_url')

check('test_current_asset_revision_contract.py' in RUNNER
      and 'test_current_asset_revision_runtime.js' in RUNNER,
      'the current feature gate includes static and runtime revision tests')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
