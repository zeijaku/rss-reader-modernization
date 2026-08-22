#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo '== V1.19-E release-candidate focused gate =='
python tests/test_v119e_release_candidate.py
bash tests/run-v119.sh
bash tests/run-v119c.sh
bash tests/run-v119d.sh
python tests/test_current_version_contract.py
python tests/test_current_asset_contract.py
python tests/test_current_cache_security.py
node --check public/js/calendar.js
node --check public/js/camera-video-streaming.js
php -l app/version.php

echo 'PASS: V1.19-E release-candidate focused gate completed'
