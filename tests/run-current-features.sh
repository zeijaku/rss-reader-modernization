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
python3 "$SCRIPT_DIR/test_v124b_memo_widget.py"
node --check "$ROOT/public/js/memo-counter.js"

echo '== Current feature contracts: V1.24 Stock state =='
php "$SCRIPT_DIR/test_v124c_stock_state_api.php"
python3 "$SCRIPT_DIR/test_v124c_stock_state_static.py"
php "$SCRIPT_DIR/test_v124d_stock_state_ui.php"
php "$SCRIPT_DIR/test_v124e_stock_state_filters.php"
node "$SCRIPT_DIR/test_v124e_stock_state_ui.js"
python3 "$SCRIPT_DIR/test_v124e_stock_state_workflow_static.py"
node --check "$ROOT/public/js/stock-state-ui.js"

echo 'PASS: current feature contract suite completed'
