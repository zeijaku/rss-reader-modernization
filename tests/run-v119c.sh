#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo '== V1.19-C security hardening focused checks =='
python tests/test_v119c_security_hardening.py
python tests/test_v119c_registration_throttle.py
python tests/test_v119c_api_request_limit.py
for file in app/common/common_conf.php app/login_throttle.php public/api_v1.php public/index.php public/stock.php; do
    php -l "$file"
done
echo 'PASS: V1.19-C focused tests completed'
