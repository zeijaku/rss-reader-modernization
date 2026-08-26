#!/usr/bin/env sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

printf '%s\n' '== V1.22-E final release contract =='
python3 "$ROOT/tests/test_v122e_final.py"

printf '%s\n' '== V1.22-D RSS Rules integration compatibility =='
bash "$ROOT/tests/run-v122d.sh"

printf '%s\n' '== V1.22-C RSS Rules compatibility =='
bash "$ROOT/tests/run-v122c.sh"

printf '%s\n' '== V1.22-B Feed Health compatibility =='
bash "$ROOT/tests/run-v122b.sh"

printf '%s\n' '== Final changed PHP syntax =='
php -l "$ROOT/app/version.php"
php -l "$ROOT/public/rss-management.php"
php -l "$ROOT/public/api_v1.php"

printf '%s\n' '== Final changed JavaScript syntax =='
node --check "$ROOT/public/js/calendar.js"
node --check "$ROOT/public/js/feed-health.js"
node --check "$ROOT/public/js/rss-rule-display.js"
node --check "$ROOT/public/js/drawer-categories.js"

printf '%s\n' 'V1.22-E final checks passed.'
