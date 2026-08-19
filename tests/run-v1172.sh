#!/usr/bin/env sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

echo '== V1.17.2-A X API Widget runtime / persistence checks =='
php "$ROOT/tests/test_v1172a_x_widget.php"
php "$ROOT/tests/test_v1172a_x_widget_persistence.php"

echo '== V1.17.2-B Bearer Token state / advanced UI checks =='
php "$ROOT/tests/test_v1172b_x_status.php"
php "$ROOT/tests/test_v1172b_x_status_missing.php"
php "$ROOT/tests/test_v1172b_x_status_invalid.php"
python3 "$ROOT/tests/test_v1172b_release_gate.py"

node --check "$ROOT/public/js/x-widget.js"
node --check "$ROOT/public/js/calendar.js"
node --check "$ROOT/public/js/camera-video-streaming.js"

for file in \
  app/version.php \
  app/bootstrap.php \
  app/common/common_conf.php \
  app/dashboard_widget.php \
  app/information_widget.php \
  app/http_fetch.php \
  app/api.php \
  app/x_widget.php \
  public/api_v1.php
do
  php -l "$ROOT/$file" >/dev/null
done

echo 'PASS: V1.17.2 focused tests completed'
