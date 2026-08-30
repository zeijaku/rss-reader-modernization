#!/usr/bin/env sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
SCRIPT_DIR="$ROOT/tests"

# Durable feature contracts introduced after the original current-regression
# split. Keep this runner version-neutral: historical run-v*.sh files remain
# available for targeted investigation, but active CI/Release must not stack
# them indefinitely.

echo '== Current feature contracts: Security hardening =='
python3 "$SCRIPT_DIR/test_v119c_registration_throttle.py"
python3 "$SCRIPT_DIR/test_v119c_api_request_limit.py"

echo '== Current feature contracts: Drawer / mobile navigation =='
python3 "$SCRIPT_DIR/test_current_drawer_contract.py"
node --check "$ROOT/public/js/drawer-categories.js"

echo '== Current feature contracts: Feed Health =='
python3 "$SCRIPT_DIR/test_v122b_feed_health.py"
php "$SCRIPT_DIR/test_v122b_feed_health_runtime.php"
node --check "$ROOT/public/js/feed-health.js"
node --check "$ROOT/public/js/rss-management.js"

echo '== Current feature contracts: RSS Rules =='
python3 "$SCRIPT_DIR/test_v122c_rss_rules.py"
php "$SCRIPT_DIR/test_v122c_rss_rules_runtime.php"
python3 "$SCRIPT_DIR/test_v122d_rss_rules.py"
php "$SCRIPT_DIR/test_v122d_rss_rule_engine_runtime.php"
node --check "$ROOT/public/js/rss-rules.js"
node --check "$ROOT/public/js/rss-rule-display.js"
node --check "$ROOT/public/js/rss-rules-integration.js"

echo '== Current feature contracts: V1.24 Memo =='
python3 "$SCRIPT_DIR/test_v124b_memo_contract.py"
node --check "$ROOT/public/js/memo-counter.js"

echo '== Current feature contracts: V1.24 Stock state =='
php "$SCRIPT_DIR/test_v124c_stock_state.php"
python3 "$SCRIPT_DIR/test_v124c_stock_state_static.py"
php "$SCRIPT_DIR/test_v124d_stock_state_ui.php"
php "$SCRIPT_DIR/test_v124e_stock_state_filters.php"
node "$SCRIPT_DIR/test_v124e_stock_state_ui.js"
python3 "$SCRIPT_DIR/test_v124e_stock_state_workflow_static.py"
node --check "$ROOT/public/js/stock-state-ui.js"

echo '== Current feature contracts: V1.25 Calendar expansion =='
php "$SCRIPT_DIR/test_v1_25_b_calendar_time_contract.php"
php "$SCRIPT_DIR/test_v1_25_b_calendar_time_validation.php"
php "$SCRIPT_DIR/test_v1_25_c_calendar_event_ui_contract.php"
php "$SCRIPT_DIR/test_v1_25_d_recurrence_contract.php"
php "$SCRIPT_DIR/test_v1_25_d_recurrence_validation.php"
php "$SCRIPT_DIR/test_v1_25_e_calendar_source_actions_contract.php"
php "$SCRIPT_DIR/test_v1_25_f_calendar_polish_contract.php"
php "$SCRIPT_DIR/test_v1_25_f_calendar_polish_r3_contract.php"
node --check "$ROOT/public/js/calendar.js"
node --check "$ROOT/public/js/calendar-recurrence.js"
node --check "$ROOT/public/js/calendar-event-details.js"
node --check "$ROOT/public/js/calendar-source-actions.js"
node --check "$ROOT/public/js/calendar-polish.js"
node --check "$ROOT/public/js/calendar-polish-r3.js"

echo '== Current feature contracts: V1.26 Information Board backend =='
php "$SCRIPT_DIR/test_v1_26_b_info_board_backend.php"
python3 "$SCRIPT_DIR/test_v1_26_b_info_board_static.py"

echo '== Current feature contracts: V1.26 Information Board UI =='
python3 "$SCRIPT_DIR/test_v1_26_c_info_board_ui.py"
node --check "$ROOT/public/js/info-board.js"

echo '== Current feature contracts: V1.26 Information Board ticker =='
python3 "$SCRIPT_DIR/test_v1_26_d_info_board_ticker.py"
node "$SCRIPT_DIR/test_v1_26_d_info_board_ticker.js"
node --check "$ROOT/public/js/info-board-ticker.js"

echo '== Current feature contracts: V1.27 Tracking / File Library / Image Viewer =='
php "$SCRIPT_DIR/url_normalizer_v127b_test.php"
php "$SCRIPT_DIR/user_file_v127d_test.php"
php "$SCRIPT_DIR/file_library_v127e_test.php"
php "$SCRIPT_DIR/file_library_drawer_v127e2_test.php"
php "$SCRIPT_DIR/file_library_upload_v127e3_test.php"
php "$SCRIPT_DIR/file_library_drag_drop_v127e4_test.php"
php "$SCRIPT_DIR/file_library_endpoint_v127e_test.php"
php "$SCRIPT_DIR/file_library_image_viewer_v127f_test.php"
php "$SCRIPT_DIR/test_v127g_current_contract.php"
node --check "$ROOT/public/js/file-library.js"

echo 'PASS: current feature contract suite completed'
