<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=sqlite');
$serverPort = (int) ($_SERVER['SERVER_PORT'] ?? 0);
if ($serverPort <= 0) {
    throw new RuntimeException('Test server port is unavailable.');
}
$dbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rss-sb03-http-' . $serverPort . '.sqlite';
putenv('DB_SQLITE_PATH=' . $dbPath);
putenv('SESSION_IDLE_TIMEOUT=600');
putenv('SESSION_ABSOLUTE_TIMEOUT=3600');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

require $root . '/app/bootstrap.php';

// SB-03 is an HTTP/session lifecycle test, not a production database test.
// V1.32-G made Session Registry state part of every authenticated session, so
// provide the minimal persistent SQLite registry table needed across the
// built-in server's separate HTTP requests. This keeps the production
// Session Registry path active instead of bypassing it in the test.
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

if ($path === '/logout.php') {
    require $root . '/public/logout.php';
    return true;
}

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

if ($path === '/__test/expire') {
    if (!app_session_is_authenticated()) {
        http_response_code(401);
        echo json_encode(['error' => 'not authenticated'], JSON_THROW_ON_ERROR);
        return true;
    }
    $_SESSION['last_activity'] = time() - SESSION_IDLE_TIMEOUT - 5;
    echo json_encode(['ok' => true, 'session_id' => session_id()], JSON_THROW_ON_ERROR);
    return true;
}

if ($path === '/__test/flash') {
    echo json_encode([
        'notice' => app_flash_take('auth_notice'),
        'session_id' => session_id(),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
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
