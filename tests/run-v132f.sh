#!/usr/bin/env bash
set -euo pipefail
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$ROOT"

echo '== V1.32-F package syntax =='
for f in \
  app/session.php app/auth_step_up.php app/account_security.php \
  app/api/account_security.php app/bootstrap.php app/common/common_conf.php \
  app/view/account_security.php public/api_v1.php; do
  php -l "$f"
done
node --check public/js/account-2fa.js

echo '== V1.32-F package focused =='
php tests/test_v132f_step_up.php
php tests/test_v132f_password.php
php tests/test_v132f_disable.php
php tests/test_v132f_api.php
python tests/test_v132f_contract.py

echo 'PASS: V1.32-F package-local checks completed'
