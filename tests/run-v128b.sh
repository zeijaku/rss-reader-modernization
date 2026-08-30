#!/usr/bin/env sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

printf '%s\n' '== V1.28-B PHP syntax =='
php -l "$ROOT/app/version.php"
php -l "$ROOT/app/file_preview.php"
php -l "$ROOT/public/file_preview_api.php"
php -l "$ROOT/tests/file_preview_v128b_test.php"
php -l "$ROOT/tests/file_preview_endpoint_v128b_test.php"

printf '%s\n' '== V1.28-B helper / security contracts =='
php "$ROOT/tests/file_preview_v128b_test.php"
php "$ROOT/tests/file_preview_endpoint_v128b_test.php"

printf '%s\n' '== V1.28-B frontend syntax =='
node --check "$ROOT/public/js/file-library.js"

printf '%s\n' 'PASS: V1.28-B focused suite completed'
