#!/usr/bin/env python3
from pathlib import Path
import re

from dashboard_source_utils import dashboard_source

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


public_ht = (ROOT / 'public/.htaccess').read_text(encoding='utf-8')
cache = (ROOT / 'app/response_cache.php').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app/bootstrap.php').read_text(encoding='utf-8')
error_response = (ROOT / 'app/error_response.php').read_text(encoding='utf-8')
session = (ROOT / 'app/session.php').read_text(encoding='utf-8')
index = dashboard_source(ROOT)
logout = (ROOT / 'public/logout.php').read_text(encoding='utf-8')
api = (ROOT / 'public/api_v1.php').read_text(encoding='utf-8')

# Current security policy. These are meaningful browser/security boundaries rather
# than historical version/document markers.
check('<IfModule mod_headers.c>' in public_ht, 'security/cache headers are guarded by mod_headers availability')
for header, value in [
    ('X-Content-Type-Options', 'nosniff'),
    ('Referrer-Policy', 'strict-origin-when-cross-origin'),
    ('X-Frame-Options', 'SAMEORIGIN'),
]:
    check(f'Header always set {header} "{value}"' in public_ht, f'{header} policy remains configured')
check("frame-ancestors 'self'" in public_ht and "base-uri 'self'" in public_ht and "form-action 'self'" in public_ht,
      'CSP keeps framing/base/form protections')

# Static assets may be long-lived only because their URL carries a revision.
check(re.search(r'<FilesMatch "\\\.\(\?:css\\\|js\)\\\$">.*?Cache-Control.*?immutable', public_ht, re.S) is not None,
      'CSS/JavaScript cache policy remains immutable/versioned')

# Private/dynamic responses must stay non-cacheable.
check('function app_send_private_no_store_headers(): void' in cache, 'private HTML no-store helper exists')
check("Cache-Control: private, no-store" in cache, 'private HTML uses private no-store policy')
check('function app_send_no_store_headers(): void' in cache, 'API/error no-store helper exists')
check("Cache-Control: no-store" in cache, 'API/error responses use no-store policy')
check("'session.cache_limiter' => ''" in session, 'PHP automatic cache limiter does not override explicit policy')

for text, marker, message in [
    (index, 'app_send_private_no_store_headers();', 'Dashboard/login HTML applies private no-store policy'),
    (logout, 'app_send_private_no_store_headers();', 'Logout applies private no-store policy'),
    (api, 'app_send_no_store_headers();', 'API applies no-store policy'),
    (error_response, 'app_send_no_store_headers();', 'Error response applies no-store policy'),
    (bootstrap, 'app_send_no_store_headers();', 'Unhandled JSON error path applies no-store policy'),
]:
    check(marker in text, message)

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
