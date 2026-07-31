<?php

declare(strict_types=1);

ob_start();

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');
putenv('DB_HOST=test');
putenv('DB_NAME=test');
putenv('DB_USER=test');
putenv('DB_PASSWORD=test');
putenv('SESSION_IDLE_TIMEOUT=600');
putenv('SESSION_ABSOLUTE_TIMEOUT=3600');

$_SERVER['HTTPS'] = 'off';
$_SERVER['SERVER_PORT'] = '80';

require $root . '/app/common/common_conf.php';
require $root . '/app/session.php';

$failures = [];
function scheck(bool $condition, string $message): void
{
    global $failures;
    if ($condition) {
        echo "PASS: {$message}\n";
    } else {
        $failures[] = $message;
        echo "FAIL: {$message}\n";
    }
}

app_session_start();
$anonymousId = session_id();
$params = session_get_cookie_params();

scheck(ini_get('session.use_strict_mode') === '1', 'session.use_strict_mode is enabled');
scheck(ini_get('session.use_only_cookies') === '1', 'session.use_only_cookies is enabled');
scheck(ini_get('session.use_trans_sid') === '0', 'URL session identifiers are disabled');
scheck((int) ini_get('session.cookie_lifetime') === 0, 'session cookie has browser-session lifetime');
scheck(($params['httponly'] ?? false) === true, 'session cookie is HttpOnly');
scheck(($params['samesite'] ?? '') === 'Lax', 'session cookie SameSite is Lax');
scheck(($params['secure'] ?? true) === false, 'HTTP test cookie is not Secure');
scheck(strlen(app_csrf_token()) === 64, 'CSRF token is 256-bit hex');

app_session_login(42);
$authenticatedId = session_id();
scheck($authenticatedId !== '' && $authenticatedId !== $anonymousId, 'session id changes on login');
scheck(app_session_user_id() === 42, 'authenticated user id is stored');
$keys = array_keys($_SESSION);
sort($keys);
scheck($keys === ['authenticated_at', 'csrf_token', 'last_activity', 'user_id'], 'session stores only minimal auth metadata');

$oldCsrf = app_csrf_token();
scheck(app_csrf_is_valid($oldCsrf), 'valid CSRF token is accepted');
scheck(!app_csrf_is_valid(str_repeat('0', 64)), 'wrong CSRF token is rejected');
scheck(!app_csrf_is_valid(null), 'missing CSRF token is rejected');

// Simulate idle expiry in the same process by closing and reopening the same id.
$_SESSION['last_activity'] = time() - SESSION_IDLE_TIMEOUT - 10;
session_write_close();
session_id($authenticatedId);
app_session_start();
scheck(!app_session_is_authenticated(), 'idle timeout clears authenticated state');

app_session_login(43);
$absoluteId = session_id();
$_SESSION['authenticated_at'] = time() - SESSION_ABSOLUTE_TIMEOUT - 10;
$_SESSION['last_activity'] = time();
session_write_close();
session_id($absoluteId);
app_session_start();
scheck(!app_session_is_authenticated(), 'absolute timeout clears authenticated state');
scheck(session_id() !== $absoluteId, 'expired authenticated session id is rotated');

app_session_logout();
scheck(session_status() !== PHP_SESSION_ACTIVE, 'logout destroys the active session');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " SB-03 session checks failed.\n");
    exit(1);
}

echo "All SB-03 session checks passed.\n";
ob_end_flush();
