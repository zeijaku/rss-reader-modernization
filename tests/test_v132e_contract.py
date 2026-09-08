from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
failed = 0

def check(condition: bool, message: str) -> None:
    global failed
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failed += 1

settings = (ROOT / 'public/settings.php').read_text(encoding='utf-8')
dashboard = (ROOT / 'app/view/dashboard_modals.php').read_text(encoding='utf-8')
security_view = (ROOT / 'app/view/account_security.php').read_text(encoding='utf-8')
totp = (ROOT / 'app/auth_totp.php').read_text(encoding='utf-8')
js = (ROOT / 'public/js/account-2fa.js').read_text(encoding='utf-8')

check("require_once dirname(__DIR__) . '/app/view/account_security.php';" in settings, 'Settings loads the shared Account Security view helper')
check("require_once __DIR__ . '/account_security.php';" in dashboard, 'Dashboard loads the same shared Account Security view helper')
check("account_security_view_state($currentUserId, 'Account Settings')" in settings, 'Settings computes Security state through the shared helper')
check("account_security_view_state((int) $currentUserId, 'Dashboard Account Settings')" in dashboard, 'Dashboard computes Security state through the shared helper')
check("account_security_render($accountSecurityState" in settings, 'Settings renders the shared Security panel')
check("account_security_render($dashboardAccountSecurityState" in dashboard, 'Dashboard renders the shared Security panel')
check('data-account-security-panel' in security_view, 'shared panel exposes one bounded Security UI root')
check('data-account-totp-badge' in security_view and 'data-account-recovery-count' in security_view, '2FA and Recovery Code status have dedicated badge targets')
check('残り0 / ' in security_view and '残りが少なくなっています' in security_view, 'Recovery Code UI distinguishes exhausted and low-count states')
check('data-account-recovery-manage' in security_view and '<details' in security_view, 'Recovery Code generation is grouped under a collapsible management control')
check('追加の本人確認を伴うSecurity操作として後続工程で追加します' in security_view, 'dangerous 2FA disable/re-registration is not exposed before Step-up')
check("$account = 'Account';" in totp and "'user-' . $userId" not in totp, 'new TOTP provisioning labels no longer expose the internal numeric user ID')
check("$container.find('[data-account-totp-badge]').first()" in js, 'browser updates only the dedicated 2FA badge after enrollment transitions')
check("data-account-recovery-guidance" in js and "remaining <= 3" in js, 'browser keeps Recovery Code warning text synchronized after generation')
check('localStorage' not in js and 'sessionStorage' not in js and 'console.log' not in js, 'Account Security UI still avoids browser persistence/logging of sensitive material')
check(settings.count('data-account-totp-status') == 0 and dashboard.count('data-account-totp-status') == 0, 'duplicated 2FA markup is removed from both page templates')

raise SystemExit(1 if failed else 0)
