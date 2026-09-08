<?php

declare(strict_types=1);

const AUTH_SESSION_REGISTRY_TOKEN_BYTES = 32;
const AUTH_SESSION_REGISTRY_TOUCH_INTERVAL = 60;
const AUTH_SESSION_REGISTRY_LIST_LIMIT = 20;

/** Return a database datetime in the application's existing Asia/Tokyo convention. */
function auth_session_registry_datetime(int $timestamp): string
{
    return (new DateTimeImmutable('@' . $timestamp))
        ->setTimezone(new DateTimeZone('Asia/Tokyo'))
        ->format('Y-m-d H:i:s');
}

function auth_session_registry_datetime_to_timestamp(string $value): ?int
{
    $timezone = new DateTimeZone('Asia/Tokyo');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $timezone);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date instanceof DateTimeImmutable) {
        return null;
    }
    if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
        return null;
    }
    if ($date->format('Y-m-d H:i:s') !== $value) {
        return null;
    }

    return $date->getTimestamp();
}

function auth_session_registry_generate_token(): string
{
    return bin2hex(random_bytes(AUTH_SESSION_REGISTRY_TOKEN_BYTES));
}

function auth_session_registry_token_is_valid(mixed $token): bool
{
    return is_string($token) && preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1;
}

function auth_session_registry_hash_token(int $userId, string $token): string
{
    if ($userId <= 0 || !auth_session_registry_token_is_valid($token)) {
        throw new InvalidArgumentException('Valid Session Registry token material is required.');
    }

    return hash('sha256', 'auth-session:' . $userId . ':' . $token);
}

function auth_session_registry_current_token(): ?string
{
    $token = function_exists('app_session_registry_token') ? app_session_registry_token() : null;
    return auth_session_registry_token_is_valid($token) ? $token : null;
}

function auth_session_registry_current_token_hash(int $userId): ?string
{
    $token = auth_session_registry_current_token();
    return $token === null ? null : auth_session_registry_hash_token($userId, $token);
}

function auth_session_registry_current_id(): ?int
{
    return function_exists('app_session_registry_id') ? app_session_registry_id() : null;
}

function auth_session_registry_remember_selector_is_valid(mixed $selector): bool
{
    return is_string($selector) && preg_match('/\A[a-f0-9]{24}\z/D', $selector) === 1;
}

/** Build a privacy-bounded client label without storing a full fingerprint or IP address. */
function auth_session_registry_client_label(?string $userAgent = null): string
{
    $userAgent ??= isset($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT'])
        ? $_SERVER['HTTP_USER_AGENT']
        : '';
    $userAgent = str_replace(["\r", "\n", "\0"], ' ', $userAgent);

    $browser = 'Browser';
    if (preg_match('/Edg(?:A|iOS)?\/([0-9]+)/i', $userAgent, $matches) === 1) {
        $browser = 'Edge ' . $matches[1];
    } elseif (preg_match('/OPR\/([0-9]+)/i', $userAgent, $matches) === 1) {
        $browser = 'Opera ' . $matches[1];
    } elseif (preg_match('/(?:Firefox|FxiOS)\/([0-9]+)/i', $userAgent, $matches) === 1) {
        $browser = 'Firefox ' . $matches[1];
    } elseif (preg_match('/(?:Chrome|CriOS)\/([0-9]+)/i', $userAgent, $matches) === 1) {
        $browser = 'Chrome ' . $matches[1];
    } elseif (preg_match('/Version\/([0-9]+).*Safari\//i', $userAgent, $matches) === 1) {
        $browser = 'Safari ' . $matches[1];
    }

    $platform = '端末';
    if (stripos($userAgent, 'iPad') !== false) {
        $platform = 'iPad';
    } elseif (stripos($userAgent, 'iPhone') !== false) {
        $platform = 'iPhone';
    } elseif (stripos($userAgent, 'Android') !== false) {
        $platform = 'Android';
    } elseif (stripos($userAgent, 'Windows') !== false) {
        $platform = 'Windows';
    } elseif (stripos($userAgent, 'Macintosh') !== false || stripos($userAgent, 'Mac OS X') !== false) {
        $platform = 'macOS';
    } elseif (stripos($userAgent, 'Linux') !== false) {
        $platform = 'Linux';
    }

    return substr($browser . ' / ' . $platform, 0, 120);
}

/** Return the Session Registry expiry mirrored from the PHP idle/absolute policy. */
function auth_session_registry_expiry_timestamp(?int $now = null): int
{
    $now ??= time();
    $authenticatedAt = function_exists('app_session_authenticated_at') ? (app_session_authenticated_at() ?? $now) : $now;
    if ($authenticatedAt <= 0 || $authenticatedAt > ($now + 60)) {
        $authenticatedAt = $now;
    }

    return min($authenticatedAt + SESSION_ABSOLUTE_TIMEOUT, $now + SESSION_IDLE_TIMEOUT);
}

/** Register the current authenticated PHP session and keep the raw registry token only in the session file. */
/** Bind a structurally valid existing Remember cookie when adopting a pre-G session. */
function auth_session_registry_adopt_current_remember_selector(int $userId): void
{
    if ($userId <= 0
        || !function_exists('persistent_login_cookie_value')
        || !function_exists('remember_token_parse')) {
        return;
    }

    $cookieValue = persistent_login_cookie_value();
    if (!is_string($cookieValue) || $cookieValue === '') {
        return;
    }

    $parsed = remember_token_parse($cookieValue);
    $selector = is_array($parsed) ? ($parsed['selector'] ?? null) : null;
    if (!auth_session_registry_remember_selector_is_valid($selector)) {
        return;
    }

    // The validator remains exclusively in the Remember cookie/token table.
    // Binding the selector here prevents a current pre-G Remember token from
    // being mistaken for an "other" token immediately after deployment.
    auth_session_registry_bind_remember_selector($userId, (string) $selector);
}

function auth_session_registry_register_current(int $userId): int
{
    if ($userId <= 0 || session_status() !== PHP_SESSION_ACTIVE || !function_exists('app_session_user_id') || app_session_user_id() !== $userId) {
        throw new RuntimeException('An authenticated PHP session is required for Session Registry registration.');
    }

    $token = auth_session_registry_generate_token();
    $tokenHash = auth_session_registry_hash_token($userId, $token);
    $now = time();

    $stmt = conn_db()->prepare(
        'INSERT INTO ' . db_table_identifier('auth_session') . ' ('
        . 'auth_session_user_id, auth_session_token_hash, auth_session_remember_selector, auth_session_client_label, '
        . 'auth_session_created_at, auth_session_last_seen_at, auth_session_expires_at, auth_session_revoked_at'
        . ') VALUES ('
        . ':user_id, :token_hash, NULL, :client_label, :created_at, :last_seen_at, :expires_at, NULL'
        . ')'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':token_hash' => $tokenHash,
        ':client_label' => auth_session_registry_client_label(),
        ':created_at' => auth_session_registry_datetime($now),
        ':last_seen_at' => auth_session_registry_datetime($now),
        ':expires_at' => auth_session_registry_datetime(auth_session_registry_expiry_timestamp($now)),
    ]);

    $sessionId = (int) conn_db()->lastInsertId();
    if ($sessionId <= 0) {
        throw new RuntimeException('Session Registry did not return a valid row id.');
    }

    if (!function_exists('app_session_registry_store')) {
        throw new RuntimeException('Session Registry state storage is unavailable.');
    }
    app_session_registry_store($token, $sessionId, $now);
    return $sessionId;
}

/**
 * Validate one authenticated PHP session against the DB registry.
 * Existing pre-G sessions have no registry token and are adopted once during deployment.
 *
 * @return array{ok:bool,reason:string,session_id?:int}
 */
function auth_session_registry_validate_current(int $userId, ?int $now = null): array
{
    if ($userId <= 0 || session_status() !== PHP_SESSION_ACTIVE || !function_exists('app_session_user_id') || app_session_user_id() !== $userId) {
        return ['ok' => false, 'reason' => 'invalid_session'];
    }

    $now ??= time();
    $token = auth_session_registry_current_token();
    if ($token === null) {
        $sessionId = auth_session_registry_register_current($userId);
        auth_session_registry_adopt_current_remember_selector($userId);
        return ['ok' => true, 'reason' => 'adopted', 'session_id' => $sessionId];
    }

    $tokenHash = auth_session_registry_hash_token($userId, $token);
    $stmt = conn_db()->prepare(
        'SELECT auth_session_id, auth_session_expires_at, auth_session_revoked_at '
        . 'FROM ' . db_table_identifier('auth_session') . ' '
        . 'WHERE auth_session_user_id = :user_id AND auth_session_token_hash = :token_hash LIMIT 1'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':token_hash' => $tokenHash,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return ['ok' => false, 'reason' => 'missing'];
    }

    $sessionId = (int) ($row['auth_session_id'] ?? 0);
    $revokedAt = $row['auth_session_revoked_at'] ?? null;
    if ($sessionId <= 0 || (is_string($revokedAt) && $revokedAt !== '')) {
        return ['ok' => false, 'reason' => 'revoked'];
    }

    $expiresAt = auth_session_registry_datetime_to_timestamp((string) ($row['auth_session_expires_at'] ?? ''));
    if ($expiresAt === null || $expiresAt <= $now) {
        return ['ok' => false, 'reason' => 'expired'];
    }

    if (function_exists('app_session_registry_store')) {
        app_session_registry_store($token, $sessionId, function_exists('app_session_registry_last_touch_at') ? app_session_registry_last_touch_at() : 0);
    }
    $lastTouch = function_exists('app_session_registry_last_touch_at') ? app_session_registry_last_touch_at() : 0;
    if ($lastTouch <= 0 || ($now - $lastTouch) >= AUTH_SESSION_REGISTRY_TOUCH_INTERVAL) {
        $stmt = conn_db()->prepare(
            'UPDATE ' . db_table_identifier('auth_session') . ' '
            . 'SET auth_session_last_seen_at = :last_seen_at, auth_session_expires_at = :expires_at, '
            . 'auth_session_client_label = :client_label '
            . 'WHERE auth_session_id = :session_id AND auth_session_user_id = :user_id '
            . 'AND auth_session_token_hash = :token_hash AND auth_session_revoked_at IS NULL'
        );
        $stmt->execute([
            ':last_seen_at' => auth_session_registry_datetime($now),
            ':expires_at' => auth_session_registry_datetime(auth_session_registry_expiry_timestamp($now)),
            ':client_label' => auth_session_registry_client_label(),
            ':session_id' => $sessionId,
            ':user_id' => $userId,
            ':token_hash' => $tokenHash,
        ]);
        if ($stmt->rowCount() === 0) {
            return ['ok' => false, 'reason' => 'revoked'];
        }
        if (function_exists('app_session_registry_set_last_touch_at')) {
            app_session_registry_set_last_touch_at($now);
        }
    }

    return ['ok' => true, 'reason' => 'active', 'session_id' => $sessionId];
}

/** Bind the selector of the current Remember Token without exposing its validator or cookie value. */
function auth_session_registry_bind_remember_selector(int $userId, string $selector): bool
{
    if ($userId <= 0 || !auth_session_registry_remember_selector_is_valid($selector)) {
        return false;
    }

    $tokenHash = auth_session_registry_current_token_hash($userId);
    if ($tokenHash === null) {
        return false;
    }

    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        // A fixed Remember selector moves to the newest PHP session after restore.
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('auth_session') . ' '
            . 'SET auth_session_remember_selector = NULL '
            . 'WHERE auth_session_user_id = :user_id AND auth_session_remember_selector = :selector '
            . 'AND auth_session_token_hash <> :token_hash'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':selector' => $selector,
            ':token_hash' => $tokenHash,
        ]);

        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('auth_session') . ' '
            . 'SET auth_session_remember_selector = :selector '
            . 'WHERE auth_session_user_id = :user_id AND auth_session_token_hash = :token_hash '
            . 'AND auth_session_revoked_at IS NULL'
        );
        $stmt->execute([
            ':selector' => $selector,
            ':user_id' => $userId,
            ':token_hash' => $tokenHash,
        ]);
        $ok = $stmt->rowCount() === 1;

        if ($started) {
            $pdo->commit();
        }
        return $ok;
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/** @return list<array{id:int,client_label:string,created_at:string,last_seen_at:string,is_current:bool,remembered:bool}> */
function auth_session_registry_list(int $userId, ?int $now = null): array
{
    if ($userId <= 0) {
        return [];
    }

    $now ??= time();
    $currentHash = auth_session_registry_current_token_hash($userId);
    $stmt = conn_db()->prepare(
        'SELECT auth_session_id, auth_session_token_hash, auth_session_remember_selector, auth_session_client_label, '
        . 'auth_session_created_at, auth_session_last_seen_at '
        . 'FROM ' . db_table_identifier('auth_session') . ' '
        . 'WHERE auth_session_user_id = :user_id AND auth_session_revoked_at IS NULL '
        . 'AND auth_session_expires_at > :now '
        . 'ORDER BY auth_session_last_seen_at DESC, auth_session_id DESC LIMIT ' . AUTH_SESSION_REGISTRY_LIST_LIMIT
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':now' => auth_session_registry_datetime($now),
    ]);

    $sessions = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int) ($row['auth_session_id'] ?? 0);
        $tokenHash = (string) ($row['auth_session_token_hash'] ?? '');
        if ($id <= 0 || preg_match('/\A[a-f0-9]{64}\z/D', $tokenHash) !== 1) {
            continue;
        }
        $sessions[] = [
            'id' => $id,
            'client_label' => (string) ($row['auth_session_client_label'] ?? 'Browser / 端末'),
            'created_at' => (string) ($row['auth_session_created_at'] ?? ''),
            'last_seen_at' => (string) ($row['auth_session_last_seen_at'] ?? ''),
            'is_current' => $currentHash !== null && hash_equals($currentHash, $tokenHash),
            'remembered' => auth_session_registry_remember_selector_is_valid($row['auth_session_remember_selector'] ?? null),
        ];
    }

    return $sessions;
}

/** Revoke one other active session and its bound Remember Token when present. */
function auth_session_registry_revoke_one(int $userId, int $sessionId): array
{
    if ($userId <= 0 || $sessionId <= 0) {
        return ['ok' => false, 'reason' => 'not_found'];
    }
    $currentHash = auth_session_registry_current_token_hash($userId);
    if ($currentHash === null) {
        return ['ok' => false, 'reason' => 'current_unknown'];
    }

    $pdo = conn_db();
    $pdo->beginTransaction();
    try {
        $sql = 'SELECT auth_session_token_hash, auth_session_remember_selector FROM ' . db_table_identifier('auth_session') . ' '
            . 'WHERE auth_session_id = :session_id AND auth_session_user_id = :user_id '
            . 'AND auth_session_revoked_at IS NULL LIMIT 1';
        if (strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':session_id' => $sessionId, ':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            $pdo->rollBack();
            return ['ok' => false, 'reason' => 'not_found'];
        }

        $targetHash = (string) ($row['auth_session_token_hash'] ?? '');
        if ($targetHash !== '' && hash_equals($currentHash, $targetHash)) {
            $pdo->rollBack();
            return ['ok' => false, 'reason' => 'current_session'];
        }

        $selector = $row['auth_session_remember_selector'] ?? null;
        if (auth_session_registry_remember_selector_is_valid($selector)) {
            remember_token_revoke_selector_for_user($userId, $selector, $pdo);
        }

        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('auth_session') . ' SET auth_session_revoked_at = :revoked_at '
            . 'WHERE auth_session_id = :session_id AND auth_session_user_id = :user_id AND auth_session_revoked_at IS NULL'
        );
        $stmt->execute([
            ':revoked_at' => auth_session_registry_datetime(time()),
            ':session_id' => $sessionId,
            ':user_id' => $userId,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Session Registry revoke did not update exactly one row.');
        }

        $pdo->commit();
        return ['ok' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/**
 * Revoke every session for a user except one token hash. When requested, also
 * revoke Remember Tokens linked to those sessions.
 */
function auth_session_registry_revoke_user(
    int $userId,
    ?string $exceptTokenHash = null,
    ?PDO $pdo = null,
    bool $revokeRememberSelectors = true
): int {
    if ($userId <= 0) {
        return 0;
    }
    if ($exceptTokenHash !== null && preg_match('/\A[a-f0-9]{64}\z/D', $exceptTokenHash) !== 1) {
        throw new InvalidArgumentException('Invalid Session Registry exception token hash.');
    }

    $conn = $pdo ?? conn_db();
    $started = !$conn->inTransaction();
    if ($started) {
        $conn->beginTransaction();
    }

    try {
        $where = 'auth_session_user_id = :user_id AND auth_session_revoked_at IS NULL';
        $params = [':user_id' => $userId];
        if ($exceptTokenHash !== null) {
            $where .= ' AND auth_session_token_hash <> :except_token_hash';
            $params[':except_token_hash'] = $exceptTokenHash;
        }

        if ($revokeRememberSelectors) {
            $stmt = $conn->prepare(
                'SELECT auth_session_remember_selector FROM ' . db_table_identifier('auth_session') . ' WHERE ' . $where
            );
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $selector) {
                if (auth_session_registry_remember_selector_is_valid($selector)) {
                    remember_token_revoke_selector_for_user($userId, (string) $selector, $conn);
                }
            }
        }

        $updateParams = $params;
        $updateParams[':revoked_at'] = auth_session_registry_datetime(time());
        $stmt = $conn->prepare(
            'UPDATE ' . db_table_identifier('auth_session') . ' SET auth_session_revoked_at = :revoked_at WHERE ' . $where
        );
        $stmt->execute($updateParams);
        $count = $stmt->rowCount();

        if ($started) {
            $conn->commit();
        }
        return $count;
    } catch (Throwable $exception) {
        if ($started && $conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $exception;
    }
}

/** Revoke every other active session and every Remember Token not bound to the current session. */
function auth_session_registry_revoke_others(int $userId): int
{
    $currentHash = auth_session_registry_current_token_hash($userId);
    if ($currentHash === null) {
        throw new RuntimeException('Current Session Registry token is unavailable.');
    }

    $pdo = conn_db();
    $pdo->beginTransaction();
    try {
        $currentSelector = null;
        $stmt = $pdo->prepare(
            'SELECT auth_session_remember_selector FROM ' . db_table_identifier('auth_session') . ' '
            . 'WHERE auth_session_user_id = :user_id AND auth_session_token_hash = :token_hash '
            . 'AND auth_session_revoked_at IS NULL LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId, ':token_hash' => $currentHash]);
        $selector = $stmt->fetchColumn();
        if (auth_session_registry_remember_selector_is_valid($selector)) {
            $currentSelector = (string) $selector;
        }

        $count = auth_session_registry_revoke_user($userId, $currentHash, $pdo, true);
        remember_token_revoke_user_except_selector($userId, $currentSelector, $pdo);
        $pdo->commit();
        return $count;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/** Mark the current registry row revoked during an explicit Logout. */
function auth_session_registry_revoke_current(int $userId): void
{
    if ($userId <= 0) {
        return;
    }
    $tokenHash = auth_session_registry_current_token_hash($userId);
    if ($tokenHash === null) {
        return;
    }

    $stmt = conn_db()->prepare(
        'UPDATE ' . db_table_identifier('auth_session') . ' SET auth_session_revoked_at = :revoked_at '
        . 'WHERE auth_session_user_id = :user_id AND auth_session_token_hash = :token_hash '
        . 'AND auth_session_revoked_at IS NULL'
    );
    $stmt->execute([
        ':revoked_at' => auth_session_registry_datetime(time()),
        ':user_id' => $userId,
        ':token_hash' => $tokenHash,
    ]);
}
