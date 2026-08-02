#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


expected = {
    'APP_ENV', 'APP_DEBUG', 'APP_LOG_ENABLED', 'APP_LOG_PATH', 'APP_ERROR_LOG_PATH', 'APP_HASH_KEY',
    'REGISTRATION_ENABLED', 'AUTH_PASSWORD_MIN_LENGTH', 'AUTH_PASSWORD_MAX_LENGTH', 'SESSION_COOKIE_NAME',
    'SESSION_IDLE_TIMEOUT', 'SESSION_ABSOLUTE_TIMEOUT', 'LOGIN_RATE_WINDOW', 'LOGIN_RATE_MAX_PAIR',
    'LOGIN_RATE_MAX_IP', 'LOGIN_RATE_BLOCK_SECONDS',
    'APP_HTTP_CONNECT_TIMEOUT_MS', 'APP_HTTP_TIMEOUT_MS', 'APP_HTTP_MAX_REDIRECTS', 'APP_HTTP_MAX_BYTES',
    'APP_HTTP_USER_AGENT', 'APP_FEED_CACHE_ENABLED', 'APP_FEED_CONDITIONAL_REQUEST_ENABLED',
    'APP_FEED_CACHE_TTL_SECONDS', 'APP_FEED_CACHE_LOCK_TIMEOUT_MS', 'APP_FEED_RETRY_ENABLED',
    'APP_FEED_RETRY_MAX_DELAY_SECONDS', 'APP_FEED_STALE_IF_ERROR_ENABLED', 'APP_FEED_STALE_MAX_AGE_SECONDS',
    'DB_DRIVER', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_TABLE_PREFIX',
}

local_text = (ROOT / 'config/local.php.example').read_text(encoding='utf-8')
env_text = (ROOT / 'config/.env.example').read_text(encoding='utf-8')
conf_text = (ROOT / 'app/common/common_conf.php').read_text(encoding='utf-8')
doc_text = (ROOT / 'docs/configuration.md').read_text(encoding='utf-8')

local_keys = set(re.findall(r"'([A-Z][A-Z0-9_]+)'\s*=>", local_text))
env_keys = set(re.findall(r'^([A-Z][A-Z0-9_]+)=', env_text, flags=re.M))
check(local_keys == expected, 'local.php.example contains the exact supported production key inventory')
check(env_keys == expected, '.env.example contains the exact supported production key inventory')

for key in sorted(expected):
    check(key in conf_text, f'Runtime configuration supports documented key: {key}')
    check(f'`{key}`' in doc_text, f'Configuration document covers key: {key}')

check('Environment variable > config/local.php > safe default' in doc_text, 'configuration precedence is documented')
check('Environment variables take precedence' in local_text, 'local.php.example warns about environment precedence')
check('does not load .env files by itself' in env_text, '.env.example states that no dotenv loader exists')
check('.env` fileを自動読込しません' in doc_text, 'configuration document states that no dotenv loader exists')

# Defaults and clamps are tied to current code, not invented documentation.
defaults = {
    "app_env('APP_ENV', 'production')": 'APP_ENV default',
    "app_env_bool('APP_DEBUG', false)": 'APP_DEBUG default',
    "app_env_bool('REGISTRATION_ENABLED', true)": 'registration default',
    "app_env('AUTH_PASSWORD_MIN_LENGTH', '12')": 'password minimum default',
    "app_env('AUTH_PASSWORD_MAX_LENGTH', '72')": 'password maximum default',
    "app_env('SESSION_COOKIE_NAME', 'iguguru_session')": 'session cookie default',
    "app_env('SESSION_IDLE_TIMEOUT', '7200')": 'session idle default',
    "app_env('SESSION_ABSOLUTE_TIMEOUT', '43200')": 'session absolute default',
    "app_env('APP_HTTP_MAX_BYTES', '2097152')": 'HTTP body limit default',
    "app_env('APP_FEED_CACHE_TTL_SECONDS', '60')": 'Feed cache TTL default',
    "app_env('APP_FEED_RETRY_MAX_DELAY_SECONDS', '3600')": 'Feed retry max default',
    "app_env('APP_FEED_STALE_MAX_AGE_SECONDS', '86400')": 'stale max default',
    "app_env('DB_TABLE_PREFIX', 'ig_')": 'legacy-compatible prefix default',
}
for token, label in defaults.items():
    check(token in conf_text, f'Runtime {label} remains exact')

for value in ['production', 'false', 'true', '12', '72', 'iguguru_session', '7200', '43200', '3000', '8000', '3', '2097152', '60', '9000', '3600', '86400', '3306', 'rss_']:
    check(value in doc_text, f'Configuration documentation includes expected default/example: {value}')

check('DB_TABLE_PREFIX must be 1-40 ASCII characters' in conf_text, 'table prefix runtime validation remains')
check('1〜40文字' in doc_text and '英数字' in doc_text, 'table prefix constraint is documented')
check('APP_HASH_KEY' in doc_text and '32文字以上' in doc_text and '運用開始後は変更しない' in doc_text, 'APP_HASH_KEY continuity is documented')
check('replace-with-your-db-password' in local_text, 'local example uses a dummy DB password')
check('replace-with-a-strong-password' in env_text, 'environment example uses a dummy DB password')
check('replace-with-your-db-host' in local_text and 'replace-with-your-db-host' in env_text, 'examples use dummy DB hosts')
check('DB_SQLITE_PATH' not in local_text + env_text + doc_text, 'test-only SQLite path is not presented as production setting')
check('APP_FEED_CACHE_DIR' not in local_keys and 'APP_FEED_CACHE_DIR' not in env_keys, 'fixed private cache path is not presented as configurable')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M4-C configuration inventory checks passed.')
