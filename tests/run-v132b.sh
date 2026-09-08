#!/usr/bin/env sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
SCRIPT_DIR="$ROOT/tests"

echo '== V1.32-B focused: syntax =='
php -l "$ROOT/app/auth_totp.php"
php -l "$SCRIPT_DIR/test_v132b_totp.php"

echo '== V1.32-B focused: TOTP runtime / crypto / enrollment =='
php "$SCRIPT_DIR/test_v132b_totp.php"

echo '== V1.32-B focused: migration / config contract =='
python3 "$SCRIPT_DIR/test_v132b_migration.py"

echo 'PASS: V1.32-B focused suite completed'
