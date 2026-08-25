#!/usr/bin/env sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

echo '== V1.21-A Drawer category focused checks =='
python3 "$ROOT/tests/test_v121a_drawer_categories.py"

echo '== Existing Drawer / Offcanvas compatibility check =='
python3 "$ROOT/tests/test_v13b_drawer_structure.py"

echo '== Changed PHP syntax =='
php -l "$ROOT/app/version.php"
php -l "$ROOT/public/settings.php"

echo '== Changed / added JavaScript syntax =='
node --check "$ROOT/public/js/calendar.js"
node --check "$ROOT/public/js/drawer-categories.js"

echo 'V1.21-A focused checks passed.'
