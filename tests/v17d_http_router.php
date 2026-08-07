<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($path === '/private') {
    require_once $root . '/app/response_cache.php';
    app_send_private_no_store_headers();
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><title>private</title>';
    return;
}

if ($path === '/api') {
    require_once $root . '/app/response_cache.php';
    app_send_no_store_headers();
    header('Content-Type: application/json; charset=UTF-8');
    echo '{"ok":true}';
    return;
}

if ($path === '/error') {
    require_once $root . '/app/error_response.php';
    app_render_error_page(404);
    return;
}

http_response_code(404);
echo 'not found';
