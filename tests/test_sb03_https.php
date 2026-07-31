<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');
putenv('DB_HOST=test');
putenv('DB_NAME=test');
putenv('DB_USER=test');
putenv('DB_PASSWORD=test');
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';
require $root . '/app/common/common_conf.php';
require $root . '/app/session.php';
app_session_start();
$params = session_get_cookie_params();
if (($params['secure'] ?? false) !== true) {
    fwrite(STDERR, "FAIL: HTTPS session cookie is not Secure\n");
    exit(1);
}
app_session_logout();
echo "PASS: HTTPS session cookie is Secure\n";
