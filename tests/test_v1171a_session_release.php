<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/session.php';

function v1171a_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
}

$directory = sys_get_temp_dir() . '/rss-reader-v1171a-' . bin2hex(random_bytes(6));
v1171a_assert(mkdir($directory, 0700), 'temporary session directory could not be created');

$sessionName = 'RSSV1171A';
$sessionId = 'v1171a' . bin2hex(random_bytes(8));

try {
    v1171a_assert(ini_set('session.save_path', $directory) !== false, 'temporary session.save_path could not be configured');
    v1171a_assert(ini_set('session.use_cookies', '0') !== false, 'session cookies could not be disabled for CLI test');
    session_name($sessionName);
    session_id($sessionId);

    v1171a_assert(session_start(), 'test session could not be started');
    $_SESSION['marker'] = 'persisted';

    app_session_release();
    v1171a_assert(session_status() === PHP_SESSION_NONE, 'app_session_release did not close the active session');

    // Calling release again must be a harmless no-op.
    app_session_release();
    v1171a_assert(session_status() === PHP_SESSION_NONE, 'inactive release unexpectedly changed session status');

    session_id($sessionId);
    v1171a_assert(session_start(), 'released session could not be reopened');
    v1171a_assert(($_SESSION['marker'] ?? null) === 'persisted', 'released session data was not persisted');
    session_write_close();

    echo "[PASS] V1.17.1-A session release contract\n";
} finally {
    foreach (glob($directory . '/*') ?: [] as $path) {
        @unlink($path);
    }
    @rmdir($directory);
}
