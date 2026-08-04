from __future__ import annotations
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
AUTH_JS = (ROOT / 'public/js/auth.js').read_text(encoding='utf-8')

checks = []
def check(condition, message):
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

try:
    from playwright.sync_api import sync_playwright
except Exception as exc:
    print('SKIP: Playwright is unavailable: ' + str(exc))
    sys.exit(0)

html = '''<!doctype html>
<html lang="ja"><head><meta charset="utf-8"></head><body>
<section data-auth-panel="login">
<form data-auth-form>
<input id="loginEmail" type="email">
<input id="loginPassword" type="password">
<button id="loginToggle" type="button" data-password-toggle aria-controls="loginPassword" aria-pressed="false" aria-label="パスワードを表示"><i data-password-icon></i></button>
<button id="loginSubmit" type="submit"><span data-submit-label>ログイン</span></button>
</form>
<button id="toRegister" type="button" data-auth-switch="register">新規登録</button>
</section>
<section data-auth-panel="register" hidden>
<form data-auth-form>
<input id="registerEmail" type="email">
<input id="registerPassword" type="password">
<button id="registerToggle" type="button" data-password-toggle aria-controls="registerPassword" aria-pressed="false" aria-label="パスワードを表示"><i data-password-icon></i></button>
<button type="submit"><span data-submit-label>登録する</span></button>
</form>
<button id="toLogin" type="button" data-auth-switch="login">ログインへ戻る</button>
</section>
</body></html>'''

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    page = browser.new_page()
    page.set_content(html, wait_until='load')
    page.add_script_tag(content=AUTH_JS)

    page.click('#loginToggle')
    check(page.locator('#loginPassword').get_attribute('type') == 'text', 'password toggle reveals the login password')
    check(page.locator('#loginToggle').get_attribute('aria-pressed') == 'true' and page.locator('#loginToggle').get_attribute('aria-label') == 'パスワードを隠す', 'revealed password toggle updates ARIA state')
    page.click('#loginToggle')
    check(page.locator('#loginPassword').get_attribute('type') == 'password', 'password toggle hides the login password again')

    page.click('#toRegister')
    check(page.locator('[data-auth-panel="login"]').is_hidden() and page.locator('[data-auth-panel="register"]').is_visible(), 'registration switch shows only the requested panel')
    check(page.evaluate('document.activeElement && document.activeElement.id') == 'registerEmail', 'registration panel sends focus to its first real input')
    page.click('#toLogin')
    check(page.evaluate('document.activeElement && document.activeElement.id') == 'loginEmail', 'login panel sends focus back to its first real input')

    submit_state = page.evaluate('''() => {
        const form = document.querySelector('[data-auth-panel="login"] form');
        const first = new Event('submit', {bubbles: true, cancelable: true});
        form.dispatchEvent(first);
        const second = new Event('submit', {bubbles: true, cancelable: true});
        form.dispatchEvent(second);
        return {
            firstPrevented: first.defaultPrevented,
            secondPrevented: second.defaultPrevented,
            submitting: form.dataset.submitting,
            busy: form.getAttribute('aria-busy'),
            disabled: document.getElementById('loginSubmit').disabled,
            label: document.querySelector('#loginSubmit [data-submit-label]').textContent
        };
    }''')
    check(submit_state['firstPrevented'] is False and submit_state['secondPrevented'] is True, 'first submit is allowed and duplicate submit is prevented')
    check(submit_state['submitting'] == 'true' and submit_state['busy'] == 'true', 'submitting form exposes locked and busy state')
    check(submit_state['disabled'] is True and submit_state['label'] == '送信中…', 'submit button is disabled with progress wording')

    browser.close()

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} V1.2-A browser checks passed.')
