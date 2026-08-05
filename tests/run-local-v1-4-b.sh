#!/usr/bin/env sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
php -l "$ROOT/app/mini_game.php"
php -l "$ROOT/app/dashboard_widget.php"
php -l "$ROOT/app/api.php"
php -l "$ROOT/public/index.php"
node --check "$ROOT/public/js/mini-game.js"
node --check "$ROOT/public/js/dashboard.js"
php "$ROOT/tests/test_v14b_game_widget.php"
python3 "$ROOT/tests/test_v14b_architecture.py"
node "$ROOT/tests/test_v14b_storage_runtime.js"
python3 "$ROOT/tests/test_v14b_dashboard_render.py"
