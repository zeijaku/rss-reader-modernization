#!/usr/bin/env bash
set -euo pipefail
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$ROOT"

echo '== V1.32-D package syntax =='
for f in \
  app/auth_recovery_code.php app/bootstrap.php app/api/account_totp.php \
  app/common/common_login.php app/view/dashboard_modals.php \
  public/index.php public/settings.php; do
  php -l "$f"
done
node --check public/js/account-2fa.js

echo '== V1.32-D package focused tests =='
php tests/test_v132d_recovery_code.php
php tests/test_v132d_api.php
# test_v132d_contract.py was executed against the complete working tree during packaging.
# pending_http needs the complete project runtime and is therefore not run from
# this changed-files-only delivery ZIP.

echo 'PASS: V1.32-D package-local checks completed'
