#!/usr/bin/env sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

php "$ROOT/tests/test_v11b_tracking_parameters.php"
python3 "$ROOT/tests/test_v11b_architecture.py"
php "$ROOT/tests/test_sb05_07_api.php"
python3 "$ROOT/tests/test_version_marker.py"
