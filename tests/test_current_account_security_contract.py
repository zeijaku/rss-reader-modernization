from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
APP = ROOT / 'app'
PUBLIC = ROOT / 'public'

failures = []
passed = 0

def check(cond, msg):
    global passed
    if cond:
        print('PASS: ' + msg)
        passed += 1
    else:
        print('FAIL: ' + msg)
        failures.append(msg)

index = (PUBLIC / 'index.php').read_text(encoding='utf-8')
stock = (PUBLIC / 'stock.php').read_text(encoding='utf-8')
session = (APP / 'session.php').read_text(encoding='utf-8')
persistent = (APP / 'persistent_login.php').read_text(encoding='utf-8')
api = (PUBLIC / 'api_v1.php').read_text(encoding='utf-8')
totp = (APP / 'auth_totp.php').read_text(encoding='utf-8')
totp_api = (APP / 'api' / 'account_totp.php').read_text(encoding='utf-8')
throttle = (APP / 'login_throttle.php').read_text(encoding='utf-8')
js = (PUBLIC / 'js' / 'account-2fa.js').read_text(encoding='utf-8')
qr = (PUBLIC / 'js' / 'totp-qr.js').read_text(encoding='utf-8')
gitignore = (ROOT / '.gitignore').read_text(encoding='utf-8')
migration = ROOT / 'database' / 'migrations' / '022_v1_32_auth_2fa.sql'

public_php = []
for path in PUBLIC.rglob('*.php'):
    public_php.append((path, path.read_text(encoding='utf-8', errors='replace')))

# Canonical credential entry point.
auth_calls = [(p.relative_to(ROOT).as_posix(), t.count('auth_authenticate(')) for p, t in public_php if 'auth_authenticate(' in t]
register_calls = [(p.relative_to(ROOT).as_posix(), t.count('auth_register(')) for p, t in public_php if 'auth_register(' in t]
login_views = [(p.relative_to(ROOT).as_posix(), t.count('view_login(')) for p, t in public_php if 'view_login(' in t]
check(auth_calls == [('public/index.php', 1)], 'password authentication has exactly one public entry point: public/index.php')
check(register_calls == [('public/index.php', 1)], 'registration has exactly one public entry point: public/index.php')
check(login_views == [('public/index.php', 1)], 'Login form rendering has exactly one public entry point')
check('auth_authenticate(' not in stock and 'app_session_login(' not in stock and 'view_login(' not in stock, 'Stock contains no alternate authentication path')
check("header('Location: ./', true, 302);" in stock, 'Stock rejects incomplete authentication by redirecting to the root flow')

# Pending is not authenticated and all raw pending metadata stays in session.php.
begin = session[session.index('function app_session_begin_pending_auth'):session.index('function app_session_complete_pending_auth')]
check("'auth_pending_user_id' => $userId" in begin and "'user_id' =>" not in begin, 'entering 2FA pending never writes authenticated user_id')
check('session_regenerate_id(true)' in begin, 'entering 2FA pending rotates the session id')
complete = session[session.index('function app_session_complete_pending_auth'):session.index('function app_session_cancel_pending_auth')]
check('app_session_login($userId)' in complete, 'pending completion reaches full authentication only through app_session_login')
check('session_regenerate_id(true)' in session[session.index('function app_session_cancel_pending_auth'):session.index('function app_session_login')], 'pending cancellation rotates the session id')
raw_pending_refs = []
for base in (APP, PUBLIC):
    for p in base.rglob('*.php'):
        txt = p.read_text(encoding='utf-8', errors='replace')
        if 'auth_pending_user_id' in txt:
            raw_pending_refs.append(p.relative_to(ROOT).as_posix())
check(set(raw_pending_refs) <= {'app/session.php'}, 'raw pending user id remains confined to app/session.php')

# Password and Remember paths require TOTP when enabled.
check('auth_totp_status($authenticatedUserId)' in index and 'app_session_begin_pending_auth($authenticatedUserId' in index, 'password login branches 2FA-enabled users into pending state')
check(index.index('app_session_begin_pending_auth($authenticatedUserId') < index.index("header('Location: ./?auth=2fa'"), 'password 2FA challenge is established before redirect')
check("if ($twoFactorEnabled)" in persistent and "app_session_begin_pending_auth($userId, 'remember'" in persistent, 'Remember restoration cannot bypass enabled 2FA')
check("app_session_login($userId)" in persistent, 'Remember restoration retains direct login only for accounts without 2FA')

# TOTP login completion and replay resistance.
check('auth_totp_verify_enabled_code($pendingUserId, $code)' in index, 'pending login verifies TOTP before completion')
check('app_session_complete_pending_auth()' in index, 'successful TOTP completes the pending state through the session helper')
check('auth_totp_last_used_step < :compare_step' in totp, 'enabled TOTP verification rejects reuse of the same/older time step atomically')
check('auth_totp_last_used_step = :last_used_step' in totp, 'successful TOTP verification records the consumed time step')

# TOTP crypto and provisioning boundaries.
check('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' in totp and 'sodium_crypto_aead_xchacha20poly1305_ietf_decrypt' in totp, 'TOTP Secret uses XChaCha20-Poly1305 authenticated encryption')
check("return 'rss-reader:auth-totp:' . $userId . ':v1';" in totp, 'TOTP encryption binds ciphertext to user id with AAD')
check("'secret' => $secret" in totp_api and "'otpauth_uri' => $otpauthUri" in totp_api, 'provisioning material is returned only by the dedicated authenticated account action')
check('app_send_no_store_headers();' in api or 'app_send_private_no_store_headers();' in api, 'API v1 provisioning responses inherit no-store headers')
check("if ($userId === null)" in api and "api_error('unauthenticated'" in api, 'API v1 rejects pending/anonymous sessions before account TOTP dispatch')
check("app_csrf_is_valid($csrfToken)" in api, 'API v1 validates CSRF before TOTP account dispatch')
check('console.log' not in js and 'console.error' not in js and 'console.debug' not in js, '2FA browser code does not log Secret or TOTP values to console')
qr_without_svg_ns = qr.replace("http://www.w3.org/2000/svg", '')
check('http://' not in qr_without_svg_ns and 'https://' not in qr_without_svg_ns and 'fetch(' not in qr and '$.ajax' not in qr and 'XMLHttpRequest' not in qr, 'QR generator has no external-network dependency')

# 2FA throttling is isolated from password buckets.
check("'2fa-pair'" in throttle and "'2fa-ip'" in throttle, '2FA uses dedicated pair/IP throttle namespaces')
check('AUTH_2FA_RATE_MAX_PAIR' in throttle and 'AUTH_2FA_RATE_MAX_IP' in throttle, '2FA throttle applies dedicated limits')

# No direct authenticated session writes elsewhere.
raw_session_refs = []
for base in (APP, PUBLIC):
    for p in base.rglob('*.php'):
        txt = p.read_text(encoding='utf-8', errors='replace')
        if '$_SESSION' in txt:
            raw_session_refs.append(p.relative_to(ROOT).as_posix())
check(set(raw_session_refs) <= {'app/session.php'}, 'raw PHP session access is confined to app/session.php')

# Auth-related logs must not interpolate plaintext factors/provisioning material.
for rel in ['public/index.php', 'app/api/account_totp.php', 'app/persistent_login.php', 'app/view/dashboard_modals.php', 'public/settings.php']:
    txt = (ROOT / rel).read_text(encoding='utf-8', errors='replace')
    for line in txt.splitlines():
        if 'error_log(' in line:
            low = line.lower()
            check('$code' not in low and '$secret' not in low and '$otpauth' not in low and '$token' not in low, f'{rel} auth error logs do not include plaintext code/Secret/token')

# Migration/source tracking completeness.
check(migration.is_file(), 'Migration 022 is present in the final C source tree')
check('!/database/migrations/022_v1_32_auth_2fa.sql' in gitignore, 'Migration 022 is explicitly unignored for Git source tracking')
ms = migration.read_text(encoding='utf-8')
check('auth_totp' in ms and 'auth_recovery_code' in ms, 'Migration 022 defines TOTP and future Recovery Code tables')

print(f'RESULT: PASS {passed} / FAIL {len(failures)} / SKIP 0')
raise SystemExit(1 if failures else 0)
