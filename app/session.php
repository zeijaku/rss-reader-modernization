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

/** Start the application session and enforce idle/absolute/pending expiry. */
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
    $pendingExpired = false;
    $registryRevoked = false;
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
            // Keep the current CSRF token until Remember Me restoration decides
            // whether the browser can be re-authenticated. If restoration succeeds,
            // persistent_login_restore_session() rotates to a fresh token and keeps
            // this token only as a short grace token for an already-open page.
        } else {
            $_SESSION['last_activity'] = $now;
        }
    } elseif (app_session_has_pending_auth() && app_session_pending_is_expired($now)) {
        app_session_clear_authentication();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Unable to rotate an expired pending session identifier.');
        }
        $pendingExpired = true;
    }

    if (!$authenticationExpired && app_session_is_authenticated() && function_exists('auth_session_registry_validate_current')) {
        $registry = auth_session_registry_validate_current((int) $_SESSION['user_id'], $now);
        if (($registry['ok'] ?? false) !== true) {
            $reason = (string) ($registry['reason'] ?? 'invalid_session');
            app_session_clear_authentication();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            if (!session_regenerate_id(true)) {
                throw new RuntimeException('Unable to rotate an invalidated session identifier.');
            }

            if ($reason === 'expired') {
                $authenticationExpired = true;
            } else {
                $registryRevoked = true;
                if (function_exists('persistent_login_revoke_current')) {
                    persistent_login_revoke_current();
                }
            }
        }
    }

    $restored = false;
    if (!$pendingExpired
        && !$registryRevoked
        && !app_session_is_authenticated()
        && !app_session_has_pending_auth()
        && function_exists('persistent_login_restore_session')) {
        $restored = persistent_login_restore_session();
    }
    if ($pendingExpired) {
        app_flash_set('auth_notice', '2段階認証の有効期限が切れました。もう一度ログインしてください。', 'warning');
    } elseif ($registryRevoked) {
        app_flash_set('auth_notice', 'このセッションはログアウトされました。もう一度ログインしてください。', 'warning');
    } elseif ($authenticationExpired && !$restored) {
        app_flash_set('auth_notice', 'セッションの有効期限が切れました。もう一度ログインしてください。', 'warning');
    }

    app_csrf_token();
}

/** Persist the current session and release the file-session lock. */
function app_session_release(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    if (!session_write_close()) {
        throw new RuntimeException('Unable to write and release the application session.');
    }
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

function app_session_authenticated_at(): ?int
{
    if (!app_session_is_authenticated()) {
        return null;
    }
    $value = isset($_SESSION['authenticated_at']) ? (int) $_SESSION['authenticated_at'] : 0;
    return $value > 0 ? $value : null;
}

/** Session Registry material remains accessible only through app/session.php. */
function app_session_registry_token(): ?string
{
    $token = $_SESSION['auth_session_registry_token'] ?? null;
    return is_string($token) && preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1 ? $token : null;
}

function app_session_registry_id(): ?int
{
    $id = isset($_SESSION['auth_session_registry_id']) ? (int) $_SESSION['auth_session_registry_id'] : 0;
    return $id > 0 ? $id : null;
}

function app_session_registry_last_touch_at(): int
{
    return isset($_SESSION['auth_session_registry_last_touch_at'])
        ? max(0, (int) $_SESSION['auth_session_registry_last_touch_at'])
        : 0;
}

function app_session_registry_store(string $token, int $sessionId, int $lastTouchAt): void
{
    if (preg_match('/\A[a-f0-9]{64}\z/D', $token) !== 1 || $sessionId <= 0 || $lastTouchAt < 0) {
        throw new InvalidArgumentException('Invalid Session Registry state.');
    }
    $_SESSION['auth_session_registry_token'] = $token;
    $_SESSION['auth_session_registry_id'] = $sessionId;
    $_SESSION['auth_session_registry_last_touch_at'] = $lastTouchAt;
}

function app_session_registry_set_last_touch_at(int $timestamp): void
{
    if ($timestamp < 0) {
        throw new InvalidArgumentException('Invalid Session Registry touch timestamp.');
    }
    $_SESSION['auth_session_registry_last_touch_at'] = $timestamp;
}

/** Return whether this authenticated session still has a recent Security step-up grant. */
function app_session_step_up_is_valid(?int $now = null): bool
{
    if (!app_session_is_authenticated()) {
        app_session_step_up_clear();
        return false;
    }

    $now ??= time();
    $userId = app_session_user_id();
    $verifiedUserId = isset($_SESSION['auth_step_up_user_id']) ? (int) $_SESSION['auth_step_up_user_id'] : 0;
    $verifiedAt = isset($_SESSION['auth_step_up_verified_at']) ? (int) $_SESSION['auth_step_up_verified_at'] : 0;
    $method = $_SESSION['auth_step_up_method'] ?? null;

    $valid = $userId !== null
        && $verifiedUserId === $userId
        && $verifiedAt > 0
        && $verifiedAt <= ($now + 60)
        && ($now - $verifiedAt) <= AUTH_STEP_UP_TIMEOUT
        && is_string($method)
        && in_array($method, ['totp', 'recovery'], true);

    if (!$valid) {
        app_session_step_up_clear();
    }

    return $valid;
}

function app_session_step_up_verified_at(): ?int
{
    return app_session_step_up_is_valid() ? (int) $_SESSION['auth_step_up_verified_at'] : null;
}

function app_session_step_up_expires_at(): ?int
{
    $verifiedAt = app_session_step_up_verified_at();
    return $verifiedAt === null ? null : $verifiedAt + AUTH_STEP_UP_TIMEOUT;
}

/** Record a short-lived Security step-up grant inside the current PHP session only. */
function app_session_step_up_grant(int $userId, string $method): void
{
    if ($userId <= 0 || !in_array($method, ['totp', 'recovery'], true)) {
        throw new InvalidArgumentException('Valid Security step-up context is required.');
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        app_session_start();
    }
    if (app_session_user_id() !== $userId) {
        throw new RuntimeException('Security step-up user does not match the authenticated session.');
    }

    $_SESSION['auth_step_up_user_id'] = $userId;
    $_SESSION['auth_step_up_verified_at'] = time();
    $_SESSION['auth_step_up_method'] = $method;
}

function app_session_step_up_clear(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    unset(
        $_SESSION['auth_step_up_user_id'],
        $_SESSION['auth_step_up_verified_at'],
        $_SESSION['auth_step_up_method']
    );
}

/** True only for the temporary password/Remember verified state before TOTP. */
function app_session_has_pending_auth(): bool
{
    return isset($_SESSION['auth_pending_user_id']) && (int) $_SESSION['auth_pending_user_id'] > 0;
}

function app_session_pending_user_id(): ?int
{
    return app_session_has_pending_auth() ? (int) $_SESSION['auth_pending_user_id'] : null;
}

function app_session_pending_source(): ?string
{
    if (!app_session_has_pending_auth()) {
        return null;
    }
    $source = $_SESSION['auth_pending_source'] ?? null;
    return is_string($source) && in_array($source, ['password', 'remember'], true) ? $source : null;
}

function app_session_pending_remember_requested(): bool
{
    return app_session_has_pending_auth() && ($_SESSION['auth_pending_remember_requested'] ?? false) === true;
}

function app_session_pending_remember_selector(): ?string
{
    if (!app_session_has_pending_auth()) {
        return null;
    }
    $selector = $_SESSION['auth_pending_remember_selector'] ?? null;
    return is_string($selector) && preg_match('/\A[a-f0-9]{24}\z/D', $selector) === 1 ? $selector : null;
}

function app_session_pending_is_expired(?int $now = null): bool
{
    if (!app_session_has_pending_auth()) {
        return false;
    }
    $now ??= time();
    $startedAt = isset($_SESSION['auth_pending_started_at']) ? (int) $_SESSION['auth_pending_started_at'] : 0;
    $source = app_session_pending_source();
    return $startedAt <= 0
        || $startedAt > ($now + 60)
        || $source === null
        || ($now - $startedAt) > AUTH_2FA_PENDING_TIMEOUT;
}

/** Enter the temporary second-factor state without granting application authentication. */
function app_session_begin_pending_auth(int $userId, string $source, bool $rememberRequested = false, ?string $rememberSelector = null): void
{
    if ($userId <= 0 || !in_array($source, ['password', 'remember'], true)) {
        throw new InvalidArgumentException('Valid pending authentication context is required.');
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        app_session_start();
    }
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Unable to regenerate the pending session identifier.');
    }

    if ($rememberSelector !== null && preg_match('/\A[a-f0-9]{24}\z/D', $rememberSelector) !== 1) {
        throw new InvalidArgumentException('Invalid pending Remember selector.');
    }

    $_SESSION = [
        'auth_pending_user_id' => $userId,
        'auth_pending_started_at' => time(),
        'auth_pending_source' => $source,
        'auth_pending_remember_requested' => $source === 'password' && $rememberRequested,
        'csrf_token' => bin2hex(random_bytes(32)),
    ];
    if ($source === 'remember' && $rememberSelector !== null) {
        $_SESSION['auth_pending_remember_selector'] = $rememberSelector;
    }
}

/** Complete the pending state only for its recorded user and rotate the session again. */
function app_session_complete_pending_auth(): ?int
{
    $userId = app_session_pending_user_id();
    if ($userId === null || app_session_pending_is_expired()) {
        return null;
    }
    $rememberSelector = app_session_pending_remember_selector();
    app_session_login($userId);
    if ($rememberSelector !== null && function_exists('auth_session_registry_bind_remember_selector')) {
        auth_session_registry_bind_remember_selector($userId, $rememberSelector);
    }
    return $userId;
}

/** Cancel pending authentication and rotate back to a clean anonymous session. */
function app_session_cancel_pending_auth(): void
{
    app_session_clear_authentication();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    if (session_status() === PHP_SESSION_ACTIVE && !session_regenerate_id(true)) {
        throw new RuntimeException('Unable to rotate a cancelled pending session identifier.');
    }
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

    $preserveRegistry = app_session_is_authenticated()
        && app_session_user_id() === $userId
        && function_exists('auth_session_registry_current_token')
        && auth_session_registry_current_token() !== null;
    $registryToken = $preserveRegistry ? auth_session_registry_current_token() : null;
    $registryId = $preserveRegistry && function_exists('auth_session_registry_current_id')
        ? auth_session_registry_current_id()
        : null;

    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Unable to regenerate the session identifier.');
    }

    $_SESSION = [
        'user_id' => $userId,
        'authenticated_at' => time(),
        'last_activity' => time(),
        'csrf_token' => bin2hex(random_bytes(32)),
    ];
    if ($registryToken !== null) {
        $_SESSION['auth_session_registry_token'] = $registryToken;
        if ($registryId !== null) {
            $_SESSION['auth_session_registry_id'] = $registryId;
        }
        // Force a Registry touch after authentication/session rotation so the
        // mirrored idle/absolute expiry matches this fresh authenticated_at.
        $_SESSION['auth_session_registry_last_touch_at'] = 0;
    }

    if (function_exists('auth_session_registry_validate_current')) {
        $registry = auth_session_registry_validate_current($userId);
        if (($registry['ok'] ?? false) !== true) {
            app_session_clear_authentication();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            if (!session_regenerate_id(true)) {
                throw new RuntimeException('Unable to rotate a failed Session Registry login.');
            }
            throw new RuntimeException('Unable to register the authenticated session.');
        }
    }
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

/** Return the current CSRF token without creating or rotating it. */
function app_csrf_current_token(): ?string
{
    $token = $_SESSION['csrf_token'] ?? null;
    return is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) === 1 ? $token : null;
}

/**
 * Allow the token held by an already-open page for a short overlap after
 * silent Remember Me restoration. The normal current token remains primary.
 */
function app_csrf_allow_previous_token(string $token, int $graceSeconds = 300): void
{
    if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1 || $graceSeconds <= 0) {
        return;
    }

    $_SESSION['csrf_previous_token'] = $token;
    $_SESSION['csrf_previous_expires_at'] = time() + min($graceSeconds, 600);
}

function app_csrf_is_valid(?string $submittedToken): bool
{
    if (!is_string($submittedToken) || $submittedToken === '') {
        return false;
    }

    $sessionToken = app_csrf_current_token();
    if ($sessionToken !== null && hash_equals($sessionToken, $submittedToken)) {
        return true;
    }

    $previousToken = $_SESSION['csrf_previous_token'] ?? null;
    $previousExpiresAt = isset($_SESSION['csrf_previous_expires_at'])
        ? (int) $_SESSION['csrf_previous_expires_at']
        : 0;

    if ($previousExpiresAt <= time()) {
        unset($_SESSION['csrf_previous_token'], $_SESSION['csrf_previous_expires_at']);
        return false;
    }

    return is_string($previousToken)
        && preg_match('/^[a-f0-9]{64}$/', $previousToken) === 1
        && hash_equals($previousToken, $submittedToken);
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

    $logoutUserId = app_session_user_id();
    if ($logoutUserId !== null && function_exists('auth_session_registry_revoke_current')) {
        try {
            auth_session_registry_revoke_current($logoutUserId);
        } catch (Throwable $exception) {
            error_log('Session Registry logout update failed: ' . $exception::class);
        }
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
