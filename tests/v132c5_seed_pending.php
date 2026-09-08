<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$expired = in_array('--expired', $argv, true);
$source = in_array('--remember', $argv, true) ? 'remember' : 'password';

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');
putenv('DB_HOST=test');
putenv('DB_NAME=test');
putenv('DB_USER=test');
putenv('DB_PASSWORD=test');
putenv('SESSION_COOKIE_NAME=iguguru_v132c5_http');
putenv('AUTH_2FA_PENDING_TIMEOUT=300');

require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/session.php';

app_session_start();
app_session_begin_pending_auth(77, $source, $source === 'password');
if ($expired) {
    $_SESSION['auth_pending_started_at'] = time() - AUTH_2FA_PENDING_TIMEOUT - 5;
}

$result = [
    'session_name' => session_name(),
    'session_id' => session_id(),
    'csrf_token' => app_csrf_token(),
    'source' => app_session_pending_source(),
];

session_write_close();
echo json_encode($result, JSON_UNESCAPED_SLASHES), PHP_EOL;
