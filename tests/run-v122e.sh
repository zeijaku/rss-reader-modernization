#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo '== V1.22-A OPML focused checks =='
php tests/db_table_allowlist_v122a_test.php
php tests/feed_metadata_title_v122a_test.php
php tests/opml_v122a_test.php

echo '== V1.22-B Feed Health gate =='
bash tests/run-v122b.sh

echo '== V1.22-C RSS Rules gate =='
bash tests/run-v122c.sh

echo '== V1.22-D RSS Rules integration gate =='
bash tests/run-v122d.sh

echo '== V1.22-E final release contract =='
python3 tests/test_v122e_final.py

php -l app/version.php
python3 -m py_compile tools/build_release_package.py tools/verify_release_package.py tools/build_complete_package.py tools/verify_complete_package.py tests/test_v122e_final.py
find tools tests -type d -name '__pycache__' -prune -exec rm -rf {} +

echo 'PASS: V1.22.0 final release gate completed'
