#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

echo '== V1.32-G syntax =='
for file in \
  app/auth_session_registry.php \
  app/session.php \
  app/persistent_login.php \
  app/remember_token.php \
  app/account_settings.php \
  app/account_security.php \
  app/api/account_session.php \
  app/bootstrap.php \
  app/common/common_conf.php \
  app/view/account_security.php \
  public/api_v1.php; do
  php -l "$file" >/dev/null
  echo "PASS: php -l $file"
done
node --check public/js/account-2fa.js
echo 'PASS: node --check public/js/account-2fa.js'

echo '== V1.32-G focused =='
php tests/test_v132g_session_registry.php
python3 tests/test_v132g_contract.py
