#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
php -l app/file_preview.php
php -l public/file_preview_api.php
php -l public/file_content.php
php -l app/file_library.php
php -l app/version.php
node --check public/js/file-library.js
node --check public/js/file-library-core.js
node --check public/js/file-library-text-preview.js
php tests/file_library_text_v128d_test.php
php tests/file_library_text_viewer_v128d_test.php
php tests/file_library_pdf_v128c_test.php
if grep -REn --exclude='*.min.js' --exclude='*.map' '\b(eval|shell_exec|system|passthru|proc_open|popen)\s*\(' app/file_preview.php public/file_preview_api.php public/js/file-library.js public/js/file-library-text-preview.js; then
  echo 'FAIL: dynamic/code execution primitive detected'; exit 1
fi
if grep -REn '\b(ZipArchive|extractTo)\b' app/file_preview.php public/file_preview_api.php public/js/file-library.js public/js/file-library-text-preview.js; then
  echo 'FAIL: ZIP extraction primitive detected'; exit 1
fi
if grep -REn 'file_stored_name|APP_FILE_UPLOAD_DIR|var/uploads' public/js/file-library.js public/js/file-library-text-preview.js; then
  echo 'FAIL: physical storage information exposed in JS'; exit 1
fi
echo 'V1.28-D focused checks passed.'
