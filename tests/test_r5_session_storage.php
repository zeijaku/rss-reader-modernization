<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/session_storage.php';

// Reproduce the Legacy inherited configuration first.
ini_set('session.save_path', './session_file');
configure_session_storage();
$path = app_session_storage_path();

if (session_save_path() !== $path) {
    fwrite(STDERR, "FAIL: Legacy session.save_path was not overridden\n");
    exit(1);
}
if (!is_dir($path) || !is_writable($path)) {
    fwrite(STDERR, "FAIL: private session directory unavailable\n");
    exit(1);
}

$sessionId = 'r5roundtrip' . bin2hex(random_bytes(8));
session_id($sessionId);
if (!session_start()) {
    fwrite(STDERR, "FAIL: first session_start\n");
    exit(1);
}
$_SESSION['user_id'] = 4242;
$_SESSION['probe'] = 'persisted';
session_write_close();

// Simulate the redirect request by reopening the same persisted session.
$_SESSION = [];
session_id($sessionId);
if (!session_start()) {
    fwrite(STDERR, "FAIL: second session_start\n");
    exit(1);
}
$ok = ($_SESSION['user_id'] ?? null) === 4242
    && ($_SESSION['probe'] ?? null) === 'persisted';
session_destroy();

$sessionFile = $path . DIRECTORY_SEPARATOR . 'sess_' . $sessionId;
if (is_file($sessionFile)) {
    @unlink($sessionFile);
}

if (!$ok) {
    fwrite(STDERR, "FAIL: session did not survive close/reopen\n");
    exit(1);
}

echo "PASS: Legacy save_path override + private session roundtrip\n";
