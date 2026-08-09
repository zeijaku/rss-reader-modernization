<?php

declare(strict_types=1);

require_once __DIR__ . '/session_storage.php';

/** True when the current request is HTTPS without trusting arbitrary proxy headers. */
function app_request_is_https(): bool
{
    $https = $_SERVER['HTTPS'] ?? null;
    if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
        return true;
    }

    return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

/** Configure PHP session security before session_start(). */
function app_session_configure(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    configure_session_storage();

    $settings = [
        'session.use_strict_mode' => '1',
        'session.use_only_cookies' => '1',
        'session.use_trans_sid' => '0',
        'session.cookie_httponly' => '1',
        'session.cookie_samesite' => 'Lax',
        'session.cookie_lifetime' => '0',
        // Dynamic response cache headers are controlled explicitly by the application.
        'session.cache_limiter' => '',
        'session.gc_maxlifetime' => (string) max(SESSION_ABSOLUTE_TIMEOUT, SESSION_IDLE_TIMEOUT),
    ];

    foreach ($settings as $name => $value) {
        if (ini_set($name, $value) === false) {
            throw new RuntimeException('Unable to configure PHP session security.');
        }
    }

    session_name(SESSION_COOKIE_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => app_request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** Start the application session and enforce idle/absolute expiry. */
function app_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    app_session_configure();

    if (!session_start()) {
        throw new RuntimeException('Unable to start the application session.');
    }

    $now = time();
    $authenticationExpired = false;
    if (isset($_SESSION['user_id'])) {
        $authenticatedAt = isset($_SESSION['authenticated_at']) ? (int) $_SESSION['authenticated_at'] : 0;
        $lastActivity = isset($_SESSION['last_activity']) ? (int) $_SESSION['last_activity'] : 0;

        $authenticationExpired = $authenticatedAt <= 0
            || $lastActivity <= 0
            || ($now - $lastActivity) > SESSION_IDLE_TIMEOUT
            || ($now - $authenticatedAt) > SESSION_ABSOLUTE_TIMEOUT;

        if ($authenticationExpired) {
            app_session_clear_authentication();
            if (!session_regenerate_id(true)) {
                throw new RuntimeException('Unable to rotate an expired session identifier.');
            }
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } else {
            $_SESSION['last_activity'] = $now;
        }
    }

    $restored = false;
    if (!app_session_is_authenticated() && function_exists('persistent_login_restore_session')) {
        $restored = persistent_login_restore_session();
    }
    if ($authenticationExpired && !$restored) {
        app_flash_set('auth_notice', 'セッションの有効期限が切れました。もう一度ログインしてください。', 'warning');
    }

    app_csrf_token();
}

function app_session_is_authenticated(): bool
{
    return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
}

function app_session_user_id(): ?int
{
    if (!app_session_is_authenticated()) {
        return null;
    }

    return (int) $_SESSION['user_id'];
}

/** Establish a fresh authenticated session after successful credential verification. */
function app_session_login(int $userId): void
{
    if ($userId <= 0) {
        throw new InvalidArgumentException('A positive user id is required.');
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        app_session_start();
    }

    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Unable to regenerate the session identifier.');
    }

    $_SESSION = [
        'user_id' => $userId,
        'authenticated_at' => time(),
        'last_activity' => time(),
        'csrf_token' => bin2hex(random_bytes(32)),
    ];
}

/** Remove authenticated state but keep a valid anonymous session. */
function app_session_clear_authentication(): void
{
    $csrfToken = $_SESSION['csrf_token'] ?? null;
    $_SESSION = [];

    if (is_string($csrfToken) && preg_match('/^[a-f0-9]{64}$/', $csrfToken) === 1) {
        $_SESSION['csrf_token'] = $csrfToken;
    } else {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/** Return the per-session CSRF token, creating it when needed. */
function app_csrf_token(): string
{
    $token = $_SESSION['csrf_token'] ?? null;
    if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
    }

    return $token;
}

function app_csrf_is_valid(?string $submittedToken): bool
{
    if (!is_string($submittedToken) || $submittedToken === '') {
        return false;
    }

    $sessionToken = $_SESSION['csrf_token'] ?? null;
    return is_string($sessionToken) && hash_equals($sessionToken, $submittedToken);
}


/** Store a one-time message in the current anonymous or authenticated session. */
function app_flash_set(string $key, string $message, string $type = 'info'): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException('An active session is required for a flash message.');
    }
    if (preg_match('/^[a-z0-9_.-]{1,64}$/', $key) !== 1) {
        throw new InvalidArgumentException('A valid flash message key is required.');
    }
    if (!in_array($type, ['danger', 'success', 'info', 'warning'], true)) {
        $type = 'info';
    }
    $_SESSION['flash'][$key] = [
        'message' => $message,
        'type' => $type,
    ];
}

/** @return array{message:string,type:string}|null */
function app_flash_take(string $key): ?array
{
    $flash = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    if (isset($_SESSION['flash']) && $_SESSION['flash'] === []) {
        unset($_SESSION['flash']);
    }
    if (!is_array($flash) || !isset($flash['message'], $flash['type']) || !is_string($flash['message']) || !is_string($flash['type'])) {
        return null;
    }
    return [
        'message' => $flash['message'],
        'type' => in_array($flash['type'], ['danger', 'success', 'info', 'warning'], true) ? $flash['type'] : 'info',
    ];
}

/** Destroy the server session and expire the cookie with matching attributes. */
function app_session_logout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $cookieName = session_name();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => (string) ($params['samesite'] ?? 'Lax'),
        ]);
    }

    session_destroy();
    if ($cookieName !== '') {
        unset($_COOKIE[$cookieName]);
    }
    session_id('');
}
