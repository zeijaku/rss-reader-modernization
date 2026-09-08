from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
view = (ROOT / 'app/view/account_security.php').read_text(encoding='utf-8')
js = (ROOT / 'public/js/account-2fa.js').read_text(encoding='utf-8')
failed = 0

def check(cond, msg):
    global failed
    print(('PASS' if cond else 'FAIL') + ': ' + msg)
    if not cond:
        failed += 1

check('data-account-security-panel data-account-totp-status="<?php echo app_html($totpState); ?>"' in view,
      'Security section itself is the TOTP state container')
check('<div class="border rounded p-3" data-account-totp-status=' not in view,
      'inner status card no longer creates a narrower JS scope')
check('data-account-sensitive-actions' in view and 'data-account-stepup-verify' in view and 'data-account-totp-disable' in view,
      'Step-up and disable controls remain inside the Security section')
check("verifyStepUp($(this).closest('[data-account-totp-status]'))" in js,
      'Step-up click resolves the shared Security state container')
check("disableTotp($(this).closest('[data-account-totp-status]'))" in js,
      'Disable click resolves the shared Security state container')
check("$container.find('[data-account-sensitive-actions]')" in js,
      'Step-up helpers can now find sensitive actions within the resolved container')
check("$container.attr('data-account-totp-status') !== 'enabled'" in js,
      'Step-up still fails closed unless the resolved state is enabled')

raise SystemExit(1 if failed else 0)
