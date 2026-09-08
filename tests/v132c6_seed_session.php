<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$mode = $argv[1] ?? 'pending';

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');
putenv('DB_HOST=test');
putenv('DB_NAME=test');
putenv('DB_USER=test');
putenv('DB_PASSWORD=test');
putenv('SESSION_COOKIE_NAME=iguguru_v132c6_http');
putenv('AUTH_2FA_PENDING_TIMEOUT=300');

require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/session.php';

app_session_start();

if ($mode === 'pending') {
    app_session_begin_pending_auth(77, 'password', true);
} elseif ($mode === 'pending-remember') {
    app_session_begin_pending_auth(77, 'remember', false);
} elseif ($mode === 'invalid-source') {
    app_session_begin_pending_auth(77, 'password', true);
    $_SESSION['auth_pending_source'] = 'tampered';
} elseif ($mode === 'future-start') {
    app_session_begin_pending_auth(77, 'password', true);
    $_SESSION['auth_pending_started_at'] = time() + 120;
} elseif ($mode === 'missing-start') {
    app_session_begin_pending_auth(77, 'password', true);
    unset($_SESSION['auth_pending_started_at']);
} elseif ($mode === 'authenticated') {
    app_session_login(77);
} else {
    throw new InvalidArgumentException('Unknown seed mode.');
}

$result = [
    'session_name' => session_name(),
    'session_id' => session_id(),
    'csrf_token' => app_csrf_token(),
    'authenticated' => app_session_is_authenticated(),
    'pending' => app_session_has_pending_auth(),
];

session_write_close();
echo json_encode($result, JSON_UNESCAPED_SLASHES), PHP_EOL;
