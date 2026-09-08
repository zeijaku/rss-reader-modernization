<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($uri === '/__test_login') {
    require_once $root . '/app/bootstrap.php';
    // V1.32-G Session Registry is part of the real login path. The HTTP fixture
    // provides only that additive table so login exercises the production
    // registry boundary; later API mutation tests still fail safely because
    // application content tables are intentionally absent.
    $pdo = conn_db();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ' . db_table_identifier('auth_session') . ' ('
        . 'auth_session_id INTEGER PRIMARY KEY AUTOINCREMENT,'
        . 'auth_session_user_id INTEGER NOT NULL,'
        . 'auth_session_token_hash TEXT NOT NULL UNIQUE,'
        . 'auth_session_remember_selector TEXT NULL,'
        . 'auth_session_client_label TEXT NOT NULL,'
        . 'auth_session_created_at TEXT NOT NULL,'
        . 'auth_session_last_seen_at TEXT NOT NULL,'
        . 'auth_session_expires_at TEXT NOT NULL,'
        . 'auth_session_revoked_at TEXT NULL'
        . ')'
    );
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
