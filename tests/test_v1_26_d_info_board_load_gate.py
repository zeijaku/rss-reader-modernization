#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
loader = (ROOT / 'public/js/memo-counter.js').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
runner = (ROOT / 'tests/run-current-features.sh').read_text(encoding='utf-8')

checks = []

def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

check("widget.infoboard.fetch" in loader and "function isInfoBoardFetch" in loader,
      'load gate targets only Information Board aggregate fetch requests')
check("return originalAjax.apply(this, arguments)" in loader,
      'normal RSS, Weather and other Ajax requests continue immediately')
check("Number($.active || 0)" in loader and "IDLE_CHECKS_REQUIRED = 2" in loader,
      'Information Board waits for a stably idle Dashboard Ajax queue')
check("idleChecks = 0" in loader and "idleChecks++" in loader,
      'new Dashboard network activity resets the idle window')
check("var queue = []" in loader and "var running = false" in loader,
      'Information Board fetches are queued and limited to one running request')
check("xhr.done(function ()" in loader and ".fail(function ()" in loader and ".always(function ()" in loader,
      'queued request preserves the existing jqXHR done/fail/always contract')
check("proxy.abort = function" in loader,
      'queued Information Board request remains abortable')
check("APP_ASSET_REVISION = '1.25.0'" in version,
      'global asset revision remains at the formal V1.25 baseline during V1.26 development')
check("APP_VERSION = '1.25.0'" in version,
      'formal app version remains unchanged during V1.26 development')
check('test_v1_26_d_info_board_load_gate.py' in runner,
      'active current-feature runner executes the gate static contract')
check('test_v1_26_d_info_board_load_gate.js' in runner,
      'active current-feature runner executes the gate runtime helper test')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {len(checks) - failed} / FAIL {failed} / SKIP 0')
if failed:
    raise SystemExit(1)
