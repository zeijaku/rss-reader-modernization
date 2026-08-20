#!/usr/bin/env sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
SCRIPT_DIR="$ROOT/tests"

# V1.17-G / TEST-1 + TEST-2
# Default CI should protect the current product contract, not every historical
# implementation detail accumulated during earlier phased releases.
#
# The previous comprehensive runner (tests/run.sh) is intentionally kept
# unchanged and can still be run manually when a historical regression is
# specifically being investigated.

echo '== Current regression: PHP syntax =='
find "$ROOT" -type f -name '*.php' -print0 | xargs -0 -n1 php -l

echo '== Current regression: Core / Security Baseline =='
php "$SCRIPT_DIR/run.php"
python3 "$SCRIPT_DIR/static_checks.py"
python3 "$SCRIPT_DIR/test_sql_behavior.py"
php "$SCRIPT_DIR/test_sb03_session.php"
php "$SCRIPT_DIR/test_sb03_https.php"
python3 "$SCRIPT_DIR/test_sb03_http.py"
python3 "$SCRIPT_DIR/test_public_smoke.py"
php "$SCRIPT_DIR/test_r5_session_storage.php"
php "$SCRIPT_DIR/test_sb04_auth.php"
php "$SCRIPT_DIR/test_sb04_throttle.php"
php "$SCRIPT_DIR/test_sb04_registration_disabled.php"
python3 "$SCRIPT_DIR/test_sb03_04_static.py"
php "$SCRIPT_DIR/test_sb05_07_api.php"
python3 "$SCRIPT_DIR/test_sb05_07_http.py"
python3 "$SCRIPT_DIR/test_sb05_07_static.py"
python3 "$SCRIPT_DIR/test_dashboard_js_syntax.py"
php "$SCRIPT_DIR/test_sb08_validation.php"
php "$SCRIPT_DIR/test_sb09_fetch.php"
php "$SCRIPT_DIR/test_sb10_feed_payload.php"
python3 "$SCRIPT_DIR/test_sb10_output_static.py"
php "$SCRIPT_DIR/test_sb12_runtime.php"
php "$SCRIPT_DIR/test_sb12_signatures.php"
php "$SCRIPT_DIR/test_sb12_atom_links.php"
python3 "$SCRIPT_DIR/test_sb12_public_warnings.py"
python3 "$SCRIPT_DIR/test_sb11_12_static.py"
php "$SCRIPT_DIR/test_sb13_integrity.php"
python3 "$SCRIPT_DIR/test_sb13_sql.py"
php "$SCRIPT_DIR/test_sb13_prefix.php"
php "$ROOT/tools/db_sb13.php" --help >/dev/null
php "$SCRIPT_DIR/test_sb14_auth_rollback.php"
php "$SCRIPT_DIR/test_sb14_ssrf_matrix.php"
php "$SCRIPT_DIR/test_sb14_xss_matrix.php"
php "$SCRIPT_DIR/test_sb14_parser_matrix.php"
python3 "$SCRIPT_DIR/test_sb14_surface_static.py"

for d in "$ROOT/var/session" "$ROOT/var/log" "$ROOT/var/security/login-throttle" "$ROOT/var/db-migration" "$ROOT/var/cache/feed"; do
    if [ -d "$d" ]; then
        find "$d" -type f ! -name '.gitkeep' -delete
    fi
done
python3 "$SCRIPT_DIR/test_sb14_repository_scan.py"

if grep -RInE --exclude-dir=.git --exclude='*.md' --exclude='.env.example' '(BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY|mysql:[^\n]*(password|pwd)=|sk-[A-Za-z0-9_-]{20,}|AKIA[0-9A-Z]{16})' "$ROOT/public" "$ROOT/app"; then
    echo 'Potential secret pattern found.' >&2
    exit 1
fi
echo 'PASS: secret pattern scan'
python3 "$SCRIPT_DIR/test_current_version_contract.py"

echo '== Current regression: RSS engine / fetch / cache =='
php "$SCRIPT_DIR/test_m1a_feed_engine.php"
php "$SCRIPT_DIR/test_m1b_feed_source.php"
php "$SCRIPT_DIR/test_m1c_feed_adapters.php"
python3 "$SCRIPT_DIR/test_m1c_fixture_shapes.py"
php "$SCRIPT_DIR/test_m1d_item_identity.php"
php "$SCRIPT_DIR/test_m1e_feed_cache.php"
python3 "$SCRIPT_DIR/test_m1e_concurrency.py"
php "$SCRIPT_DIR/test_m1f_http_conditional.php"
php "$SCRIPT_DIR/test_m1f_cache_revalidation.php"
python3 "$SCRIPT_DIR/test_m1f_concurrency.py"
php "$SCRIPT_DIR/test_m1g_http_retry.php"
php "$SCRIPT_DIR/test_m1g_fetch_resilience.php"
python3 "$SCRIPT_DIR/test_m1g_concurrency.py"

echo '== Current regression: Frontend runtime / assets =='
node "$SCRIPT_DIR/test_m2a_dashboard_runtime.js"
node "$SCRIPT_DIR/test_m2b_feed_runtime.js"
node "$SCRIPT_DIR/test_m2c_accessibility_runtime.js"
node "$SCRIPT_DIR/test_m2d_mutation_runtime.js"
python3 "$SCRIPT_DIR/test_current_asset_contract.py"
python3 "$SCRIPT_DIR/test_m2f_browser_smoke.py"

echo '== Current regression: Dashboard / Widget core =='
php "$SCRIPT_DIR/test_v11b_tracking_parameters.php"
php "$SCRIPT_DIR/test_v11c_feed_item_state.php"
python3 "$SCRIPT_DIR/test_v11c_sql.py"
php "$SCRIPT_DIR/test_v11d_dashboard_widget.php"
python3 "$SCRIPT_DIR/test_v11d_sql.py"
node "$SCRIPT_DIR/test_v11e_frontend_runtime.js"
php "$SCRIPT_DIR/test_v11f_clock_widget.php"
node "$SCRIPT_DIR/test_v11f_frontend_runtime.js"
php "$SCRIPT_DIR/test_v11g_memo_widget.php"
node "$SCRIPT_DIR/test_v11g_frontend_runtime.js"
php "$SCRIPT_DIR/test_v11h_task_widget.php"
node "$SCRIPT_DIR/test_v11h_frontend_runtime.js"
php "$SCRIPT_DIR/test_v11i_calendar_widget.php"
node "$SCRIPT_DIR/test_v11i_frontend_runtime.js"
php "$SCRIPT_DIR/test_v11j_account_settings.php"
php "$SCRIPT_DIR/test_v11j_session.php"
node "$SCRIPT_DIR/test_v11j_frontend_runtime.js"

echo '== Current regression: Feed / Search / Article actions =='
python3 "$SCRIPT_DIR/test_v12a_auth_http.py"
python3 "$SCRIPT_DIR/test_v12a_error_http.py"
php "$SCRIPT_DIR/test_v12b_feed_payload.php"
php "$SCRIPT_DIR/test_v12c_search_feed.php"
python3 "$SCRIPT_DIR/test_v12d_article_actions.py"
python3 "$SCRIPT_DIR/test_v12d_article_actions_browser.py"

echo '== Current regression: Game / Clock / Mobile interactions =='
php "$SCRIPT_DIR/test_v14b_game_widget.php"
node "$SCRIPT_DIR/test_v14b_storage_runtime.js"
node "$SCRIPT_DIR/test_v14c_game_runtime.js"
node "$SCRIPT_DIR/test_v14d_game_runtime.js"
node "$SCRIPT_DIR/test_v15b_clock_timer_runtime.js"
node "$SCRIPT_DIR/test_v15c_clock_timer_runtime.js"
node "$SCRIPT_DIR/test_v16b_frontend_runtime.js"
php "$SCRIPT_DIR/test_v16c_game_widget.php"
node "$SCRIPT_DIR/test_v16c_lights_out_runtime.js"
node "$SCRIPT_DIR/test_v16d_storage_runtime.js"

echo '== Current regression: Assets / Login / Grid / Calendar =='
php "$SCRIPT_DIR/test_v17c_asset_url.php"
python3 "$SCRIPT_DIR/test_current_cache_security.py"
python3 "$SCRIPT_DIR/test_v17d_response_headers.py"
php "$SCRIPT_DIR/test_v17e_remember_token.php"
php "$SCRIPT_DIR/test_v17f_persistent_login.php"
python3 "$SCRIPT_DIR/test_v1180_prerelease_fixes.py"
node "$SCRIPT_DIR/test_v17g_widget_grid_runtime.js"
php "$SCRIPT_DIR/test_v17h_widget_height.php"
php "$SCRIPT_DIR/test_v17h_r4_holiday.php"

echo '== Current regression: Stock / split entry points =='
php "$SCRIPT_DIR/test_v18b_stock_db.php"
php "$SCRIPT_DIR/test_v18c_stock_helpers.php"
php "$SCRIPT_DIR/test_v18c_stock_render.php"
php "$SCRIPT_DIR/test_v18d_stock_pagination.php"
php "$SCRIPT_DIR/test_v18d_stock_page_clamp.php"
php "$SCRIPT_DIR/test_v18e_stock_task_targets.php"
php "$SCRIPT_DIR/test_v18e_stock_render.php"
python3 "$SCRIPT_DIR/test_v113b_stock_route.py"
python3 "$SCRIPT_DIR/test_v113c_settings_render.py"
python3 "$SCRIPT_DIR/test_v113c_settings_browser.py"

echo '== Current regression: Information Widgets =='
php "$SCRIPT_DIR/test_v115_information_widgets.php"
python3 "$SCRIPT_DIR/test_current_information_widget_contract.py"

echo 'PASS: current regression suite completed'
