#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo '== V1.19-B modular architecture focused checks =='
python tests/test_v119b_modular_architecture.py
for file in app/api.php app/api/*.php app/dashboard_widget.php app/dashboard/*.php; do
    php -l "$file"
done
echo 'PASS: V1.19-B focused tests completed'
