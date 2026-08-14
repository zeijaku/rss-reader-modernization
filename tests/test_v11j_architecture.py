from pathlib import Path
import hashlib
import re
import sys

from dashboard_source_utils import dashboard_source
ROOT = Path(__file__).resolve().parents[1]
account = (ROOT / 'app/account_settings.php').read_text(encoding='utf-8')
api = (ROOT / 'app/api.php').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app/bootstrap.php').read_text(encoding='utf-8')
auth = (ROOT / 'app/auth.php').read_text(encoding='utf-8')
session = (ROOT / 'app/session.php').read_text(encoding='utf-8')
index = dashboard_source(ROOT)
js = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
schema_path = ROOT / 'database/schema.sql'

checks = []
def check(cond, msg):
    checks.append(bool(cond))
    print(('PASS' if cond else 'FAIL') + ': ' + msg)

check((ROOT / 'app/account_settings.php').is_file(), 'Account Settings domain module exists')
check("require_once __DIR__ . '/account_settings.php';" in bootstrap, 'Account Settings module loads through bootstrap')
check(bootstrap.index("/auth.php") < bootstrap.index("/account_settings.php"), 'authentication helpers load before Account Settings')
check("'account.email.update' => api_account_email_update" in api, 'email update action is dispatched')
check("'account.password.update' => api_account_password_update" in api, 'password update action is dispatched')
check("api_string($input, 'new_email')" in api, 'email API reads new_email')
check(api.count("api_string($input, 'current_password')") >= 2, 'both Account Settings actions read current_password')
check("api_string($input, 'new_password')" in api, 'password API reads new_password')
check("api_string($input, 'new_password_confirmation')" in api, 'password API reads confirmation')
account_dispatch = api[api.find("'account.email.update'"):api.find("'tabs.update'")]
check('user_id' not in account_dispatch, 'Account Settings dispatch does not accept a client owner id')

check('auth_email_is_valid($newEmail)' in account, 'domain validates the new email')
check('auth_identity_key($newEmail)' in account, 'email update stores the existing keyed identity format')
check('password_verify($currentPassword, $storedPassword)' in account, 'current password is verified before changes')
check('auth_password_hash($newPassword)' in account, 'new password uses the existing password hash helper')
check('password_verify($newPassword, $storedPassword)' in account, 'new password cannot equal the current password')
check('hash_equals($newPassword, $newPasswordConfirmation)' in account, 'password confirmation uses constant-time comparison')
check('AUTH_PASSWORD_MAX_LENGTH' in account, 'current password has an explicit upper bound')
check('strpos($password, "\\0") === false' in account, 'current password rejects NUL bytes')
check("WHERE user_id = :user_id AND user_flag = 0" in account, 'account lookup is restricted to the active authenticated user')
check("WHERE user_email = :email AND user_id <> :user_id" in account, 'duplicate email check excludes only the current user')
check(account.count('FOR UPDATE') >= 2, 'MySQL row locking is used for account and duplicate checks')
check("PDO::ATTR_DRIVER_NAME" in account and "=== 'mysql'" in account, 'FOR UPDATE is restricted to MySQL')
check(account.count('beginTransaction()') == 2, 'both account changes start a transaction')
check(account.count('commit()') == 2, 'both account changes commit explicitly')
check(account.count('rollBack()') >= 8, 'validation failures and exceptions roll back active transactions')
check(account.count('rowCount() !== 1') == 2, 'both updates require exactly one active row')
check("SET user_email = :email" in account, 'email update changes only the email identity field')
check("SET user_password = :password" in account, 'password update changes only the password field')
check("hash_hmac('sha256', 'account-settings:' . $userId" in account, 'rate-limit identity is keyed and user-scoped')

check('api_account_settings_rate_status($userId)' in api, 'Account Settings uses the existing rate limiter')
check(api.count('api_account_settings_record_failure($userId)') == 2, 'wrong current password is recorded for both actions')
check(api.count('api_account_settings_record_success($userId)') == 2, 'successful changes clear the pair throttle')
check("account_settings_throttled" in api and ', 429)' in api, 'blocked Account Settings request returns HTTP 429')
check("substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 128)" in api, 'remote IP is bounded before throttle use')
check("error_log('Account email update failed.');" in api, 'email failure log is generic')
check("error_log('Account password update failed.');" in api, 'password failure log is generic')
account_api = api[api.find('function api_account_email_update'):api.find('function api_settings_update')]
check('$newEmail' not in ''.join(re.findall(r'error_log\((.*?)\);', account_api, re.S)), 'email value is not written to Account Settings logs')
check('$currentPassword' not in ''.join(re.findall(r'error_log\((.*?)\);', account_api, re.S)), 'current password is not written to Account Settings logs')
check('$newPassword' not in ''.join(re.findall(r'error_log\((.*?)\);', account_api, re.S)), 'new password is not written to Account Settings logs')
check(api.count('api_account_settings_rotate_session($userId)') == 2, 'both successful changes rotate the authenticated session')
check('app_session_login($userId)' in api, 'session rotation reuses the secure login session helper')
check("return app_csrf_token();" in api, 'rotated CSRF token is returned')
check("api_success(['csrf_token' => $csrfToken])" in api, 'success response includes the new CSRF token')
check('session_regenerate_id(true)' in session, 'session helper rotates the session identifier')

modal_start = index.find('id="accountSettings"')
modal_end = index.find('<!-- 記録用スモールモーダル[Save] -->', modal_start)
modal = index[modal_start:modal_end]
check(modal_start >= 0 and modal_end > modal_start, 'Account Settings modal is present')
check('aria-labelledby="accountSettingsTitle"' in modal, 'Account Settings modal has an accessible label')
check('id="accountEmailForm"' in modal, 'email change form is present')
check('id="accountPasswordForm"' in modal, 'password change form is present')
check('現在のメールアドレスは画面には表示していません' in modal, 'UI explains why current email is not displayed')
check('type="email"' in modal and 'name="new_email"' in modal, 'email form uses a native email input')
check('maxlength="254"' in modal, 'email input has the same 254 byte bound')
check('autocomplete="email"' in modal, 'new email input has an autocomplete hint')
check(modal.count('autocomplete="current-password"') == 2, 'both forms identify the current password field')
check(modal.count('autocomplete="new-password"') == 2, 'new password and confirmation have correct autocomplete hints')
check(modal.count('type="password"') == 4, 'all four credential inputs are password fields')
check('value="' not in '\n'.join(line for line in modal.splitlines() if 'type="password"' in line), 'password fields are never prefilled in HTML')
check('user_email' not in modal and 'user_password' not in modal, 'stored account identity and hash are not rendered in the modal')
check('data-drawer-modal-target="#accountSettings"' in index and 'アカウント設定' in index, 'Drawer exposes Account Settings')
check(index.index('href="./settings#display"') < index.index('data-drawer-modal-target="#accountSettings"') < index.index('drawer-logout-button'), 'Drawer keeps dedicated Display Settings before Account Settings and logout')

check("function accountRefreshCsrfToken" in js, 'frontend has a bounded CSRF refresh helper')
check("/^[a-f0-9]{64}$/.test(token)" in js, 'frontend validates the rotated CSRF token shape')
check("$('meta[name=\"csrf-token\"]').attr('content', token)" in js, 'frontend replaces the CSRF meta token')
check("apiRequest('account.email.update'" in js, 'frontend sends email update action')
check("apiRequest('account.password.update'" in js, 'frontend sends password update action')
email_js = js[js.find('function changeAccountEmail'):js.find('function changeAccountPassword')]
password_js = js[js.find('function changeAccountPassword'):js.find('/* タブ名変更')]
check("'new_email'" in email_js and "'current_password'" in email_js, 'email payload contains only required credential fields')
check('user_id' not in email_js and 'user_id' not in password_js, 'frontend never sends a user id for account changes')
check("newPassword !== confirmation" in password_js, 'frontend rejects mismatched password confirmation')
check("$form.find('.accountCurrentPasswordEmail').val('')" in email_js, 'email current password is cleared after every request')
check("$form.find('input[type=\"password\"]').val('')" in password_js, 'password form credentials are cleared after every request')
check('accountResetForms();' in email_js and 'accountResetForms();' in password_js, 'successful changes reset both forms')
check("$('#accountSettings').modal('hide')" in email_js and "$('#accountSettings').modal('hide')" in password_js, 'successful changes close the modal')
check("showNotice('メールアドレスを変更しました'" in email_js, 'email success notice is present')
check("showNotice('パスワードを変更しました'" in password_js, 'password success notice is present')
check(".off('submit' + eventNamespace, '#accountEmailForm')" in js, 'email form handler replaces older namespaced handlers')
check(".off('submit' + eventNamespace, '#accountPasswordForm')" in js, 'password form handler replaces older namespaced handlers')
check(".off('hidden.bs.modal' + eventNamespace, '#accountSettings')" in js, 'modal cleanup handler is namespaced')
for unsafe in ['.html(', 'innerHTML', 'insertAdjacentHTML', 'document.write(', 'eval(', 'new Function']:
    check(unsafe not in email_js + password_js, f'Account Settings frontend avoids unsafe operation: {unsafe}')

schema_text = schema_path.read_text(encoding='utf-8')
check('account_settings' not in schema_text.lower(), 'Account Settings adds no table or column to the Version 1.1 schema')
account_migrations = [path for path in (ROOT / 'database/migrations').glob('*.sql') if 'account_settings' in path.name.lower()] if (ROOT / 'database/migrations').exists() else []
check(account_migrations == [], 'Account Settings adds no database migration')
check("const APP_VERSION = '1.1.0-dev.9';" in version or "const APP_VERSION = '1.1.0';" in version or "const APP_VERSION = '1.2.0-dev.3';" or "const APP_VERSION = '1.2.0-dev.4';" in version, 'application version is V1.1-J dev.9 or final 1.1.0')
check("const APP_VERSION_LABEL = 'RSS Reader Modernization V1.1-J / R1';" in version or "const APP_VERSION_LABEL = 'RSS Reader Modernization 1.1.0';" in version or "const APP_VERSION = '1.2.0-dev.3';" or "const APP_VERSION = '1.2.0-dev.4';" in version or "const APP_VERSION_LABEL = 'RSS Reader Modernization 1.2.0-dev.3';" in version, 'visible version label identifies V1.1-J or final 1.1.0')

if not all(checks):
    print(f'{checks.count(False)}/{len(checks)} V1.1-J architecture checks failed.')
    sys.exit(1)
print(f'All {len(checks)} V1.1-J architecture checks passed.')