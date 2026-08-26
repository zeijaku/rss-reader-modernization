#!/usr/bin/env sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

printf '%s\n' '== V1.21-D Drawer integration focused checks =='
python3 "$ROOT/tests/test_v121d_drawer_integration.py"

printf '%s\n' '== V1.21-C Smartphone / Touch compatibility =='
python3 "$ROOT/tests/test_v121c_mobile_touch.py"

printf '%s\n' '== V1.21-B visual compatibility =='
python3 "$ROOT/tests/test_v121b_drawer_visual.py"

printf '%s\n' '== V1.21-A structure compatibility =='
python3 "$ROOT/tests/test_v121a_drawer_categories.py"

printf '%s\n' '== Existing Drawer / Offcanvas compatibility =='
python3 "$ROOT/tests/test_v13b_drawer_structure.py"

printf '%s\n' '== Changed PHP syntax =='
php -l "$ROOT/app/version.php"

printf '%s\n' '== Changed JavaScript syntax =='
node --check "$ROOT/public/js/calendar.js"
node --check "$ROOT/public/js/drawer-categories.js"

printf '%s\n' 'V1.21-D focused checks passed.'
