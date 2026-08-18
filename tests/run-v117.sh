#!/usr/bin/env sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

echo '== V1.17-B Camera / Video foundation checks =='
php "$ROOT/tests/test_v117b_camera_video.php"
python3 "$ROOT/tests/test_v117b_camera_video.py"

echo '== V1.17-C Snapshot Camera checks =='
node --check "$ROOT/public/js/camera-video.js"
node --check "$ROOT/public/js/calendar.js"
python3 "$ROOT/tests/test_v117c_snapshot_camera.py"
