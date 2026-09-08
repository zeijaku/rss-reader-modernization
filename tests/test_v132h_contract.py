from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
checks = []
def check(cond, msg):
    checks.append((bool(cond), msg))
    print(('PASS' if cond else 'FAIL') + ': ' + msg)

migration = (ROOT/'database/migrations/024_v1_32_auth_audit_log.sql').read_text()
gitignore = (ROOT/'.gitignore').read_text()
conf = (ROOT/'app/common/common_conf.php').read_text()
bootstrap = (ROOT/'app/bootstrap.php').read_text()
audit = (ROOT/'app/auth_audit_log.php').read_text()
index = (ROOT/'public/index.php').read_text()
logout = (ROOT/'public/logout.php').read_text()
persistent = (ROOT/'app/persistent_login.php').read_text()
totp_api = (ROOT/'app/api/account_totp.php').read_text()
security_api = (ROOT/'app/api/account_security.php').read_text()
session_api = (ROOT/'app/api/account_session.php').read_text()
account = (ROOT/'app/account_settings.php').read_text()
view = (ROOT/'app/view/account_security.php').read_text()

check('024_v1_32_auth_audit_log.sql' in gitignore, 'Migration 024 is explicitly allowlisted despite global *.sql ignore')
check("'auth_audit_log'" in conf, 'auth_audit_log is a bounded DB table name')
check("require_once __DIR__ . '/auth_audit_log.php';" in bootstrap, 'Authentication Audit module is loaded by bootstrap')
for col in ['auth_audit_log_user_id','auth_audit_log_identity_hash','auth_audit_log_event','auth_audit_log_result','auth_audit_log_method','auth_audit_log_client_label','auth_audit_log_ip_hash','auth_audit_log_created_at']:
    check(col in migration, f'Migration 024 defines {col}')
check('DROP ' not in migration.upper() and 'TRUNCATE ' not in migration.upper() and 'DELETE ' not in migration.upper() and 'ALTER TABLE' not in migration.upper(), 'Migration 024 is additive and non-destructive')
check('raw IP' in migration and 'full User-Agent' in migration, 'Migration documents privacy boundary for IP and User-Agent')
check("hash_hmac('sha256', 'auth-audit-ip:v1:'" in audit, 'REMOTE_ADDR is transformed into a keyed digest')
check('HTTP_X_FORWARDED_FOR' not in audit and 'HTTP_CF_CONNECTING_IP' not in audit, 'Audit does not trust proxy forwarding headers implicitly')
check("in_array($event, auth_audit_log_allowed_events(), true)" in audit, 'Audit events are allowlisted')
check("in_array($result, ['success', 'failure'], true)" in audit, 'Audit results are allowlisted')
check("if (defined('APP_DEBUG') && APP_DEBUG)" in audit, 'Audit DB failures do not spam production logs or break authentication')

# Secret material must not be accepted as columns or event payload fields.
for forbidden in ['auth_audit_log_password','auth_audit_log_totp_code','auth_audit_log_recovery_code','auth_audit_log_totp_secret','auth_audit_log_session_id','auth_audit_log_user_agent','auth_audit_log_ip_address']:
    check(forbidden not in migration.lower(), f'Migration excludes secret/sensitive column {forbidden}')
check('$_SESSION' not in audit, 'Audit module does not directly read raw PHP Session contents')
check('auth_audit_log_ip_hash' not in view and 'auth_audit_log_identity_hash' not in view, 'Security Activity UI does not render keyed identity/IP digests')
check('Password、認証コード、Recovery Code、Secret、Session ID、IP Address全文' in view, 'UI states the bounded Security Activity privacy policy')

# Required H event hooks.
check("auth_audit_log_record('login', 'success'" in index and "auth_audit_log_record(\n                'login',\n                'failure'" in index, 'Password login success/failure hooks are present')
check("auth_audit_log_record('two_factor', 'success'" in index and "'two_factor',\n                'failure'" in index, 'Second-factor success/failure hooks are present')
check("auth_audit_log_record('recovery_code', 'success'" in index, 'Recovery Code login use is audited')
check("auth_audit_log_record('login', 'success', $userId, null, 'remember')" in persistent, 'Remember restoration login is audited')
check("auth_audit_log_record('logout', 'success'" in logout, 'Explicit Logout is audited before session destruction')
check("auth_audit_log_record('totp_enable', 'success'" in totp_api and "auth_audit_log_record('totp_enable', 'failure'" in totp_api, '2FA enable success/failure is audited')
check("auth_audit_log_record('recovery_codes_generate', 'success'" in totp_api, 'Recovery Code generation/regeneration is audited')
check("auth_audit_log_record('step_up', 'success'" in security_api and "auth_audit_log_record('step_up', 'failure'" in security_api, 'Step-up success/failure is audited')
check("auth_audit_log_record('totp_disable', 'success'" in security_api, '2FA disable is audited')
check("auth_audit_log_record('session_revoke', 'success'" in session_api and "auth_audit_log_record('session_revoke_others', 'success'" in session_api, 'Session revocation actions are audited')
check("auth_audit_log_record('password_change', 'success'" in account and "auth_audit_log_record('email_change', 'success'" in account, 'Password and Email changes are audited')
check('auth_audit_log_list($userId)' in view and 'data-account-security-activity' in view, 'Account Security UI renders recent owner-scoped Security Activity')
check('Migration 024' in view, 'Missing audit migration degrades to an actionable UI warning instead of a 500')

if not all(ok for ok,_ in checks):
    sys.exit(1)
