#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
php tests/file_library_csv_v128e_test.php
php tests/file_library_csv_viewer_v128e_test.php
php tests/file_library_text_v128d_test.php
php tests/file_library_pdf_v128c_test.php
php tests/file_preview_v128b_test.php
php -l app/file_preview.php
php -l public/file_preview_api.php
node --check public/js/file-library.js
node --check public/js/file-library-core.js
node --check public/js/file-library-text-preview.js
node --check public/js/file-library-csv-preview.js
if grep -R -nE '\b(eval|exec|shell_exec|system|passthru|proc_open|popen)\s*\(' app/file_preview.php public/file_preview_api.php public/js/file-library*.js; then
  echo 'FAIL: dynamic/RCE primitive detected' >&2; exit 1
fi
if grep -R -nE 'ZipArchive|extractTo\s*\(' app/file_preview.php public/file_preview_api.php public/js/file-library*.js; then
  echo 'FAIL: ZIP extraction primitive detected' >&2; exit 1
fi
if grep -R -nE 'file_stored_name|APP_FILE_UPLOAD_DIR|var/uploads' public/js/file-library*.js; then
  echo 'FAIL: physical storage detail exposed to client JS' >&2; exit 1
fi
printf 'RESULT: V1.28-E focused suite PASS\n'
