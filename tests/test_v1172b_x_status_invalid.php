<?php

declare(strict_types=1);

define('APP_X_BEARER_TOKEN', "invalid\nvalue");
define('APP_X_CACHE_DIR', sys_get_temp_dir() . '/rss-reader-v1172b-x-invalid-' . bin2hex(random_bytes(4)));
define('APP_ENV', 'testing');
define('APP_DEBUG', false);
define('APP_LOG_ENABLED', false);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/api.php';

$status = x_widget_connection_status();
$create = api_widget_x_create(1, []);
$ok = ($status['state'] ?? null) === 'invalid_format'
    && ($status['configured'] ?? false) === true
    && ($status['can_add'] ?? true) === false
    && ($create['body']['error']['code'] ?? null) === 'x_token_invalid_format';
echo ($ok ? 'PASS' : 'FAIL') . ": malformed APP_X_BEARER_TOKEN is visible and server-side create is blocked\n";
exit($ok ? 0 : 1);
