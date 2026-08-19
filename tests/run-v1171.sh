#!/usr/bin/env sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

echo '== V1.17.1-A/E Timeout / session foundation checks =='
php "$ROOT/tests/test_v1171a_session_release.php"
python3 "$ROOT/tests/test_v1171a_api_session_policy.py"
node --check "$ROOT/public/js/app-notice.js"

echo '== V1.17.1-B Camera / Video + Mail stability checks =='
python3 "$ROOT/tests/test_v1171b_stability.py"

echo '== V1.17.1-C Information Widget stability checks =='
python3 "$ROOT/tests/test_v1171c_information_stability.py"

echo '== V1.17.1-D no-reload settings checks =='
python3 "$ROOT/tests/test_v1171d_r3_production_runtime.py"

echo '== V1.17.1-E Release Gate checks =='
python3 "$ROOT/tests/test_v1171e_hls_sri.py"
python3 "$ROOT/tests/test_v1171e_release_gate.py"
php -l "$ROOT/public/api_v1.php"
php -l "$ROOT/app/session.php"
php -l "$ROOT/app/version.php"
node --check "$ROOT/public/js/calendar.js"
node --check "$ROOT/public/js/camera-video-streaming.js"
node --check "$ROOT/public/js/camera-video-watchdog.js"
node --check "$ROOT/public/js/mail-widget-watchdog.js"
node --check "$ROOT/public/js/information-widget-watchdog.js"
node --check "$ROOT/public/js/widget-card-refresh.js"
node --check "$ROOT/public/js/widget-settings-no-reload.js"

echo 'PASS: V1.17.1 Release Gate focused tests completed'
