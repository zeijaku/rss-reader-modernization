from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
login = (ROOT / 'app/common/common_login.php').read_text(encoding='utf-8')
auth = (ROOT / 'app/auth.php').read_text(encoding='utf-8')
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
session = (ROOT / 'app/session.php').read_text(encoding='utf-8')
logout = (ROOT / 'public/logout.php').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app/bootstrap.php').read_text(encoding='utf-8')
api = (ROOT / 'public/api_v1.php').read_text(encoding='utf-8')
error = (ROOT / 'app/error_response.php').read_text(encoding='utf-8')
root_htaccess = (ROOT / '.htaccess').read_text(encoding='utf-8')
public_htaccess = (ROOT / 'public/.htaccess').read_text(encoding='utf-8')
css = (ROOT / 'public/css/auth.css').read_text(encoding='utf-8')
js = (ROOT / 'public/js/auth.js').read_text(encoding='utf-8')

checks = []
def check(condition, message):
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

# Dedicated auth UI and behavior.
check('form-control' not in login and 'btn-primary' not in login and '<div class="panel' not in login, 'authentication markup no longer relies on Bootstrap sample classes')
check('./css/auth.css' in index and './js/auth.js' in login, 'authentication page uses dedicated CSS and JavaScript')
check(login.count('data-password-toggle') == 2 and 'aria-pressed="false"' in login, 'both password fields have accessible visibility toggles')
check("input.type = reveal ? 'text' : 'password'" in js, 'password visibility is changed without modifying authentication data')
check("form.dataset.submitting === 'true'" in js and 'event.preventDefault();' in js and 'submit.disabled = true' in js, 'double submission is blocked client-side')
check('method="post" action="./"' in login and 'type="submit"' in login, 'forms preserve native POST and Enter-key submission')
check('@media (max-width: 520px)' in css and ':focus-visible' in css and 'min-height: 46px' in css, 'auth CSS supports smartphone controls and visible focus')

# Honeypot without weakening CSRF/throttle/auth.
field_match = re.search(r"const AUTH_FORM_TRAP_FIELD = '([^']+)';", auth)
field_name = field_match.group(1) if field_match else ''
check(bool(field_name) and all(word not in field_name.lower() for word in ['honeypot', 'bot', 'trap']), 'form trap uses a neutral field name')
check(login.count('name="<?php echo AUTH_FORM_TRAP_FIELD; ?>"') == 2, 'login and registration both include the form trap')
check(login.count('tabindex="-1"') >= 2 and login.count('aria-hidden="true"') >= 2 and login.count('autocomplete="off"') >= 2, 'form trap stays out of keyboard, screen-reader and autofill flow')
check('.auth-decoy' in css and 'left: -10000px' in css and 'display: none' not in css[css.find('.auth-decoy'):css.find('@media (max-width: 520px)')], 'form trap is visually hidden without relying on display:none')
check('auth_form_trap_is_filled' in auth and '$trapValue = $_POST[AUTH_FORM_TRAP_FIELD] ?? null;' in index and 'auth_form_trap_is_filled($trapValue)' in index, 'form trap is checked server-side')
check(index.index('app_csrf_is_valid') < index.index("if ($token === 'login' && !$authCsrfInvalid)"), 'CSRF remains the first authentication gate')
check('login_throttle_status' in index and 'login_throttle_record_failure' in index, 'Login Throttle remains active for suspicious submissions')
check('Login failed. Please check your email address and password.' in index and 'Bot' not in index, 'trap detection uses the existing generic login failure')
check('access_log' not in auth and 'error_log' not in auth, 'form trap helper does not log entered values')

# Flash and session boundaries.
check("app_flash_set('auth_notice', 'ログアウトしました。', 'success')" in logout, 'normal logout sets a one-time success notice')
check(logout.rindex('app_session_logout();') < logout.rindex('app_session_start();') < logout.index("app_flash_set('auth_notice'"), 'old authenticated session is destroyed before creating the notice session')
check('session_destroy();' in session and "session_id('');" in session and "unset($_COOKIE[$cookieName])" in session, 'logout clears server session, local id and cookie state')
check('セッションの有効期限が切れました。もう一度ログインしてください。' in session, 'session expiry has distinct wording')
check("app_flash_take('auth_notice')" in index, 'authentication notice is consumed once on the login page')
check('auth_notice' not in root_htaccess and '?logout=' not in logout, 'logout notice does not use a persistent query parameter')

# Common error and API boundary.
for status in [403, 404, 500, 503]:
    check(f'ErrorDocument {status} /public/error.php' in root_htaccess, f'root server htaccess maps HTTP {status} to common error page')
    check(f'ErrorDocument {status} /public/error.php' in public_htaccess, f'public htaccess maps HTTP {status} to common error page')
check('RewriteRule ^(?:app|config|tools|var)(?:/|$) - [F,L,NC]' in root_htaccess, 'server-specific private-directory restriction is preserved')
check('RewriteCond %{DOCUMENT_ROOT}/public/$1 -f' in root_htaccess and 'RewriteRule ^(.+)$ public/$1 [L]' in root_htaccess, 'server-specific internal public rewrite is preserved')
check('RewriteRule ^ - [R=404,L]' in public_htaccess, 'unknown public path returns a real 404 instead of index.php 200')
check("require_once dirname(__DIR__) . '/app/error_response.php';" in (ROOT / 'public/error.php').read_text(encoding='utf-8'), 'public error endpoint has a single minimal application dependency')
check('common_db.php' not in error and 'session.php' not in error and 'bootstrap.php' not in error, 'common error renderer has no DB, Session or application bootstrap dependency')
check("define('APP_RESPONSE_FORMAT', 'json');" in api and "APP_RESPONSE_FORMAT === 'json'" in bootstrap, 'API bootstrap failures remain structured JSON')
check("http_response_code($status)" in error and 'noindex,nofollow' in error and 'X-Robots-Tag: noindex, nofollow' in error, 'error renderer preserves status and blocks indexing')
check('$exception->getMessage()' not in bootstrap[bootstrap.find("echo json_encode"):], 'exception detail is not emitted in the response')

# Scope boundaries.
check(not (ROOT / 'package.json').exists(), 'Stage 1 adds no build environment or framework dependency')
check(not any((ROOT / 'database').glob('*v1_2*')), 'Stage 1 adds no V1.2 database migration')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} V1.2-A architecture checks passed.')
