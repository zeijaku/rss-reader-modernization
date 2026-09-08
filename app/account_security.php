<?php

declare(strict_types=1);

/**
 * Disable TOTP for one active account and remove every Recovery Code.
 * Persistent login tokens are also revoked because the account's authentication
 * policy has changed. Existing authenticated PHP sessions are handled later by
 * V1.32-G Session Management and are not forcibly logged out here.
 *
 * @return array{ok:bool,reason?:string}
 */
function account_security_disable_totp(int $userId): array
{
    if ($userId <= 0) {
        return ['ok' => false, 'reason' => 'not_found'];
    }

    $pdo = conn_db();

    try {
        $pdo->beginTransaction();

        $sql = 'SELECT auth_totp_user_id, auth_totp_enabled_at FROM ' . db_table_name('auth_totp') . ' '
            . 'WHERE auth_totp_user_id = :user_id LIMIT 1';
        if (account_settings_supports_for_update($pdo)) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)
            || !is_string($row['auth_totp_enabled_at'] ?? null)
            || $row['auth_totp_enabled_at'] === '') {
            $pdo->rollBack();
            return ['ok' => false, 'reason' => 'not_enabled'];
        }

        $stmt = $pdo->prepare(
            'DELETE FROM ' . db_table_name('auth_recovery_code') . ' WHERE auth_recovery_code_user_id = :user_id'
        );
        $stmt->execute([':user_id' => $userId]);

        $stmt = $pdo->prepare(
            'DELETE FROM ' . db_table_name('auth_totp') . ' '
            . 'WHERE auth_totp_user_id = :user_id AND auth_totp_enabled_at IS NOT NULL'
        );
        $stmt->execute([':user_id' => $userId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('TOTP disable did not remove exactly one enabled row.');
        }

        remember_token_revoke_user($userId, $pdo);
        $currentSessionHash = function_exists('auth_session_registry_current_token_hash')
            ? auth_session_registry_current_token_hash($userId)
            : null;
        if ($currentSessionHash !== null && function_exists('auth_session_registry_revoke_user')) {
            auth_session_registry_revoke_user($userId, $currentSessionHash, $pdo, false);
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
