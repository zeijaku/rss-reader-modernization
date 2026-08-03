#!/usr/bin/env sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

echo '== PHP syntax =='
find "$ROOT/app" "$ROOT/public" "$ROOT/tools" "$ROOT/tests" -type f -name '*.php' -print0 | xargs -0 -n1 php -l

echo '== JavaScript syntax =='
node --check "$ROOT/public/js/dashboard.js"
node --check "$ROOT/public/js/calendar.js"

echo '== V1.1-B Tracking Parameter checks =='
php "$ROOT/tests/test_v11b_tracking_parameters.php"
python3 "$ROOT/tests/test_v11b_architecture.py"

echo '== V1.1-C Feed item NEW state checks =='
php "$ROOT/tests/test_v11c_feed_item_state.php"
python3 "$ROOT/tests/test_v11c_architecture.py"
python3 "$ROOT/tests/test_v11c_sql.py"
python3 "$ROOT/tests/test_v11c_runner.py"

echo '== V1.1-D Dashboard Widget foundation checks =='
php "$ROOT/tests/test_v11d_dashboard_widget.php"
python3 "$ROOT/tests/test_v11d_architecture.py"
python3 "$ROOT/tests/test_v11d_sql.py"
python3 "$ROOT/tests/test_v11d_dashboard_render.py"
python3 "$ROOT/tests/test_v11d_runner.py"

echo '== V1.1-E Dashboard Widget reorder checks =='
php "$ROOT/tests/test_v11e_widget_reorder.php"
python3 "$ROOT/tests/test_v11e_architecture.py"
node "$ROOT/tests/test_v11e_frontend_runtime.js"

echo '== V1.1-F Clock Widget checks =='
php "$ROOT/tests/test_v11f_clock_widget.php"
python3 "$ROOT/tests/test_v11f_architecture.py"
node "$ROOT/tests/test_v11f_frontend_runtime.js"
python3 "$ROOT/tests/test_v11f_dashboard_render.py"
python3 "$ROOT/tests/test_v11f_browser.py"

echo '== V1.1-G Memo Widget checks =='
php "$ROOT/tests/test_v11g_memo_widget.php"
python3 "$ROOT/tests/test_v11g_architecture.py"
python3 "$ROOT/tests/test_v11g_sql.py"
node "$ROOT/tests/test_v11g_frontend_runtime.js"
python3 "$ROOT/tests/test_v11g_dashboard_render.py"
python3 "$ROOT/tests/test_v11g_browser.py"

echo '== V1.1-H Task Widget checks =='
php "$ROOT/tests/test_v11h_task_widget.php"
python3 "$ROOT/tests/test_v11h_architecture.py"
python3 "$ROOT/tests/test_v11h_sql.py"
node "$ROOT/tests/test_v11h_frontend_runtime.js"
python3 "$ROOT/tests/test_v11h_dashboard_render.py"
python3 "$ROOT/tests/test_v11h_browser.py"

echo '== V1.1-I Calendar Widget checks =='
php "$ROOT/tests/test_v11i_calendar_widget.php"
python3 "$ROOT/tests/test_v11i_architecture.py"
python3 "$ROOT/tests/test_v11i_sql.py"
node "$ROOT/tests/test_v11i_frontend_runtime.js"
python3 "$ROOT/tests/test_v11i_dashboard_render.py"
python3 "$ROOT/tests/test_v11i_browser.py"

echo '== V1.1-I R2 Mobile Swipe / Loading checks =='
python3 "$ROOT/tests/test_v11i_r2_architecture.py"
node "$ROOT/tests/test_v11i_r2_frontend_runtime.js"
python3 "$ROOT/tests/test_v11i_r2_loading_browser.py"


echo '== V1.1-I R3 Mobile Task date layout checks =='
python3 "$ROOT/tests/test_v11i_r3_task_mobile_layout.py"

echo '== V1.1-J Account Settings checks =='
php "$ROOT/tests/test_v11j_account_settings.php"
php "$ROOT/tests/test_v11j_session.php"
python3 "$ROOT/tests/test_v11j_architecture.py"
node "$ROOT/tests/test_v11j_frontend_runtime.js"
python3 "$ROOT/tests/test_v11j_dashboard_render.py"
python3 "$ROOT/tests/test_v11j_browser.py"
