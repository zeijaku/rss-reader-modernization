#!/usr/bin/env sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

echo '== V1.17.1-A Timeout / session foundation checks =='
php "$ROOT/tests/test_v1171a_session_release.php"
python3 "$ROOT/tests/test_v1171a_api_session_policy.py"
node --check "$ROOT/public/js/app-notice.js"
node --check "$ROOT/public/js/calendar.js"
