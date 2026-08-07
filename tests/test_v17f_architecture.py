#!/usr/bin/env python3
from pathlib import Path

from version_test_utils import is_later_application_release, is_later_visible_label
ROOT = Path(__file__).resolve().parents[1]
checks=[]
def check(cond, msg):
    checks.append(bool(cond)); print(('PASS' if cond else 'FAIL') + ': ' + msg)

version=(ROOT/'app/version.php').read_text()
bootstrap=(ROOT/'app/bootstrap.php').read_text()
persistent=(ROOT/'app/persistent_login.php').read_text()
session=(ROOT/'app/session.php').read_text()
index=(ROOT/'public/index.php').read_text()
login=(ROOT/'app/common/common_login.php').read_text()
css=(ROOT/'public/css/auth.css').read_text()
logout=(ROOT/'public/logout.php').read_text()
account=(ROOT/'app/account_settings.php').read_text()
api=(ROOT/'app/api.php').read_text()
remember=(ROOT/'app/remember_token.php').read_text()

check(any(v in version for v in ["APP_VERSION = '1.7.0-dev.5'", "APP_VERSION = '1.7.0-dev.6'", "APP_VERSION = '1.7.0-dev.7'", "APP_VERSION = '1.7.0-dev.8'", "APP_VERSION = '1.7.0-dev.9'", "APP_VERSION = '1.7.0-dev.10'", "APP_VERSION = '1.7.0'"]) or is_later_application_release(version, (1, 7, 0)), 'Application Version is V1.7-F or V1.7-G')
check(any(v in version for v in ["APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-F / R1'", "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-G / R1'", "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-H / R1'", "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-H / R2'", "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-H / R3'", "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-H / R4'", "APP_VERSION_LABEL = 'RSS Reader Modernization 1.7.0'"]) or is_later_visible_label(version, (1, 7, 0)), 'Application Label is V1.7-F or V1.7-G')
check("require_once __DIR__ . '/persistent_login.php';" in bootstrap, 'Bootstrap loads persistent login integration')
for fragment in [
    "PERSISTENT_LOGIN_COOKIE_NAME = 'iguguru_remember'",
    "function persistent_login_is_requested(",
    "function persistent_login_set_cookie(",
    "function persistent_login_clear_cookie(",
    "function persistent_login_issue_for_user(",
    "function persistent_login_restore_session(",
    "'httponly' => true", "'samesite' => 'Lax'", "'secure' => app_request_is_https()",
]:
    check(fragment in persistent, f'Persistent login integration contains: {fragment}')
check('cookieValue' not in ''.join(line for line in persistent.splitlines() if 'error_log' in line), 'Persistent token values are not included in logs')
check("persistent_login_restore_session()" in session and '$authenticationExpired && !$restored' in session, 'Expired or anonymous sessions attempt token restore before showing expiry notice')
check("name=\"remember_me\" value=\"1\"" in login and '30日間ログイン状態を維持' in login, 'Login UI exposes an explicit 30-day checkbox')
check('共用端末では選択しないでください' in login, 'Login UI warns against persistent login on shared devices')
check('.auth-remember-input:focus-visible' in css and 'min-height: 44px' in css, 'Remember checkbox has keyboard focus and mobile-sized interaction area')
check('persistent_login_is_requested($_POST[\'remember_me\'] ?? null)' in index, 'Login POST parses only the explicit checkbox value')
check('persistent_login_issue_for_user($authenticatedUserId)' in index, 'Successful opted-in login issues a persistent token')
check('persistent_login_revoke_current();' in index, 'Successful login without opt-in revokes any current-browser token')
check(logout.index('persistent_login_revoke_current();') < logout.index('app_session_logout();'), 'Logout revokes Remember Token before destroying the session')
check('remember_token_revoke_user($userId, $conn);' in account, 'Password update revokes all user tokens in the password transaction')
check('persistent_login_clear_cookie();' in api, 'Successful password API response clears the current browser cookie')
check('function remember_token_revoke_user(int $userId, ?PDO $pdo = null)' in remember, 'User-wide token revocation can share the password transaction')
check((ROOT/'database/migrations/007_v1_7_remember_token.sql').exists(), 'Remember Token migration remains included')
check((not (ROOT/'database/migrations/008_v1_7_widget_height.sql').exists()) or ("APP_VERSION = '1.7.0-dev.7'" in version or "APP_VERSION = '1.7.0-dev.8'" in version or "APP_VERSION = '1.7.0-dev.9'" in version or "APP_VERSION = '1.7.0-dev.10'" in version or "APP_VERSION = '1.7.0'" in version) or is_later_application_release(version, (1, 7, 0)), 'Widget height remains deferred through G or is implemented in H')

for rel in ['APPLY_NOTE_V1_7_F.md','CHECKLIST_FOR_USER_V1_7_F.md','UPDATED_FILES_V1_7_F.md','docs/v1-7-f-implementation.md','docs/v1-7-f-files.md','docs/test-report-v1-7-f.md']:
    check((ROOT/rel).exists(), f'{rel} exists')

passed=sum(checks); failed=len(checks)-passed
print(f'RESULT: PASS {passed} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
