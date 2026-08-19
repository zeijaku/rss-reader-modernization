#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

php "$ROOT/tests/test_v1172a_x_widget.php"
php "$ROOT/tests/test_v1172a_x_widget_persistence.php"
python "$ROOT/tests/test_v1172a_x_widget.py"
node --check "$ROOT/public/js/x-widget.js"
node --check "$ROOT/public/js/calendar.js"

for file in \
  app/version.php \
  app/bootstrap.php \
  app/common/common_conf.php \
  app/dashboard_widget.php \
  app/information_widget.php \
  app/http_fetch.php \
  app/api.php \
  app/x_widget.php
do
  php -l "$ROOT/$file" >/dev/null
done

echo 'PASS: V1.17.2-A X API Widget focused tests completed'
