from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
failed = 0

def check(condition: bool, message: str) -> None:
    global failed
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failed += 1

recovery = (ROOT / 'app/auth_recovery_code.php').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app/bootstrap.php').read_text(encoding='utf-8')
api = (ROOT / 'app/api/account_totp.php').read_text(encoding='utf-8')
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
login = (ROOT / 'app/common/common_login.php').read_text(encoding='utf-8')
js = (ROOT / 'public/js/account-2fa.js').read_text(encoding='utf-8')
settings = (ROOT / 'public/settings.php').read_text(encoding='utf-8')
dashboard = (ROOT / 'app/view/dashboard_modals.php').read_text(encoding='utf-8')
security_view = (ROOT / 'app/view/account_security.php').read_text(encoding='utf-8')
migration = (ROOT / 'database/migrations/022_v1_32_auth_2fa.sql').read_text(encoding='utf-8')

check("require_once __DIR__ . '/auth_recovery_code.php';" in bootstrap, 'bootstrap loads the Recovery Code module')
check("AUTH_RECOVERY_CODE_COUNT = 10" in recovery and "AUTH_RECOVERY_CODE_LENGTH = 16" in recovery, 'Recovery Code set is bounded to ten 16-character codes')
check("random_bytes(AUTH_RECOVERY_CODE_LENGTH)" in recovery and "ord($bytes[$i]) & 31" in recovery, 'Recovery Code generation uses unbiased cryptographic randomness over a 32-character alphabet')
check("hash('sha256', 'rss-reader:recovery-code:v1:' . $userId . ':' . $normalized)" in recovery, 'database hash is one-way and user-bound')
check("auth_recovery_code_used_at IS NULL" in recovery and "SET auth_recovery_code_used_at = :used_at" in recovery, 'Recovery Code consumption is an atomic unused-to-used update')
check("DELETE FROM ' . db_table_name('auth_recovery_code')" in recovery, 'regeneration invalidates the previous set before inserting the replacement')
check('auth_recovery_code_hash' in migration and 'auth_recovery_code_used_at' in migration, 'Migration 022 already contains the Recovery Code hash/used-at schema')

check("'account.totp.recovery.generate' => api_account_totp_recovery_generate" in api, 'authenticated Account API routes Recovery Code generation')
check('auth_totp_verify_enabled_code($userId, $code)' in api and 'auth_recovery_code_replace($userId)' in api, 'generation requires a current valid Authenticator code before replacing the set')
check("'recovery_codes' => $codes" in api and "'remaining' => count($codes)" in api, 'plaintext codes are returned only in the generation response for one-time display')
check('auth_2fa_throttle_status' in api and 'auth_2fa_throttle_record_failure' in api, 'Recovery Code generation uses the existing second-factor rate limiter')

check("['login', 'regist', '2fa', '2fa_recovery', '2fa_cancel']" in index, 'Recovery Code login POST is inside the CSRF-protected auth token set')
check("$token === '2fa_recovery'" in index and 'auth_recovery_code_consume($pendingUserId, $submittedRecoveryCode)' in index, 'pending login can verify by atomically consuming a Recovery Code')
check('app_session_complete_pending_auth()' in index and 'persistent_login_issue_for_user($completedUserId)' in index, 'Recovery Code success completes the same pending-session/Remember flow as TOTP')
check('name="token" value="2fa_recovery"' in login and 'name="recovery_code"' in login, '2FA challenge exposes an explicit Recovery Code fallback form')
check('一度使用すると再利用できません' in login, 'login UI states the one-time-use property')

check("account_security_render($accountSecurityState" in settings, 'Settings Account Settings uses the shared Security view')
check("account_security_render($dashboardAccountSecurityState" in dashboard, 'Dashboard Account Settings uses the shared Security view')
check('data-account-recovery-codes' in security_view, 'shared Account Security view exposes Recovery Code management')
check('data-account-recovery-generate' in security_view and 'data-account-recovery-totp' in security_view, 'shared Account Security view requires an Authenticator code to generate/re-generate codes')
check('この10個はこの画面に一度だけ表示します' in security_view, 'shared Account Security view warns that plaintext Recovery Codes are shown only once')

check("action: 'account.totp.recovery.generate'" in js, 'browser calls only the authenticated Recovery Code generation API')
check("codeElement.textContent = code" in js, 'Recovery Code plaintext is inserted with textContent rather than HTML')
check("clearRecoveryPlaintext($container)" in js and "hidden.bs.modal.iguguruAccountTotp" in js, 'closing Account Settings clears plaintext Recovery Codes from the DOM')
check('localStorage' not in js and 'sessionStorage' not in js and 'console.log' not in js, 'Recovery Code browser flow does not persist or log plaintext codes')

# No D migration should be needed because 022 reserved the table in B.
new_d_migrations = list((ROOT / 'database/migrations').glob('*v1_32*d*.sql'))
check(not new_d_migrations, 'V1.32-D adds no new SQL migration beyond existing Migration 022')

raise SystemExit(1 if failed else 0)
