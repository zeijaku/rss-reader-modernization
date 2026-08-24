#!/usr/bin/env sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
T="$ROOT/tests"
echo '== V1.20.1-E final release gate =='
python3 "$T/test_v1201e_final.py"
node "$T/test_v120f_game_runtime.js"
php "$T/test_v120f_all_rss_recent.php"
php -l "$ROOT/app/calendar_color.php"
php -l "$ROOT/app/mini_game.php"
php -l "$ROOT/app/version.php"
php -l "$ROOT/public/calendar_color_api.php"
node --check "$ROOT/public/js/block-collapse.js"
node --check "$ROOT/public/js/calendar-colors.js"
node --check "$ROOT/public/js/calendar.js"
node --check "$ROOT/public/js/camera-video-streaming.js"
node --check "$ROOT/public/js/memo-refresh.js"
echo 'PASS: V1.20.1-E final release gate completed'
