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
putenv('SESSION_COOKIE_NAME=iguguru_v132c_session_test');
putenv('AUTH_2FA_PENDING_TIMEOUT=300');

require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/session.php';

$results = [];
$check = static function (bool $condition, string $message) use (&$results): void {
    $results[] = [$condition, $message];
};

app_session_start();
$anonymousId = session_id();
$anonymousCsrf = app_csrf_token();
app_session_begin_pending_auth(77, 'password', true);
$pendingId = session_id();
$pendingCsrf = app_csrf_token();

$check($anonymousId !== '' && $pendingId !== '' && !hash_equals($anonymousId, $pendingId), 'entering 2FA pending rotates the session identifier');
$check(!hash_equals($anonymousCsrf, $pendingCsrf), 'entering 2FA pending rotates the CSRF token');
$check(app_session_user_id() === null && !app_session_is_authenticated(), '2FA pending never grants authenticated user_id');
$check(app_session_has_pending_auth() && app_session_pending_user_id() === 77, 'pending state records only the expected positive user id');
$check(app_session_pending_source() === 'password' && app_session_pending_remember_requested(), 'password pending keeps the deferred Remember Me request');
$check(!app_session_pending_is_expired(), 'fresh pending state is not expired');

$completed = app_session_complete_pending_auth();
$authenticatedId = session_id();
$check($completed === 77 && app_session_user_id() === 77 && app_session_is_authenticated(), 'pending state completes into the expected authenticated user');
$check(!hash_equals($pendingId, $authenticatedId), 'completing 2FA rotates the session identifier again');
$check(!app_session_has_pending_auth(), 'completed authentication removes all pending markers');

app_session_clear_authentication();
app_session_begin_pending_auth(88, 'remember', true);
$check(app_session_pending_source() === 'remember', 'Remember restoration can enter pending without authenticating');
$check(!app_session_pending_remember_requested(), 'Remember-origin pending never requests a second persistent-token issue');
$cancelBefore = session_id();
$cancelCsrfBefore = app_csrf_token();
app_session_cancel_pending_auth();
$check(!app_session_has_pending_auth() && app_session_user_id() === null, 'cancel clears pending without authenticating');
$check(session_id() !== '' && !hash_equals($cancelBefore, session_id()), 'cancel rotates the anonymous session identifier');
$check(!hash_equals($cancelCsrfBefore, app_csrf_token()), 'cancel invalidates the pending CSRF token');

app_session_begin_pending_auth(99, 'password', false);
$_SESSION['auth_pending_started_at'] = time() - AUTH_2FA_PENDING_TIMEOUT - 1;
$expiredSessionId = session_id();
$expiredCsrf = app_csrf_token();
session_write_close();
app_session_start();
$check(!app_session_has_pending_auth() && app_session_user_id() === null, 'expired pending state is removed on the next request');
$check(session_id() !== '' && !hash_equals($expiredSessionId, session_id()), 'expired pending state rotates its session identifier');
$check(!hash_equals($expiredCsrf, app_csrf_token()), 'expired pending state invalidates the stale challenge CSRF token');
$notice = app_flash_take('auth_notice');
$check(is_array($notice) && str_contains((string) ($notice['message'] ?? ''), '2段階認証'), 'expired pending state returns a dedicated re-login notice');

app_session_logout();
$output = ob_get_clean();
if (is_string($output) && $output !== '') {
    fwrite(STDERR, $output);
}

$passed = 0;
foreach ($results as [$ok, $message]) {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if ($ok) {
        $passed++;
    }
}
$failed = count($results) - $passed;
echo "RESULT: PASS {$passed} / FAIL {$failed} / SKIP 0" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
