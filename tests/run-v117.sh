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

echo '== V1.17-D YouTube / Video playback checks =='
node --check "$ROOT/public/js/camera-video-playback.js"
python3 "$ROOT/tests/test_v117d_youtube_video.py"

echo '== V1.17-E MJPEG / HLS streaming checks =='
node --check "$ROOT/public/js/camera-video-streaming.js"
python3 "$ROOT/tests/test_v117e_mjpeg_hls.py"

echo '== V1.17-F Auto detection / UI / mobile checks =='
node "$ROOT/tests/test_v117f_auto_ui.js"
php "$ROOT/tests/test_v117f_asset_revision.php"
