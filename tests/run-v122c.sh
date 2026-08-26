#!/usr/bin/env bash
set -euo pipefail

php -l app/rss_rule.php
php -l app/api/rss_rule.php
php -l app/api.php
php -l app/version.php
php -l tests/test_v122c_rss_rules_runtime.php
node --check public/js/rss-rules.js
node --check public/js/rss-management.js
node --check public/js/calendar.js
node --check public/js/camera-video-streaming.js
python tests/test_v122c_rss_rules.py
php tests/test_v122c_rss_rules_runtime.php

echo "V1.22-C RSS Rules focused gate: PASS"
