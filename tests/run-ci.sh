#!/usr/bin/env sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

echo '== CI preflight: workflow / release contracts =='
python3 "$ROOT/tests/test_version_dependency_hygiene.py"
python3 "$ROOT/tests/test_workflow_hygiene.py"
python3 "$ROOT/tests/test_release_flow.py"

echo '== CI regression: current product contract =='
bash "$ROOT/tests/run-current.sh"
bash "$ROOT/tests/run-current-features.sh"

echo 'PASS: local CI-equivalent checks completed'
