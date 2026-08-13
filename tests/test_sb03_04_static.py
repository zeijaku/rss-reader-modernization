from pathlib import Path
import re
import sys

from dashboard_source_utils import dashboard_source
root = Path(__file__).resolve().parents[1]
checks=[]
def check(cond,msg):
    print(('PASS' if cond else 'FAIL')+': '+msg)
    checks.append(bool(cond))

index = dashboard_source(root)
api=(root/'public/api_v1.php').read_text()
logout=(root/'public/logout.php').read_text()
session=(root/'app/session.php').read_text()
auth=(root/'app/auth.php').read_text()
throttle=(root/'app/login_throttle.php').read_text()
db=(root/'app/common/common_db.php').read_text()
func=(root/'app/common/common_func.php').read_text()
login=(root/'app/common/common_login.php').read_text()
conf=(root/'app/common/common_conf.php').read_text()

check('7776000' not in index and 'session.cookie_lifetime' not in index, '90-day Legacy session policy removed from index')
check("'session.use_strict_mode' => '1'" in session, 'strict session mode configured')
check("'session.use_only_cookies' => '1'" in session, 'cookie-only sessions configured')
check("'session.use_trans_sid' => '0'" in session, 'trans-sid disabled')
check("'httponly' => true" in session and "'samesite' => 'Lax'" in session, 'HttpOnly and SameSite cookie policy present')
check('app_request_is_https()' in session and "'secure' => app_request_is_https()" in session, 'Secure cookie follows HTTPS request')
check('session_regenerate_id(true)' in session and 'app_session_login' in index, 'login rotates session identifier')
check("'authenticated_at'" in session and "'last_activity'" in session, 'idle and absolute session metadata present')
check("$ui['" in index and "$_SESSION['conf_" not in index and "$_SESSION['conf_" not in api, 'mutable UI settings removed from session')
check('user_ui_config($currentUserId)' in index, 'authenticated UI settings are reloaded from DB')
check('app_session_start();' in index and 'app_session_start();' in api and 'app_session_start();' in logout, 'session bootstrap is centralized across entry points')
check('method="post" action="./logout.php"' in index, 'logout UI uses POST')
check('app_csrf_token()' in index and 'app_csrf_is_valid' in logout, 'logout is CSRF protected')
check("!== 'POST'" in logout and '405' in logout, 'logout rejects non-POST methods')
check('password_hash(' in auth and 'password_verify(' in auth and 'password_needs_rehash(' in auth, 'modern password API used')
check('hash_unique(' not in index and 'search_auth_user(' not in index, 'Legacy combined credential lookup removed from login flow')
check('hash_unique(' not in func and 'search_auth_user(' not in db, 'dead Legacy credential functions removed')
check('WHERE user_email = :email AND user_flag = 0' in db and 'LIMIT 2' in db, 'identity lookup checks active flag and duplicate ambiguity')
check('user_identity_exists' in auth and 'user_identity_exists' in db, 'registration checks duplicate identity independent of password')
check('REGISTRATION_ENABLED' in auth and 'REGISTRATION_ENABLED' in index and 'REGISTRATION_ENABLED' in conf and 'registrationEnabled' in login, 'registration enable switch implemented')
check('AUTH_PASSWORD_MIN_LENGTH' in auth and 'AUTH_PASSWORD_MAX_LENGTH' in auth, 'new registration password bounds implemented')
check('login_throttle_status' in index and 'login_throttle_record_failure' in index and 'login_throttle_record_success' in index, 'login flow uses rate limiting')
check('flock(' in throttle and 'LOGIN_RATE_BLOCK_SECONDS' in throttle, 'rate limiter uses locked private state and temporary blocks')
check("dirname(__DIR__) . '/var/security/login-throttle'" in throttle, 'rate limit state is outside DocumentRoot')
check('FILTER_SANITIZE_SPECIAL_CHARS' not in index.split("<!doctype html>",1)[0], 'credentials are not HTML-sanitized before authentication')
check('autocomplete="current-password"' in login and 'autocomplete="new-password"' in login, 'auth forms use appropriate password autocomplete hints')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} SB-03/SB-04 static checks passed.')
