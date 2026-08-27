#!/usr/bin/env sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

printf '%s\n' '== V1.21 Smartphone / Touch compatibility =='
python3 "$ROOT/tests/test_v121c_mobile_touch.py"

printf '%s\n' '== V1.21 visual compatibility =='
python3 "$ROOT/tests/test_v121b_drawer_visual.py"

printf '%s\n' '== V1.21 structure compatibility =='
python3 "$ROOT/tests/test_v121a_drawer_categories.py"

printf '%s\n' '== Existing Drawer / Offcanvas compatibility =='
python3 "$ROOT/tests/test_v13b_drawer_structure.py"

printf '%s\n' '== V1.21 compatibility syntax =='
php -l "$ROOT/public/settings.php"
node --check "$ROOT/public/js/calendar.js"
node --check "$ROOT/public/js/drawer-categories.js"

printf '%s\n' 'V1.21 compatibility checks passed.'
