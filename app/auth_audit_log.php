<?php

declare(strict_types=1);

const AUTH_AUDIT_LOG_LIST_LIMIT = 20;

/** @return list<string> */
function auth_audit_log_allowed_events(): array
{
    return [
        'login',
        'logout',
        'two_factor',
        'recovery_code',
        'totp_enable',
        'totp_disable',
        'recovery_codes_generate',
        'password_change',
        'email_change',
        'session_revoke',
        'session_revoke_others',
        'step_up',
    ];
}

/** @return list<string> */
function auth_audit_log_allowed_methods(): array
{
    return [
        'password',
        'remember',
        'totp',
        'recovery',
        'password+totp',
        'password+recovery',
        'remember+totp',
        'remember+recovery',
        'single',
        'others',
    ];
}

function auth_audit_log_identity_hash(?string $email): ?string
{
    if (!is_string($email) || !auth_email_is_valid($email)) {
        return null;
    }
    $identity = auth_identity_key($email);
    return preg_match('/\A[a-f0-9]{64}\z/D', $identity) === 1 ? $identity : null;
}

function auth_audit_log_client_label(?string $userAgent = null): string
{
    if (function_exists('auth_session_registry_client_label')) {
        return auth_session_registry_client_label($userAgent);
    }
    return 'Browser / 端末';
}

/** Store only a keyed digest of REMOTE_ADDR. Raw IP addresses never enter the audit table. */
function auth_audit_log_ip_hash(?string $ipAddress = null): ?string
{
    $ipAddress ??= isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
        ? $_SERVER['REMOTE_ADDR']
        : '';
    $ipAddress = trim($ipAddress);
    if ($ipAddress === '' || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
        return null;
    }
    return hash_hmac('sha256', 'auth-audit-ip:v1:' . $ipAddress, (string) INI_HASH_KEY);
}

/**
 * Best-effort Security Activity write.
 *
 * Authentication and account changes must never fail merely because the audit
 * table is temporarily unavailable. No Password, OTP, Recovery Code, TOTP
 * Secret, encryption key, raw Session ID, full User-Agent, or raw IP is accepted.
 */
function auth_audit_log_record(
    string $event,
    string $result,
    ?int $userId = null,
    ?string $identityHash = null,
    ?string $method = null
): bool {
    if (!in_array($event, auth_audit_log_allowed_events(), true)) {
        return false;
    }
    if (!in_array($result, ['success', 'failure'], true)) {
        return false;
    }
    if ($userId !== null && $userId <= 0) {
        $userId = null;
    }
    if ($identityHash !== null && preg_match('/\A[a-f0-9]{64}\z/D', $identityHash) !== 1) {
        $identityHash = null;
    }
    if ($method !== null && !in_array($method, auth_audit_log_allowed_methods(), true)) {
        $method = null;
    }

    try {
        $stmt = conn_db()->prepare(
            'INSERT INTO ' . db_table_identifier('auth_audit_log') . ' ('
            . 'auth_audit_log_user_id, auth_audit_log_identity_hash, auth_audit_log_event, '
            . 'auth_audit_log_result, auth_audit_log_method, auth_audit_log_client_label, '
            . 'auth_audit_log_ip_hash, auth_audit_log_created_at'
            . ') VALUES ('
            . ':user_id, :identity_hash, :event, :result, :method, :client_label, :ip_hash, :created_at'
            . ')'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':identity_hash' => $identityHash,
            ':event' => $event,
            ':result' => $result,
            ':method' => $method,
            ':client_label' => auth_audit_log_client_label(),
            ':ip_hash' => auth_audit_log_ip_hash(),
            ':created_at' => date('Y-m-d H:i:s'),
        ]);
        return $stmt->rowCount() === 1;
    } catch (Throwable $exception) {
        // Keep the primary authentication/security action available if the
        // additive audit table has not yet been migrated or is temporarily down.
        if (defined('APP_DEBUG') && APP_DEBUG) {
            error_log('Authentication audit write failed: ' . $exception::class);
        }
        return false;
    }
}

/** Return the current keyed login identity stored for one active account. */
function auth_audit_log_user_identity_hash(int $userId): ?string
{
    if ($userId <= 0) {
        return null;
    }

    $stmt = conn_db()->prepare(
        'SELECT user_email FROM ' . db_table_identifier('user_info') . ' '
        . 'WHERE user_id = :user_id AND user_flag = 0 LIMIT 1'
    );
    $stmt->execute([':user_id' => $userId]);
    $identity = $stmt->fetchColumn();
    return is_string($identity) && preg_match('/\A[a-f0-9]{64}\z/D', $identity) === 1 ? $identity : null;
}

/**
 * Recent owner-scoped Security Activity.
 * Login failures are associated through the same keyed identity used by login;
 * raw email addresses are never stored or queried from the audit table.
 *
 * @return list<array{event:string,result:string,method:?string,client_label:string,created_at:string}>
 */
function auth_audit_log_list(int $userId, int $limit = AUTH_AUDIT_LOG_LIST_LIMIT): array
{
    if ($userId <= 0) {
        return [];
    }
    $limit = max(1, min(AUTH_AUDIT_LOG_LIST_LIMIT, $limit));
    $identityHash = auth_audit_log_user_identity_hash($userId);

    $sql = 'SELECT auth_audit_log_event, auth_audit_log_result, auth_audit_log_method, '
        . 'auth_audit_log_client_label, auth_audit_log_created_at '
        . 'FROM ' . db_table_identifier('auth_audit_log') . ' '
        . 'WHERE auth_audit_log_user_id = :user_id';
    $params = [':user_id' => $userId];
    if ($identityHash !== null) {
        $sql .= ' OR auth_audit_log_identity_hash = :identity_hash';
        $params[':identity_hash'] = $identityHash;
    }
    $sql .= ' ORDER BY auth_audit_log_id DESC LIMIT ' . $limit;

    $stmt = conn_db()->prepare($sql);
    $stmt->execute($params);

    $events = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $event = (string) ($row['auth_audit_log_event'] ?? '');
        $result = (string) ($row['auth_audit_log_result'] ?? '');
        $method = $row['auth_audit_log_method'] ?? null;
        if (!in_array($event, auth_audit_log_allowed_events(), true)
            || !in_array($result, ['success', 'failure'], true)) {
            continue;
        }
        $events[] = [
            'event' => $event,
            'result' => $result,
            'method' => is_string($method) && in_array($method, auth_audit_log_allowed_methods(), true) ? $method : null,
            'client_label' => substr((string) ($row['auth_audit_log_client_label'] ?? 'Browser / 端末'), 0, 120),
            'created_at' => (string) ($row['auth_audit_log_created_at'] ?? ''),
        ];
    }
    return $events;
}
