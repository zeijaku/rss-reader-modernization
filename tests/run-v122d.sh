#!/usr/bin/env bash
set -euo pipefail

php -l app/rss_rule_engine.php
php -l app/api/rss_rule.php
php -l app/api/feed_health.php
php -l app/version.php
php -l tests/test_v122d_rss_rule_engine_runtime.php
node --check public/js/rss-rule-display.js
node --check public/js/rss-rules-integration.js
node --check public/js/rss-management.js
node --check public/js/calendar.js
python tests/test_v122d_rss_rules.py
php tests/test_v122d_rss_rule_engine_runtime.php

echo "V1.22-D RSS Rules integration gate: PASS"
