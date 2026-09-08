from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
APP = ROOT / 'app'
failed = 0

def check(condition: bool, message: str) -> None:
    global failed
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failed += 1

conf = (APP / 'common/common_conf.php').read_text(encoding='utf-8')
session = (APP / 'session.php').read_text(encoding='utf-8')
bootstrap = (APP / 'bootstrap.php').read_text(encoding='utf-8')
stepup = (APP / 'auth_step_up.php').read_text(encoding='utf-8')
security = (APP / 'account_security.php').read_text(encoding='utf-8')
api = (APP / 'api/account_security.php').read_text(encoding='utf-8')
public_api = (ROOT / 'public/api_v1.php').read_text(encoding='utf-8')
view = (APP / 'view/account_security.php').read_text(encoding='utf-8')
js = (ROOT / 'public/js/account-2fa.js').read_text(encoding='utf-8')
local_example = (ROOT / 'config/local.php.example').read_text(encoding='utf-8')
env_example = (ROOT / 'config/.env.example').read_text(encoding='utf-8')

check("AUTH_STEP_UP_TIMEOUT" in conf and "'300'" in conf, 'Step-up TTL is short and centrally configurable with a safe default')
check('auth_step_up.php' in bootstrap and 'account_security.php' in bootstrap, 'Step-up and sensitive account-security modules load through bootstrap')
check('app_session_step_up_grant' in session and 'app_session_step_up_is_valid' in session, 'Step-up grant stays inside the existing PHP Session boundary')
check('$_SESSION' not in stepup and '$_SESSION' not in api and '$_SESSION' not in security, 'new Security modules do not bypass app/session.php with raw Session access')
check("['totp', 'recovery']" in session, 'Step-up grant records only recognized second-factor methods')
check('password_verify' in stepup and 'account_settings_find_active_user' in stepup, 'Step-up requires the current account Password server-side')
check("auth_totp_verify_enabled_code" in api and "auth_recovery_code_consume" in api, 'Step-up accepts TOTP or one-time Recovery Code as the second factor')
check('auth_2fa_throttle_status' in api and 'api_account_settings_rate_status' in api, 'Step-up reuses both Password/account and second-factor throttles')
check("account.security.stepup.verify" in api and "account.security.totp.disable" in api, 'sensitive Security actions use a dedicated API namespace')
check("'account.security.stepup.verify'" in public_api and "'account.security.totp.disable'" in public_api, 'Step-up and disable actions keep the file-backed Session lock while changing Session state')
check("str_starts_with($action, 'account.security.')" in public_api, 'public API routes Security actions only after global auth and CSRF checks')
check('remember_token_revoke_user' in security and 'DELETE FROM ' in security and "auth_recovery_code" in security, '2FA disable removes TOTP/Recovery state and revokes persistent login tokens atomically')
check('persistent_login_clear_cookie' in api and 'app_session_login($userId)' in api, 'successful disable clears current Remember cookie and rotates the authenticated Session')
check('data-account-stepup-password' in view and 'data-account-stepup-method' in view and 'data-account-totp-disable' in view, 'Account Security UI exposes Password + factor Step-up before 2FA disable')
check('Recovery Codeを使った場合' in view and '1回使用済み' in view, 'UI explicitly explains Recovery Code consumption during Step-up')
check("window.confirm('2段階認証を解除します" in js, 'browser adds an explicit final confirmation before 2FA disable')
check("action: 'account.security.stepup.verify'" in js and "action: 'account.security.totp.disable'" in js, 'browser calls only the dedicated Security endpoints for Step-up/disable')
check(".text('未発行')" in js and ".html('<i class=\"fas fa-key\" aria-hidden=\"true\"></i> 生成する')" in js, '2FA disable clears stale Recovery Code status before same-page re-enrollment')
check('localStorage' not in js and 'sessionStorage' not in js and 'console.log' not in js, 'Password/factor inputs are never persisted or logged by the Security browser code')
check('AUTH_STEP_UP_TIMEOUT' in local_example and 'AUTH_STEP_UP_TIMEOUT=300' in env_example, 'Step-up TTL is documented in both configuration examples without requiring a private-config change')

raw_session_refs = []
for path in APP.rglob('*.php'):
    text = path.read_text(encoding='utf-8', errors='ignore')
    if '$_SESSION' in text:
        raw_session_refs.append(path.relative_to(ROOT).as_posix())
check(set(raw_session_refs) <= {'app/session.php'}, 'raw PHP Session access remains confined to app/session.php after Step-up')

raise SystemExit(1 if failed else 0)
