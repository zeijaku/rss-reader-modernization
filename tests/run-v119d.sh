#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo '== V1.19-D cleanup/documentation focused checks =='
python tests/test_v119d_cleanup_docs.py
for file in app/view/dashboard_modals.php public/settings.php public/stock.php; do
    php -l "$file"
done
php tests/test_v11j_account_settings.php
php tests/test_v11j_session.php
node tests/test_v11j_frontend_runtime.js
python tests/test_v11j_dashboard_render.py
python tests/test_v113c_settings_render.py
node --check public/js/dashboard.js
node --check public/js/calendar.js
node --check public/js/camera-video-streaming.js

echo 'PASS: V1.19-D focused tests completed'
