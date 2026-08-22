#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo '== V1.19-F final release focused gate =='
python tests/test_v119f_release_final.py
bash tests/run-v119e.sh
python tests/test_current_version_contract.py
python tests/test_current_asset_contract.py
python tests/test_current_cache_security.py
node --check public/js/calendar.js
node --check public/js/camera-video-streaming.js
php -l app/version.php

echo 'PASS: V1.19-F final release focused gate completed'
