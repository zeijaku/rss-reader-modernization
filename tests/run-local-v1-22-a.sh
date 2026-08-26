#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

for file in app/common/common_conf.php app/feed_metadata.php app/opml.php app/api/opml.php public/rss-management.php; do
  php -l "$file" >/dev/null
  echo "PASS php-l: $file"
done

if command -v node >/dev/null 2>&1; then
  node --check public/js/rss-management.js
  node --check public/js/drawer-categories.js
  echo "PASS node-check: V1.22-A JavaScript"
else
  echo "SKIP node-check: node is unavailable"
fi

set +e
php tests/opml_v122a_test.php
parser_status=$?
set -e
if [[ $parser_status -ne 0 && $parser_status -ne 2 ]]; then
  exit $parser_status
fi

php tests/db_table_allowlist_v122a_test.php

grep -q "content_owner = :owner" app/feed_metadata.php
grep -q "DOCTYPE|ENTITY" app/opml.php
grep -q "app_validate_feed_url" app/opml.php
grep -q "opml.import" app/api/opml.php
grep -q "csrf-token" public/rss-management.php
echo "PASS static security/ownership checks"
