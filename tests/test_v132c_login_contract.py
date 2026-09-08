from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def check(condition: bool, message: str) -> None:
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        raise AssertionError(message)


index = (ROOT / 'public' / 'index.php').read_text(encoding='utf-8')
session = (ROOT / 'app' / 'session.php').read_text(encoding='utf-8')
persistent = (ROOT / 'app' / 'persistent_login.php').read_text(encoding='utf-8')
login_view = (ROOT / 'app' / 'common' / 'common_login.php').read_text(encoding='utf-8')
api_v1 = (ROOT / 'public' / 'api_v1.php').read_text(encoding='utf-8')

check("auth_totp_status($authenticatedUserId)" in index, 'password Login checks TOTP status before choosing the final authentication path')
check("app_session_begin_pending_auth($authenticatedUserId, 'password', $rememberRequested)" in index, '2FA-enabled password Login enters pending rather than granting user_id')
check(index.index("app_session_begin_pending_auth($authenticatedUserId, 'password', $rememberRequested)") < index.index("persistent_login_issue_for_user($completedUserId)"), 'Remember Token issue for 2FA password Login occurs only after pending completion')
check("persistent_login_revoke_current();\n                    app_session_begin_pending_auth" in index, 'old persistent credential is revoked before a fresh password-origin 2FA challenge')
check("auth_totp_verify_enabled_code($pendingUserId, $code)" in index, 'TOTP challenge uses the enabled-code verifier')
check("auth_2fa_throttle_status($pendingUserId, $ipAddress)" in index and "auth_2fa_throttle_record_failure($pendingUserId, $ipAddress)" in index, 'TOTP challenge is protected by the dedicated rate limiter')
check("app_session_complete_pending_auth()" in index, 'successful TOTP completes only the recorded pending session')
check("$pendingSource === 'password'" in index, 'Remember-origin completion does not issue a duplicate persistent token')

check("app_session_begin_pending_auth($userId, 'remember'" in persistent, 'valid Remember Token for a 2FA user becomes pending')
check("if ($twoFactorEnabled)" in persistent and "app_session_login($userId);" in persistent, 'persistent restore preserves direct login only for users without 2FA')
check("app_session_has_pending_auth()" in persistent, 'persistent restoration refuses to run again while a factor challenge is pending')

check("'auth_pending_user_id'" in session and "'user_id' => $userId" not in session[session.index('function app_session_begin_pending_auth'):session.index('function app_session_complete_pending_auth')], 'pending-session payload does not contain authenticated user_id')
check("!app_session_has_pending_auth()" in session[session.index('$restored = false;'):session.index('app_csrf_token();', session.index('$restored = false;'))], 'session bootstrap suppresses Remember restoration while pending')
check('AUTH_2FA_PENDING_TIMEOUT' in session, 'pending state has an explicit bounded lifetime')

check('autocomplete="one-time-code"' in login_view and 'pattern="[0-9]{6}"' in login_view, '2FA form is a bounded six-digit one-time-code input')
check('name="token" value="2fa_cancel"' in login_view, '2FA challenge provides a CSRF-protected cancel path')

check("$userId = app_session_user_id();" in api_v1 and "if ($userId === null)" in api_v1, 'API v1 still derives authentication only from full session user_id')
check('auth_pending_user_id' not in api_v1, 'API v1 has no pending-auth bypass path')

print('All V1.32-C login/security contract checks passed.')
