from pathlib import Path
import re, sys

ROOT = Path(__file__).resolve().parents[1]
checks = []
def check(cond, msg):
    checks.append((bool(cond), msg))
    print(('PASS' if cond else 'FAIL') + ': ' + msg)

migration = (ROOT/'database/migrations/023_v1_32_auth_session.sql').read_text()
gitignore = (ROOT/'.gitignore').read_text()
conf = (ROOT/'app/common/common_conf.php').read_text()
registry = (ROOT/'app/auth_session_registry.php').read_text()
session = (ROOT/'app/session.php').read_text()
persistent = (ROOT/'app/persistent_login.php').read_text()
remember = (ROOT/'app/remember_token.php').read_text()
bootstrap = (ROOT/'app/bootstrap.php').read_text()
api = (ROOT/'public/api_v1.php').read_text()
api_mod = (ROOT/'app/api/account_session.php').read_text()
view = (ROOT/'app/view/account_security.php').read_text()
js = (ROOT/'public/js/account-2fa.js').read_text()
acct = (ROOT/'app/account_settings.php').read_text()
security = (ROOT/'app/account_security.php').read_text()

check('023_v1_32_auth_session.sql' in gitignore, 'Migration 023 is explicitly allowlisted despite global *.sql ignore')
check("'auth_session'" in conf, 'auth_session is a known bounded DB table name')
for col in ['auth_session_token_hash','auth_session_remember_selector','auth_session_client_label','auth_session_last_seen_at','auth_session_expires_at','auth_session_revoked_at']:
    check(col in migration, f'Migration 023 defines {col}')
check('UNIQUE KEY `uq_auth_session_token_hash`' in migration, 'registry token hashes are unique')
check('auth_session_ip' not in migration.lower() and 'remote_addr' not in registry.lower(), 'Session Registry does not persist IP addresses')
check('auth_session_remember_validator' not in migration.lower() and 'remember_token_validator_hash' not in migration.lower(), 'Migration does not add a Remember validator column')
check('$_SESSION' not in registry, 'raw PHP Session access remains confined outside the Registry module')
check("require_once __DIR__ . '/auth_session_registry.php';" in bootstrap and bootstrap.index('auth_session_registry.php') < bootstrap.index("'/session.php'"), 'Session Registry loads before session lifecycle functions')
check('auth_session_registry_validate_current' in session and 'registryRevoked' in session, 'authenticated requests enforce DB revocation before application access')
check('auth_session_registry_adopt_current_remember_selector' in registry and "reason' => 'adopted" in registry, 'pre-G authenticated Sessions are adopted once and preserve the current Remember selector')
check('auth_session_registry_revoke_current' in session, 'explicit Logout marks the current Registry row revoked')
check('auth_pending_remember_selector' in session and 'auth_session_registry_bind_remember_selector' in session, 'Remember-restored 2FA pending flow preserves selector only until full authentication')
check('auth_session_registry_bind_remember_selector' in persistent, 'issued/restored Remember tokens are bound to the logical Session Registry row')
check('remember_token_revoke_selector_for_user' in remember and 'remember_token_revoke_user_except_selector' in remember, 'bounded Remember revocation helpers support Session Management')
check("'/app/api/account_session.php'" in api and "str_starts_with($action, 'account.session.')" in api, 'Session Management API is routed under existing auth+CSRF boundary')
check("'account.session.revoke'" in api_mod and "'account.session.revoke_others'" in api_mod, 'API supports individual and all-other Session revocation')
check('current_session' in api_mod, 'API refuses current-session remote revoke')
check('data-account-session-management' in view and 'data-account-session-current' in view, 'shared Account Security UI lists Sessions and marks current Browser')
check('auth_session_registry_list' in view and 'Migration 023' in view, 'Dashboard and Settings use the same Session Registry view state')
check('auth_session_token_hash' not in view and 'auth_session_remember_selector' not in view, 'Registry token hash and Remember selector are never rendered')
check('account.session.revoke' in js and 'account.session.revoke_others' in js, 'browser uses dedicated Session revocation actions')
check('localStorage' not in js and 'sessionStorage' not in js and 'console.' not in js, 'Session/Security browser code does not persist or log authentication material')
check('auth_session_registry_revoke_user' in acct and 'remember_token_revoke_user' in acct, 'Password change invalidates Remember tokens and other active Sessions')
check('auth_session_registry_revoke_user' in security and 'remember_token_revoke_user' in security, '2FA disable invalidates Remember tokens and other active Sessions')
check('session.save_path' not in registry and 'session_set_save_handler' not in registry, 'G keeps PHP Session contents filesystem-backed instead of moving them into DB')

# New modules may only read/write Session state via app/session.php.
for rel in ['app/auth_session_registry.php','app/api/account_session.php']:
    text=(ROOT/rel).read_text()
    check('$_SESSION' not in text, f'{rel} does not directly access raw PHP Session state')

if not all(ok for ok,_ in checks):
    sys.exit(1)
