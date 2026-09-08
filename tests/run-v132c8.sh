#!/usr/bin/env bash
set -euo pipefail
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$ROOT"

echo '== V1.32-C8 syntax =='
for f in \
  app/auth_totp.php app/bootstrap.php app/common/common_conf.php app/common/common_login.php \
  app/login_throttle.php app/persistent_login.php app/session.php app/api/account_totp.php \
  app/view/dashboard_modals.php public/api_v1.php public/index.php public/settings.php public/stock.php; do
  php -l "$f"
done
node --check public/js/account-2fa.js
node --check public/js/totp-qr.js

echo '== V1.32-C8 final security contract =='
python3 tests/test_v132c8_final_contract.py

echo '== V1.32-B/C focused regression =='
php tests/test_v132b_totp.php
python3 tests/test_v132b_migration.py
php tests/test_v132c_session.php
php tests/test_v132c_throttle.php
python3 tests/test_v132c_login_contract.py
php tests/test_v132c2_api.php
php tests/test_v132c2_enrollment.php
php tests/test_v132c3_api.php
php tests/test_v132c3_provisioning.php
python3 tests/test_v132c3_contract.py
node tests/test_v132c3_qr_runtime.js
php tests/test_v132c4_api.php
python3 tests/test_v132c4_contract.py
php tests/test_v132c5_remember_2fa.php
php tests/test_v132c5_totp_window.php
python3 tests/test_v132c5_pending_http.py
php tests/test_v132c6_totp_tamper.php
python3 tests/test_v132c6_bypass_contract.py
python3 tests/test_v132c6_bypass_http.py

echo '== selected existing auth/session/remember/stock compatibility =='
php tests/test_sb03_session.php
php tests/test_r5_session_storage.php
php tests/test_sb04_auth.php
php tests/test_v11j_session.php
php tests/test_v17e_remember_token.php
php tests/test_v17f_persistent_login.php
python3 tests/test_v12a_auth_http.py
php tests/test_v18c_stock_helpers.php
php tests/test_v18d_stock_pagination.php
php tests/test_v18e_stock_task_targets.php
python3 tests/test_v18e_stock_ui_static.py

echo 'PASS: V1.32-C8 focused finalization suite completed'
