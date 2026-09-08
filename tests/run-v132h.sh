#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

echo '== V1.32-H syntax =='
for file in \
  app/auth_audit_log.php \
  app/bootstrap.php \
  app/common/common_conf.php \
  app/persistent_login.php \
  app/account_settings.php \
  app/api/account_totp.php \
  app/api/account_security.php \
  app/api/account_session.php \
  app/view/account_security.php \
  public/index.php \
  public/logout.php; do
  php -l "$file" >/dev/null
  echo "PASS: php -l $file"
done

echo '== V1.32-H focused =='
php tests/test_v132h_auth_audit.php
python3 tests/test_v132h_contract.py

echo '== V1.32-G retained regression =='
bash tests/run-v132g.sh
