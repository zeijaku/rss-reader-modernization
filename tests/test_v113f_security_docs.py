from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
checks = 0

def check(cond, label):
    global checks
    checks += 1
    if not cond:
        raise AssertionError(label)
    print(f'PASS: {label}')

def text(path):
    return (ROOT / path).read_text(encoding='utf-8')

stock = text('public/stock.php')
settings = text('public/settings.php')
api = text('public/api_v1.php')
session = text('app/session.php')
remember = text('app/remember_token.php')
fetch = text('app/http_fetch.php')
htaccess = text('public/.htaccess')
root_htaccess = text('.htaccess')
health = text('tools/healthcheck.php')
install = text('docs/installation.md')
security = text('docs/security.md')
schema = text('database/schema.sql')
review = text('docs/v1.13-f-security-review.md')
docs_index = text('docs/README.md')
migration009 = text('database/migrations/009_v1_9_mail_account.sql')

# New entry points / auth boundary
check('app_session_start();' in stock, 'Stock uses central session bootstrap')
check('if ($currentUserId === null)' in stock and 'view_login(' in stock, 'Stock stops at login view when unauthenticated')
check(stock.index('if ($currentUserId === null)') < stock.index('search_stock('), 'Stock auth boundary occurs before Stock query path')
check('app_session_start();' in settings, 'Settings uses central session bootstrap')
check("if ($currentUserId === null)" in settings and "header('Location: ./', true, 302);" in settings, 'Settings redirects unauthenticated users')

# API / CSRF / Session
check("REQUEST_METHOD" in api and "POST is required" in api, 'API remains POST-only')
check('app_session_user_id()' in api and 'Authentication is required.' in api, 'API requires authenticated session user')
check('app_csrf_is_valid($csrfToken)' in api, 'API validates CSRF before dispatch')
check("'session.use_strict_mode' => '1'" in session, 'Session strict mode remains enabled')
check("'session.cookie_httponly' => '1'" in session, 'Session HttpOnly remains enabled')
check("'session.cookie_samesite' => 'Lax'" in session, 'Session SameSite remains Lax')
check("'secure' => app_request_is_https()" in session, 'Session cookie Secure follows verified HTTPS request')
check('session_regenerate_id(true)' in session, 'Session identifier rotation remains enabled')
check('hash_equals($sessionToken, $submittedToken)' in session, 'CSRF comparison uses hash_equals')

# Remember token
check('hash_equals($storedHash, $candidateHash)' in remember, 'Remember token validator comparison uses hash_equals')
check('random_bytes(REMEMBER_TOKEN_VALIDATOR_BYTES)' in remember, 'Remember token validator uses random_bytes')
check('remember_token_hash_validator' in remember, 'Remember token stores validator hash path')

# SSRF/TLS
check('CURLOPT_FOLLOWLOCATION => false' in fetch, 'SSRF transport keeps automatic redirect disabled')
check('CURLOPT_SSL_VERIFYPEER => true' in fetch, 'TLS peer verification remains enabled')
check('CURLOPT_SSL_VERIFYHOST => 2' in fetch, 'TLS hostname verification remains enabled')
check('CURLOPT_RESOLVE' in fetch, 'Validated DNS destination remains pinned')

# CSP/HSTS decision
check("Content-Security-Policy \"frame-ancestors 'self'; base-uri 'self'; form-action 'self'\"" in htaccess, 'Existing limited CSP remains explicit')
check('Strict-Transport-Security' not in htaccess and 'Strict-Transport-Security' not in root_htaccess, 'HSTS is not forced without production HTTPS confirmation')
check('既存のinline script/style' in security and 'HSTS' in security, 'CSP/HSTS deployment decision is documented')

# Runtime / private boundary
for marker in [
    'Debug mode:',
    'APP_HASH_KEY configured:',
    'APP_HASH_KEY minimum length:',
    'Session storage outside public/:',
    'Feed cache outside public/:',
    'Login throttle outside public/:',
    'Private config outside public/:',
]:
    check(marker in health, f'Healthcheck reports non-secret runtime marker: {marker}')
check("APP_DEBUG must be disabled in production." in health, 'Healthcheck rejects production debug mode')
check('APP_HASH_KEY' in health and 'INI_HASH_KEY' in health, 'Healthcheck checks APP_HASH_KEY state without printing its value')
check("RewriteRule ^(?:app|config|tools|var)(?:/|$) - [F,L,NC]" in root_htaccess, 'Application-root htaccess blocks private directories')

# DB build documentation - no DDL change, current sequencing clarified
check('through migration 008 / V1.7' in schema, 'schema.sql header identifies its actual base level')
for migration in ['009_v1_9_mail_account.sql','010_v1_10_links.sql','011_v1_11_stock_tags.sql','012_v1_12_feed_keywords.sql']:
    check(migration in install, f'Fresh install documentation includes {migration}')
check('15 table' in install, 'Fresh install documentation states final table count')
check('009〜012' in docs_index and 'installation.md' in docs_index, 'Documentation index points fresh installs to current schema plus migrations')
check('v1_9_b_preflight.sql' not in migration009, 'Migration 009 no longer references a nonexistent preflight file')
check('DDLは変更していません' in review, 'V1.13-F records that DB DDL is unchanged')

print(f'All {checks} V1.13-F security/documentation checks passed.')
