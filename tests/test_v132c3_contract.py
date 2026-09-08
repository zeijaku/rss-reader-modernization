from pathlib import Path
import sys

root = Path(__file__).resolve().parents[1]
files = {
    'settings': (root / 'public/settings.php').read_text(encoding='utf-8'),
    'index': (root / 'public/index.php').read_text(encoding='utf-8'),
    'modals': (root / 'app/view/dashboard_modals.php').read_text(encoding='utf-8'),
    'security_view': (root / 'app/view/account_security.php').read_text(encoding='utf-8'),
    'account_js': (root / 'public/js/account-2fa.js').read_text(encoding='utf-8'),
    'qr_js': (root / 'public/js/totp-qr.js').read_text(encoding='utf-8'),
    'api': (root / 'app/api/account_totp.php').read_text(encoding='utf-8'),
    'totp': (root / 'app/auth_totp.php').read_text(encoding='utf-8'),
}
failures = 0

def check(ok: bool, message: str) -> None:
    global failures
    print(('PASS' if ok else 'FAIL') + ': ' + message)
    if not ok:
        failures += 1

for page in ('settings', 'index'):
    check("app_asset_url('js/totp-qr.js')" in files[page], f'{page} loads local TOTP QR renderer')
    check(files[page].index("app_asset_url('js/totp-qr.js')") < files[page].index("app_asset_url('js/account-2fa.js')"), f'{page} loads QR renderer before account 2FA controller')

check("account_security_render($accountSecurityState" in files['settings'], 'settings renders the shared Account Security view')
check("account_security_render($dashboardAccountSecurityState" in files['modals'], 'dashboard renders the shared Account Security view')
check('data-account-totp-provisioning-show' in files['security_view'], 'shared security view offers QR display for pending enrollment')
check('data-account-totp-qr' in files['security_view'] and 'data-account-totp-secret' in files['security_view'], 'shared security view contains empty local provisioning targets')
check('外部のQR生成サービスへSecretや設定URIを送信しません' in files['security_view'], 'shared security view explains local-only QR rendering')

check("'account.totp.provisioning' => api_account_totp_provisioning($userId)" in files['api'], 'API dispatcher exposes authenticated provisioning action')
check('auth_totp_pending_provisioning' in files['totp'], 'TOTP foundation can reopen encrypted pending provisioning material')
check("action: 'account.totp.provisioning'" in files['account_js'], 'browser requests provisioning only through authenticated API v1')
check('window.iGuguruTotpQr.render' in files['account_js'], 'browser renders provisioning URI with bundled local QR encoder')
check(".on('hidden.bs.modal.iguguruAccountTotp'" in files['account_js'], 'closing Account Settings clears QR and plaintext setup key from DOM')

combined = '\n'.join(files.values()).lower()
for host in ('chart.googleapis.com', 'api.qrserver.com', 'quickchart.io', 'googleapis.com/chart'):
    check(host not in combined, f'no external QR service reference: {host}')
check('fetch(' not in files['qr_js'].lower() and 'xmlhttprequest' not in files['qr_js'].lower(), 'QR renderer performs no network requests')
check('<img src="http' not in files['security_view'].lower(), 'provisioning UI does not embed a remote QR image')

sys.exit(0 if failures == 0 else 1)
