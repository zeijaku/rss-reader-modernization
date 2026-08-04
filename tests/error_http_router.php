<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (preg_match('#^/__error/(403|404|500|503)$#', (string) $path, $matches) === 1) {
    $_SERVER['REDIRECT_STATUS'] = $matches[1];
    $_SERVER['SCRIPT_NAME'] = '/public/error.php';
    require $root . '/public/error.php';
    return true;
}

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');
putenv('DB_HOST=test');
putenv('DB_NAME=test');
putenv('DB_USER=test');
putenv('DB_PASSWORD=test');

if ($path === '/__api-exception' || $path === '/__api-config-exception') {
    define('APP_RESPONSE_FORMAT', 'json');
}
if ($path === '/__config-exception' || $path === '/__api-config-exception') {
    putenv('DB_TABLE_PREFIX=bad-prefix');
}

require $root . '/app/bootstrap.php';
throw new RuntimeException('sensitive-test-message /srv/private/config.php password=secret');
