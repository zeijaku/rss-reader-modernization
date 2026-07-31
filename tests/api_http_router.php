<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($uri === '/__test_login') {
    require_once $root . '/app/bootstrap.php';
    app_session_start();
    app_session_login(42);
    header('Content-Type: text/plain; charset=UTF-8');
    echo app_csrf_token();
    return true;
}

if ($uri === '/api_v1.php') {
    require $root . '/public/api_v1.php';
    return true;
}

$file = realpath($root . '/public' . $uri);
$public = realpath($root . '/public');
if (is_string($file) && is_string($public) && str_starts_with($file, $public . DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}

if ($uri === '/' || $uri === '/index.php') {
    require $root . '/public/index.php';
    return true;
}

return false;
