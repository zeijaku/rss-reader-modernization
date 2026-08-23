#!/usr/bin/env sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
SCRIPT_DIR="$ROOT/tests"

echo '== V1.20-F release-candidate integration gate =='
python3 "$SCRIPT_DIR/test_v120f_release_candidate.py"
node "$SCRIPT_DIR/test_v120f_game_runtime.js"
php "$SCRIPT_DIR/test_v120f_all_rss_recent.php"
php -l "$ROOT/app/all_rss_recent.php"
php -l "$ROOT/app/api/all_rss_recent.php"
php -l "$ROOT/app/mini_game.php"
php -l "$ROOT/app/version.php"
php -l "$ROOT/app/view/dashboard_modals.php"
php -l "$ROOT/public/api_v1.php"
node --check "$ROOT/public/js/all-rss-recent.js"
node --check "$ROOT/public/js/mini-game.js"
node --check "$ROOT/public/js/calendar.js"
node --check "$ROOT/public/js/camera-video-streaming.js"

echo 'PASS: V1.20-F release-candidate integration gate completed'
