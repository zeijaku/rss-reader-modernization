<?php

declare(strict_types=1);

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

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($path === '/logout.php') {
    require $root . '/public/logout.php';
    return true;
}

require $root . '/app/bootstrap.php';
app_session_start();

header('Content-Type: application/json; charset=UTF-8');
if ($path === '/__test/state') {
    echo json_encode([
        'authenticated' => app_session_is_authenticated(),
        'user_id' => app_session_user_id(),
        'session_id' => session_id(),
        'keys' => array_values(array_keys($_SESSION)),
        'csrf_token' => app_csrf_token(),
    ], JSON_THROW_ON_ERROR);
    return true;
}

if ($path === '/__test/login') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        return true;
    }
    app_session_login(42);
    echo json_encode([
        'authenticated' => true,
        'user_id' => app_session_user_id(),
        'session_id' => session_id(),
        'keys' => array_values(array_keys($_SESSION)),
        'csrf_token' => app_csrf_token(),
    ], JSON_THROW_ON_ERROR);
    return true;
}

http_response_code(404);
echo json_encode(['error' => 'not found'], JSON_THROW_ON_ERROR);
return true;
