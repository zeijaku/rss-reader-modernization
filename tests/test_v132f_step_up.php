<?php

declare(strict_types=1);

const AUTH_STEP_UP_TIMEOUT = 300;

session_id('v132f-step-up-' . bin2hex(random_bytes(4)));
session_start();
require_once dirname(__DIR__) . '/app/session.php';

$failed = 0;
$check = static function (bool $condition, string $message) use (&$failed): void {
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) { $failed++; }
};

$_SESSION = [
    'user_id' => 7,
    'authenticated_at' => time(),
    'last_activity' => time(),
    'csrf_token' => str_repeat('a', 64),
];

$check(app_session_step_up_is_valid() === false, 'authenticated session starts without a Step-up grant');
app_session_step_up_grant(7, 'totp');
$check(app_session_step_up_is_valid() === true, 'TOTP verification grants recent Step-up state');
$expires = app_session_step_up_expires_at();
$check(is_int($expires) && $expires >= time() + 295 && $expires <= time() + 305, 'Step-up grant uses the configured short TTL');

$_SESSION['auth_step_up_verified_at'] = time() - 301;
$check(app_session_step_up_is_valid() === false, 'expired Step-up state is rejected and cleared');
$check(!isset($_SESSION['auth_step_up_user_id']), 'expired Step-up metadata is removed from the session');

app_session_step_up_grant(7, 'recovery');
$_SESSION['auth_step_up_user_id'] = 8;
$check(app_session_step_up_is_valid() === false, 'Step-up grant cannot cross authenticated user IDs');

app_session_step_up_grant(7, 'totp');
app_session_step_up_clear();
$check(app_session_step_up_is_valid() === false, 'explicit Step-up clear removes the grant');

$thrown = false;
try {
    app_session_step_up_grant(8, 'totp');
} catch (Throwable) {
    $thrown = true;
}
$check($thrown, 'Step-up cannot be granted for a different authenticated user');

session_write_close();
exit($failed === 0 ? 0 : 1);
