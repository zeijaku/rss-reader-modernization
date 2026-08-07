#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []

def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
public_ht = (ROOT / 'public/.htaccess').read_text(encoding='utf-8')
root_ht = (ROOT / '.htaccess').read_text(encoding='utf-8')
cache = (ROOT / 'app/response_cache.php').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app/bootstrap.php').read_text(encoding='utf-8')
error_response = (ROOT / 'app/error_response.php').read_text(encoding='utf-8')
session = (ROOT / 'app/session.php').read_text(encoding='utf-8')
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
logout = (ROOT / 'public/logout.php').read_text(encoding='utf-8')
api = (ROOT / 'public/api_v1.php').read_text(encoding='utf-8')

check(any(v in version for v in ["APP_VERSION = '1.7.0-dev.3'", "APP_VERSION = '1.7.0-dev.4'", "APP_VERSION = '1.7.0-dev.5'", "APP_VERSION = '1.7.0-dev.6'", "APP_VERSION = '1.7.0-dev.7'", "APP_VERSION = '1.7.0-dev.8'", "APP_VERSION = '1.7.0-dev.9'", "APP_VERSION = '1.7.0-dev.10'", "APP_VERSION = '1.7.0'"]), 'Application Version is V1.7-D or later')
check(any(v in version for v in ["APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-D / R1'", "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-E / R1'", "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-F / R1'", "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-G / R1'", "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-H / R1'", "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-H / R2'", "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-H / R3'", "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-H / R4'", "APP_VERSION_LABEL = 'RSS Reader Modernization 1.7.0'"]), 'Application Label is V1.7-D or later')
check('<IfModule mod_headers.c>' in public_ht, 'Header rules are guarded by mod_headers availability')
for header, value in [
    ('X-Content-Type-Options', 'nosniff'),
    ('Referrer-Policy', 'strict-origin-when-cross-origin'),
    ('X-Frame-Options', 'SAMEORIGIN'),
    ('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()'),
]:
    check(f'Header always set {header} "{value}"' in public_ht, f'{header} is explicitly configured')

csp = "frame-ancestors 'self'; base-uri 'self'; form-action 'self'"
check(f'Header always set Content-Security-Policy "{csp}"' in public_ht, 'Limited CSP protects framing, base URI and form targets')
check('default-src' not in public_ht and 'script-src' not in public_ht and 'style-src' not in public_ht, 'V1.7-D does not introduce a breaking full CSP')
check('Strict-Transport-Security' not in public_ht and 'Strict-Transport-Security' not in root_ht, 'HSTS remains deferred until HTTPS-only deployment is confirmed')

check(re.search(r'<FilesMatch "\\\.\(\?:css\|js\)\$">.*?max-age=31536000, immutable', public_ht, re.S) is not None,
      'CSS and JavaScript receive one-year immutable caching')
check(re.search(r'<FilesMatch "\\\.\(\?:woff2\?\|ttf\|png\|jpe\?g\|gif\|svg\|ico\)\$">.*?max-age=604800', public_ht, re.S) is not None,
      'Fonts and images receive a shorter seven-day cache policy')
check(public_ht.count('Header always set Cache-Control') == 2, 'Only static Asset FilesMatch blocks set public Cache-Control')

check('function app_send_private_no_store_headers(): void' in cache, 'Private dynamic HTML cache helper exists')
check("header('Cache-Control: private, no-store, max-age=0');" in cache, 'Private HTML uses private no-store policy')
check('function app_send_no_store_headers(): void' in cache, 'API/error no-store helper exists')
check("header('Cache-Control: no-store, max-age=0');" in cache, 'API/error uses no-store policy')
check(cache.count("header('Pragma: no-cache');") == 2 and cache.count("header('Expires: 0');") == 2,
      'Legacy proxy/browser revalidation headers accompany both no-store policies')
check("require_once __DIR__ . '/response_cache.php';" in bootstrap, 'Bootstrap loads the response cache helper')
check("require_once __DIR__ . '/response_cache.php';" in error_response, 'Standalone error response loads the cache helper')
check("'session.cache_limiter' => ''" in session, 'PHP automatic cache limiter is disabled in favor of explicit policies')
check('app_send_private_no_store_headers();' in index, 'Dashboard/login HTML sends private no-store headers')
check('app_send_private_no_store_headers();' in logout, 'Logout and its redirects send private no-store headers')
check('app_send_no_store_headers();' in api, 'API responses send explicit no-store headers')
check('app_send_no_store_headers();' in error_response, 'Error pages send explicit no-store headers')
check('app_send_no_store_headers();' in bootstrap, 'Unhandled JSON exceptions send explicit no-store headers')

if "APP_VERSION = '1.7.0-dev.3'" in version:
    check(not (ROOT / 'database/migrations/007_v1_7_remember_token.sql').exists(), 'V1.7-D adds no Remember Token migration')
else:
    check(True, 'Later V1.7 checkpoints may add the planned Remember Token migration')
check((not (ROOT / 'database/migrations/008_v1_7_widget_height.sql').exists()) or ("APP_VERSION = '1.7.0-dev.7'" in version or "APP_VERSION = '1.7.0-dev.8'" in version or "APP_VERSION = '1.7.0-dev.9'" in version or "APP_VERSION = '1.7.0-dev.10'" in version or "APP_VERSION = '1.7.0'" in version), 'V1.7-D adds no height migration; V1.7-H may add the planned migration')
for rel in [
    'APPLY_NOTE_V1_7_D.md', 'CHECKLIST_FOR_USER_V1_7_D.md', 'UPDATED_FILES_V1_7_D.md',
    'docs/v1-7-d-implementation.md', 'docs/v1-7-d-files.md', 'docs/test-report-v1-7-d.md'
]:
    check((ROOT / rel).is_file(), f'{rel} exists')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
