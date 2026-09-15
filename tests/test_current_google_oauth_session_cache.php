<?php

declare(strict_types=1);

session_id('current-google-cache-' . bin2hex(random_bytes(4)));
session_start();
require_once dirname(__DIR__) . '/app/session.php';

$failed = 0;
$check = static function (bool $condition, string $message) use (&$failed): void {
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) { $failed++; }
};

$now = time();
$_SESSION = [
    'user_id' => 7,
    'authenticated_at' => $now,
    'last_activity' => $now,
    'csrf_token' => str_repeat('a', 64),
];
$refreshHash = hash('sha256', 'refresh-token');
app_session_mail_google_access_token_store(7, 41, $refreshHash, 'short-lived-access-token', $now + 3600);

$check(
    app_session_mail_google_access_token_get(7, 41, $refreshHash, $now) === 'short-lived-access-token',
    'access token cache returns a valid owner/account/refresh-bound token'
);
$check(
    app_session_mail_google_access_token_get(7, 41, hash('sha256', 'rotated-refresh-token'), $now) === null,
    'refresh credential rotation invalidates the cached token'
);
$check(
    app_session_mail_google_access_token_get(7, 41, $refreshHash, $now + 3500) === null,
    'token is rejected before its final two-minute expiry window'
);
$check(
    app_session_mail_google_access_token_get(8, 41, $refreshHash, $now) === null,
    'cached token cannot cross authenticated owners'
);

app_session_clear_authentication();
$check(!isset($_SESSION['mail_google_access_tokens']), 'logout/authentication clearing removes cached Gmail access tokens');

session_write_close();
exit($failed === 0 ? 0 : 1);
