#!/usr/bin/env sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

printf '%s\n' '== V1.21-E final release contract =='
if grep -q "APP_VERSION = '1.21.0'" "$ROOT/app/version.php"; then
    python3 "$ROOT/tests/test_v121e_final.py"
else
    printf '%s\n' 'SKIP: V1.21 formal release contract; running compatibility checks on later release.'
fi

printf '%s\n' '== V1.21-C Smartphone / Touch compatibility =='
python3 "$ROOT/tests/test_v121c_mobile_touch.py"

printf '%s\n' '== V1.21-B visual compatibility =='
python3 "$ROOT/tests/test_v121b_drawer_visual.py"

printf '%s\n' '== V1.21-A structure compatibility =='
python3 "$ROOT/tests/test_v121a_drawer_categories.py"

printf '%s\n' '== Existing Drawer / Offcanvas compatibility =='
python3 "$ROOT/tests/test_v13b_drawer_structure.py"

printf '%s\n' '== Final changed PHP syntax =='
php -l "$ROOT/app/version.php"
php -l "$ROOT/public/settings.php"

printf '%s\n' '== Final changed JavaScript syntax =='
node --check "$ROOT/public/js/calendar.js"
node --check "$ROOT/public/js/drawer-categories.js"

printf '%s\n' 'V1.21-E final checks passed.'
