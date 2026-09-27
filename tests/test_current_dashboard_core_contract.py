#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


index = text('public/index.php')
core = text('public/js/dashboard-core.js')
dashboard = text('public/js/dashboard.js')
runner = text('tests/run-current.sh')

core_tag = "app_asset_url('js/dashboard-core.js')"
dashboard_tag = "app_asset_url('js/dashboard.js')"
check(core_tag in index, 'Dashboard shared core is loaded through the versioned asset helper')
check(dashboard_tag in index, 'Dashboard controller remains loaded through the versioned asset helper')
check(
    index.find(core_tag) >= 0 and index.find(core_tag) < index.find(dashboard_tag),
    'Dashboard shared core loads before the controller',
)

check('window.IGuguruDashboardCore = {' in core, 'Dashboard core exposes one explicit namespace')
check('window.IGuguruDashboardCore' in dashboard, 'Dashboard controller consumes the shared core namespace')
check('$.ajax({' in core, 'Dashboard core owns the jQuery API transport')
check('$.ajax(' not in dashboard, 'Dashboard controller no longer owns a direct jQuery API transport')
check("url: './api_v1.php'" in core and "method: 'POST'" in core, 'Dashboard core preserves the API endpoint and POST method')
check("'csrf_token': appCsrfToken()" in core, 'Dashboard core keeps CSRF injection centralized')
check("xhr.getResponseHeader('X-CSRF-Token')" in core, 'Dashboard core keeps response CSRF rotation handling')
check("/^[a-f0-9]{64}$/.test(token)" in core, 'Dashboard core validates rotated CSRF token shape')
check("xhr.status === 401 && code === 'unauthenticated'" in core, 'Dashboard core keeps the unauthenticated reload boundary')
check("'通信がタイムアウトしました'" in core and "'通信に失敗しました'" in core, 'Dashboard core preserves controlled request error messages')
check("'request-pending'" in core and ".prop('disabled', true)" in core, 'Dashboard core preserves duplicate-submit protection')

for helper in (
    'appCsrfToken',
    'initCsrfSessionSync',
    'apiErrorMessage',
    'clearNotice',
    'showNotice',
    'apiResponseOk',
    'apiRequest',
    'requestStart',
    'requestEnd',
    'requestFail',
):
    check(f'function {helper}' in dashboard, f'Dashboard keeps compatibility wrapper: {helper}')

for unsafe in ('.html(', 'innerHTML', 'insertAdjacentHTML', 'document.write(', 'eval(', 'new Function'):
    check(unsafe not in core, f'Dashboard core avoids unsafe DOM/code operation: {unsafe}')

check(
    'test_current_dashboard_core_contract.py' in runner
    and 'test_current_dashboard_core_runtime.js' in runner,
    'current regression gate includes Dashboard core contract and runtime tests',
)

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
