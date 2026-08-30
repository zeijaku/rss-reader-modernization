#!/usr/bin/env sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

echo '== V1.28-C focused syntax =='
php -l "$ROOT/app/file_library.php"
php -l "$ROOT/public/file_content.php"
php -l "$ROOT/app/version.php"
node --check "$ROOT/public/js/file-library.js"

echo '== V1.28-C PDF eligibility runtime =='
php "$ROOT/tests/file_library_pdf_v128c_test.php"

echo '== V1.28-C PDF Viewer security/UI contract =='
php "$ROOT/tests/file_library_pdf_viewer_v128c_test.php"

echo '== V1.28-C focused static scans =='
if grep -RInE '\b(eval|exec|shell_exec|system|passthru|proc_open|popen)\s*\(' "$ROOT/app/file_library.php" "$ROOT/public/file_content.php"; then
    echo 'FAIL: execution primitive found' >&2
    exit 1
fi
if grep -RInE 'ZipArchive|extractTo' "$ROOT/app/file_library.php" "$ROOT/public/file_content.php" "$ROOT/public/js/file-library.js"; then
    echo 'FAIL: ZIP extraction primitive found' >&2
    exit 1
fi
if grep -RInE 'file_stored_name|APP_FILE_UPLOAD_DIR' "$ROOT/public/js/file-library.js"; then
    echo 'FAIL: physical storage detail exposed to viewer JS' >&2
    exit 1
fi

echo 'PASS: V1.28-C focused suite completed'
