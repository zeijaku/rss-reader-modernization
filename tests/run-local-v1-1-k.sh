#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
php -l "$ROOT/app/version.php"
python3 "$ROOT/tests/test_v11k_release.py"
python3 "$ROOT/tests/test_v11k_documentation.py"
python3 "$ROOT/tests/test_v11j_r2_feed_header_height.py"
