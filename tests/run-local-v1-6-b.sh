#!/usr/bin/env sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

php -l "$ROOT/public/index.php"
node --check "$ROOT/public/js/dashboard.js"
python3 "$ROOT/tests/test_dashboard_js_syntax.py"

python3 "$ROOT/tests/test_v11i_r2_architecture.py"
node "$ROOT/tests/test_v11i_r2_frontend_runtime.js"
python3 "$ROOT/tests/test_v11i_r2_loading_browser.py"
node "$ROOT/tests/test_v11e_frontend_runtime.js"

python3 "$ROOT/tests/test_v13c_header_structure.py"
python3 "$ROOT/tests/test_v13c_header_browser.py"
python3 "$ROOT/tests/test_v13d_spacing_structure.py"
python3 "$ROOT/tests/test_v13d_spacing_browser.py"

php "$ROOT/tests/test_v14b_game_widget.php"
python3 "$ROOT/tests/test_v14b_architecture.py"
node "$ROOT/tests/test_v14b_storage_runtime.js"
python3 "$ROOT/tests/test_v14b_dashboard_render.py"
node "$ROOT/tests/test_v14c_game_runtime.js"
python3 "$ROOT/tests/test_v14c_architecture.py"
python3 "$ROOT/tests/test_v14c_dashboard_render.py"
python3 "$ROOT/tests/test_v14c_browser.py"
node "$ROOT/tests/test_v14d_game_runtime.js"
python3 "$ROOT/tests/test_v14d_architecture.py"
python3 "$ROOT/tests/test_v14d_dashboard_render.py"
python3 "$ROOT/tests/test_v14d_browser.py"
python3 "$ROOT/tests/test_v14d_theme_browser.py"
python3 "$ROOT/tests/test_v14d_r2_game_header.py"

node "$ROOT/tests/test_v15b_clock_timer_runtime.js"
python3 "$ROOT/tests/test_v15b_architecture.py"
python3 "$ROOT/tests/test_v15b_dashboard_render.py"
python3 "$ROOT/tests/test_v15b_browser.py"
node "$ROOT/tests/test_v15c_clock_timer_runtime.js"
python3 "$ROOT/tests/test_v15c_architecture.py"
python3 "$ROOT/tests/test_v15c_dashboard_render.py"
python3 "$ROOT/tests/test_v15c_browser.py"
python3 "$ROOT/tests/test_v15c_theme_browser.py"
python3 "$ROOT/tests/test_v15c_r2_mobile_feed_overflow.py"
python3 "$ROOT/tests/test_v15c_r3_mobile_summary_icon.py"

python3 "$ROOT/tests/test_v16b_architecture.py"
node "$ROOT/tests/test_v16b_frontend_runtime.js"
python3 "$ROOT/tests/test_v16b_browser.py"
