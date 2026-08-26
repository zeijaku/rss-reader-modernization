#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo '== V1.22-B Feed Health focused checks =='
python3 tests/test_v122b_feed_health.py
php tests/test_v122b_feed_health_runtime.php
php -l app/feed_health.php
php -l app/api/feed_health.php
php -l app/api.php
php -l app/feed/feed_fetcher.php
php -l tests/test_v122b_feed_health_runtime.php
node --check public/js/feed-health.js
node --check public/js/rss-management.js
node --check public/js/calendar.js

echo 'PASS: V1.22-B focused tests completed'