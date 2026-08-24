#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
checks: list[tuple[str, bool]] = []


def check(name: str, condition: bool) -> None:
    checks.append((name, bool(condition)))


def text(rel: str) -> str:
    return (ROOT / rel).read_text(encoding='utf-8')

conf = text('app/common/common_conf.php')
throttle = text('app/login_throttle.php')
index = text('public/index.php')
stock = text('public/stock.php')
api = text('public/api_v1.php')
public_ht = text('public/.htaccess')
local_example = text('config/local.php.example')
env_example = text('config/.env.example')

check('API request byte limit has a bounded default',
      "APP_API_MAX_REQUEST_BYTES" in conf and "'1048576'" in conf and 'min(4194304' in conf)
check('API reads Content-Length through a dedicated helper', 'function api_request_content_length(): ?int' in api)
check('API rejects oversized authenticated requests with HTTP 413',
      '$contentLength > APP_API_MAX_REQUEST_BYTES' in api and "'request_too_large'" in api and ', 413)' in api)
check('API size guard remains after CSRF validation',
      api.find('app_csrf_is_valid') < api.find('$contentLength = api_request_content_length()') < api.find("$action = isset($_POST['action'])"))

for constant, default in [
    ('REGISTRATION_RATE_WINDOW', '900'),
    ('REGISTRATION_RATE_MAX_IP', '10'),
    ('REGISTRATION_RATE_BLOCK_SECONDS', '900'),
]:
    check(f'{constant} has a safe optional default', constant in conf and f"'{default}'" in conf)

check('registration throttle stores only HMAC-keyed IP scope',
      "login_throttle_mutate('registration-ip', $ipAddress" in throttle and 'hash_hmac' in throttle)
check('registration throttle counts successful attempts too',
      'Successful registrations are also counted' in throttle and "$state['failures'][] = $now" in throttle)
check('registration throttle exposes no submitted email argument',
      'function registration_throttle_consume(string $ipAddress' in throttle and 'string $email' not in throttle.split('function registration_throttle_consume', 1)[1])
for page, source in [('Dashboard/login page', index), ('Stock/login page', stock)]:
    check(f'{page} consumes registration throttle', 'registration_throttle_consume($ipAddress)' in source)
    check(f'{page} keeps throttling failure generic', "['ok' => false, 'reason' => 'registration_failed']" in source)
    check(f'{page} bypasses throttle storage when registration disabled',
          '$registrationThrottle = REGISTRATION_ENABLED' in source)

csp_match = re.search(r'Header always set Content-Security-Policy "([^"]+)"', public_ht)
csp = csp_match.group(1) if csp_match else ''
for directive in ["frame-ancestors 'self'", "base-uri 'self'", "form-action 'self'", "object-src 'none'"]:
    check(f'CSP contains {directive}', directive in csp)

php_files = sorted(p.name for p in (ROOT / 'public').glob('*.php'))
expected_endpoints = sorted([
    'api_v1.php', 'calendar_color_api.php', 'connection_probe.php', 'error.php', 'index.php',
    'logout.php', 'settings.php', 'stock.php',
])
check('public PHP endpoint inventory matches the explicit allowlist', php_files == expected_endpoints)
whitelist_line = next((line.strip() for line in public_ht.splitlines() if 'V1.19-C' not in line and 'RewriteRule' in line and 'api_v1\\.php' in line), '')
check('public .htaccess denies unlisted PHP entry points', bool(whitelist_line) and '- [F,L,NC]' in whitelist_line)
for endpoint in expected_endpoints:
    escaped = endpoint.replace('.', '\\.')
    check(f'public PHP whitelist includes {endpoint}', escaped in whitelist_line)

first_party = ''
for base in [ROOT / 'public', ROOT / 'app']:
    for pattern in ('*.php', '*.js'):
        for path in base.rglob(pattern):
            if path.name.endswith('.min.js'):
                continue
            first_party += '\n' + path.read_text(encoding='utf-8', errors='replace')
check("first-party runtime contains no <object>, <embed>, or <applet> element",
      re.search(r'<\s*(?:object|embed|applet)\b', first_party, flags=re.I) is None)

for example_name, source in [('local.php.example', local_example), ('.env.example', env_example)]:
    for key in ['REGISTRATION_RATE_WINDOW', 'REGISTRATION_RATE_MAX_IP', 'REGISTRATION_RATE_BLOCK_SECONDS', 'APP_API_MAX_REQUEST_BYTES']:
        check(f'{example_name} documents optional {key}', key in source)

failed = [name for name, ok in checks if not ok]
for name, ok in checks:
    print(f"[{'PASS' if ok else 'FAIL'}] {name}")
print(f"RESULT: PASS {len(checks)-len(failed)} / FAIL {len(failed)} / SKIP 0")
raise SystemExit(1 if failed else 0)
