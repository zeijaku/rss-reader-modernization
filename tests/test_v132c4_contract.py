from pathlib import Path

root = Path(__file__).resolve().parents[1]
api = (root / 'app/api/account_totp.php').read_text(encoding='utf-8')
js = (root / 'public/js/account-2fa.js').read_text(encoding='utf-8')
settings = (root / 'public/settings.php').read_text(encoding='utf-8')
dashboard = (root / 'app/view/dashboard_modals.php').read_text(encoding='utf-8')
security_view = (root / 'app/view/account_security.php').read_text(encoding='utf-8')
api_v1 = (root / 'public/api_v1.php').read_text(encoding='utf-8')

checks = []
def check(condition, message):
    checks.append((bool(condition), message))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

check("'account.totp.confirm' => api_account_totp_confirm($userId, $input)" in api, 'dispatcher exposes the C4 confirmation action')
check("preg_match('/\\A[0-9]{6}\\z/D', $code)" in api, 'server validates exactly six digits')
check('auth_2fa_throttle_status($userId, $ipAddress)' in api and 'auth_2fa_throttle_record_failure($userId, $ipAddress)' in api, 'confirmation uses dedicated second-factor rate limiting')
check('remember_token_revoke_user($userId)' in api and 'persistent_login_clear_cookie()' in api, '2FA enablement invalidates pre-existing Remember Login trust')
check("api_success(['state' => 'enabled'])" in api, 'confirmation success returns only enabled state')
check("error_log('Account TOTP confirmation" in api and 'error_log($code' not in api, 'server logs do not include the submitted TOTP code')
check("action: 'account.totp.confirm'" in js and 'code: code' in js, 'browser sends the confirmation code only to the authenticated API')
check("$container.find('[data-account-totp-secret]').first().text('');" in js and "data-account-totp-status', 'enabled'" in js, 'browser clears provisioning secret when enablement succeeds')
check("replace(/[^0-9]/g, '').slice(0, 6)" in js, 'browser normalizes confirmation input to six digits')
check("account_security_render($accountSecurityState" in settings, 'Settings modal uses the shared C4 security control')
check("account_security_render($dashboardAccountSecurityState" in dashboard, 'Dashboard modal uses the shared C4 security control')
check('data-account-totp-confirm-button' in security_view and 'autocomplete="one-time-code"' in security_view, 'shared security view contains the C4 confirmation control')
check("str_starts_with($action, 'account.totp.')" in api_v1, 'existing API v1 authenticated/CSRF boundary still gates all TOTP account actions')
check('https://chart.googleapis.com' not in js.lower() and 'api.qrserver.com' not in js.lower(), 'C4 does not introduce an external QR service')

failed = sum(1 for ok, _ in checks if not ok)
print(f'RESULT: PASS {len(checks)-failed} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
