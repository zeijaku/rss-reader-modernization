<?php

declare(strict_types=1);

ob_start();
$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=sqlite');
putenv('DB_SQLITE_PATH=:memory:');
putenv('SESSION_COOKIE_NAME=iguguru_v11j_test');

require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/session.php';
require_once $root . '/app/api.php';

$results = [];
function v11j_session_check(bool $condition, string $message): void
{
    global $results;
    $results[] = [$condition, $message];
}

app_session_start();
app_session_login(77);
$beforeId = session_id();
$beforeToken = app_csrf_token();
$beforeAuthenticatedAt = (int) ($_SESSION['authenticated_at'] ?? 0);

$returnedToken = api_account_settings_rotate_session(77);
$afterId = session_id();
$afterToken = app_csrf_token();

v11j_session_check($beforeId !== '' && $afterId !== '', 'session identifiers are available during the test');
v11j_session_check(!hash_equals($beforeId, $afterId), 'successful Account Settings update rotates the session identifier');
v11j_session_check(!hash_equals($beforeToken, $afterToken), 'successful Account Settings update rotates the CSRF token');
v11j_session_check(hash_equals($returnedToken, $afterToken), 'API returns the rotated CSRF token');
v11j_session_check(app_session_user_id() === 77, 'authenticated user remains logged in after session rotation');
v11j_session_check((int) ($_SESSION['authenticated_at'] ?? 0) >= $beforeAuthenticatedAt, 'authenticated timestamp remains valid after rotation');
v11j_session_check((int) ($_SESSION['last_activity'] ?? 0) > 0, 'last activity timestamp is renewed after rotation');

$stableId = session_id();
$stableToken = app_csrf_token();
$otherUserToken = api_account_settings_rotate_session(78);
v11j_session_check($otherUserToken === '', 'session rotation refuses a different user id');
v11j_session_check(session_id() === $stableId && app_csrf_token() === $stableToken && app_session_user_id() === 77, 'different user request does not alter the authenticated session');

app_session_logout();
$output = ob_get_clean();
if (is_string($output) && $output !== '') {
    fwrite(STDERR, $output);
}

$failures = 0;
foreach ($results as [$condition, $message]) {
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) {
        $failures++;
    }
}
if ($failures > 0) {
    echo $failures . '/' . count($results) . " V1.1-J session checks failed.\n";
    exit(1);
}
echo 'All ' . count($results) . " V1.1-J session checks passed.\n";
