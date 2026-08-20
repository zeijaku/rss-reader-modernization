#!/usr/bin/env sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
T="$ROOT/tests"

echo '== Version 1.18 Connection Monitor focused tests =='
python3 "$T/test_v118b_health_probe.py"
python3 "$T/test_v118c_health_probe_history.py"
python3 "$T/test_v118d_health_probe_state.py"
python3 "$T/test_v118e_health_probe_ui.py"
python3 "$T/test_v118f_health_probe_scope.py"
python3 "$T/test_v118g_release_contract.py"
python3 "$T/test_v1180_prerelease_fixes.py"
node --check "$ROOT/public/js/connection-monitor.js"
php -l "$ROOT/app/health_probe.php"
php -l "$ROOT/public/connection_probe.php"
echo 'PASS: Version 1.18 focused tests completed'
