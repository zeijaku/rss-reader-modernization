#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

php -l "$ROOT/app/version.php"
php -l "$ROOT/public/file-library.php"
php -l "$ROOT/tests/file_library_ui_v128f_test.php"
node --check "$ROOT/public/js/file-library.js"
node --check "$ROOT/public/js/file-library-core.js"
node --check "$ROOT/public/js/file-library-text-preview.js"
node --check "$ROOT/public/js/file-library-csv-preview.js"
node --check "$ROOT/public/js/file-library-ui.js"
php "$ROOT/tests/file_library_ui_v128f_test.php"
