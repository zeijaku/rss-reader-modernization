from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
PUBLIC = ROOT / 'public'
APP = ROOT / 'app'


def check(condition: bool, message: str) -> None:
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        raise AssertionError(message)


index = (PUBLIC / 'index.php').read_text(encoding='utf-8')
stock = (PUBLIC / 'stock.php').read_text(encoding='utf-8')
api = (PUBLIC / 'api_v1.php').read_text(encoding='utf-8')
session = (APP / 'session.php').read_text(encoding='utf-8')

public_php = '\n'.join(p.read_text(encoding='utf-8', errors='replace') for p in PUBLIC.glob('*.php'))

check(public_php.count('auth_authenticate(') == 1, 'public password authentication has one canonical entry point')
check('auth_authenticate(' in index, 'canonical password authentication entry point is public/index.php')
check('auth_authenticate(' not in stock, 'Stock no longer contains a password-login path that can bypass 2FA')
check('persistent_login_issue_for_user(' not in stock, 'Stock cannot issue Remember trust outside the canonical 2FA flow')
check('auth_register(' not in stock, 'Stock no longer duplicates account registration/authentication handling')
check("header('Location: ./', true, 302);" in stock, 'Stock redirects incomplete authentication to the canonical root flow')
check(stock.index('app_session_user_id()') < stock.index("$ui = user_ui_config($currentUserId)"), 'Stock checks full authentication before loading owner-scoped data')
check('app_session_pending_user_id()' not in stock, 'Stock never treats pending user id as authenticated owner scope')

pending_refs = []
for base in (APP, PUBLIC):
    for path in base.rglob('*.php'):
        text = path.read_text(encoding='utf-8', errors='replace')
        if 'auth_pending_user_id' in text:
            pending_refs.append(path.relative_to(ROOT).as_posix())
check(set(pending_refs) <= {'app/session.php'}, 'raw pending user id is confined to the session module')

check("$userId = app_session_user_id();" in api, 'API v1 derives its user only from the fully-authenticated session helper')
check("if ($userId === null)" in api and "unauthenticated" in api, 'API v1 rejects non-authenticated/pending sessions before dispatch')
check("str_starts_with($action, 'account.totp.')" in api, 'TOTP account actions remain behind the same authenticated API boundary')

check("return isset($_SESSION['user_id'])" in session, 'full authentication is still keyed only by session user_id')
check("'auth_pending_user_id' => $userId" in session and "'user_id'" not in session[session.index("function app_session_begin_pending_auth"):session.index("function app_session_complete_pending_auth")], 'entering pending state does not write authenticated user_id')
check("$startedAt > ($now + 60)" in session and "$source === null" in session, 'tampered future timestamp/source invalidates pending state')

check('$_POST[\'user_id\']' not in api and '$_REQUEST[\'user_id\']' not in api, 'API v1 has no caller-supplied user-id authentication override')
check('view_login(' not in stock, 'Stock no longer renders a second login form')

print('All V1.32-C6 bypass/static contract checks passed.')
