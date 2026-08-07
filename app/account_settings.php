<?php

declare(strict_types=1);

/** @return array{ok:bool,reason?:string} */
function account_settings_change_email(int $userId, string $newEmail, string $currentPassword): array
{
    if ($userId <= 0) {
        return ['ok' => false, 'reason' => 'not_found'];
    }
    if (!auth_email_is_valid($newEmail)) {
        return ['ok' => false, 'reason' => 'invalid_email'];
    }
    if (!account_settings_current_password_is_valid($currentPassword)) {
        auth_dummy_password_verify($currentPassword);
        return ['ok' => false, 'reason' => 'invalid_current_password'];
    }

    $identity = auth_identity_key($newEmail);
    $conn = conn_db();

    try {
        $conn->beginTransaction();
        $user = account_settings_find_active_user($conn, $userId, true);
        if ($user === null) {
            auth_dummy_password_verify($currentPassword);
            $conn->rollBack();
            return ['ok' => false, 'reason' => 'not_found'];
        }

        $storedPassword = (string) ($user['user_password'] ?? '');
        if (!auth_is_password_hash($storedPassword) || !password_verify($currentPassword, $storedPassword)) {
            $conn->rollBack();
            return ['ok' => false, 'reason' => 'invalid_current_password'];
        }

        if (hash_equals((string) ($user['user_email'] ?? ''), $identity)) {
            $conn->rollBack();
            return ['ok' => false, 'reason' => 'email_unchanged'];
        }

        if (account_settings_identity_exists($conn, $identity, $userId, true)) {
            $conn->rollBack();
            return ['ok' => false, 'reason' => 'identity_exists'];
        }

        $stmt = $conn->prepare(
            'UPDATE ' . db_table_name('user_info') . ' '
            . 'SET user_email = :email WHERE user_id = :user_id AND user_flag = 0'
        );
        $stmt->execute([
            ':email' => $identity,
            ':user_id' => $userId,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Account email update did not affect one active user.');
        }

        $conn->commit();
        return ['ok' => true];
    } catch (Throwable $exception) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $exception;
    }
}

/** @return array{ok:bool,reason?:string} */
function account_settings_change_password(
    int $userId,
    string $currentPassword,
    string $newPassword,
    string $newPasswordConfirmation
): array {
    if ($userId <= 0) {
        return ['ok' => false, 'reason' => 'not_found'];
    }
    if (!account_settings_current_password_is_valid($currentPassword)) {
        auth_dummy_password_verify($currentPassword);
        return ['ok' => false, 'reason' => 'invalid_current_password'];
    }
    if (!auth_password_is_valid_for_registration($newPassword)) {
        return ['ok' => false, 'reason' => 'invalid_password'];
    }
    if (!hash_equals($newPassword, $newPasswordConfirmation)) {
        return ['ok' => false, 'reason' => 'password_mismatch'];
    }

    $conn = conn_db();

    try {
        $conn->beginTransaction();
        $user = account_settings_find_active_user($conn, $userId, true);
        if ($user === null) {
            auth_dummy_password_verify($currentPassword);
            $conn->rollBack();
            return ['ok' => false, 'reason' => 'not_found'];
        }

        $storedPassword = (string) ($user['user_password'] ?? '');
        if (!auth_is_password_hash($storedPassword) || !password_verify($currentPassword, $storedPassword)) {
            $conn->rollBack();
            return ['ok' => false, 'reason' => 'invalid_current_password'];
        }
        if (password_verify($newPassword, $storedPassword)) {
            $conn->rollBack();
            return ['ok' => false, 'reason' => 'password_unchanged'];
        }

        $stmt = $conn->prepare(
            'UPDATE ' . db_table_name('user_info') . ' '
            . 'SET user_password = :password WHERE user_id = :user_id AND user_flag = 0'
        );
        $stmt->execute([
            ':password' => auth_password_hash($newPassword),
            ':user_id' => $userId,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Account password update did not affect one active user.');
        }

        // Password changes invalidate persistent login on every browser/device.
        remember_token_revoke_user($userId, $conn);

        $conn->commit();
        return ['ok' => true];
    } catch (Throwable $exception) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $exception;
    }
}

function account_settings_current_password_is_valid(string $password): bool
{
    return $password !== ''
        && strlen($password) <= AUTH_PASSWORD_MAX_LENGTH
        && strpos($password, "\0") === false;
}

/** @return array<string,mixed>|null */
function account_settings_find_active_user(PDO $conn, int $userId, bool $lock): ?array
{
    $sql = 'SELECT user_id, user_email, user_password, user_flag FROM ' . db_table_name('user_info') . ' '
        . 'WHERE user_id = :user_id AND user_flag = 0 LIMIT 1';
    if ($lock && account_settings_supports_for_update($conn)) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function account_settings_identity_exists(PDO $conn, string $identity, int $excludeUserId, bool $lock): bool
{
    $sql = 'SELECT user_id FROM ' . db_table_name('user_info') . ' '
        . 'WHERE user_email = :email AND user_id <> :user_id LIMIT 1';
    if ($lock && account_settings_supports_for_update($conn)) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':email' => $identity,
        ':user_id' => $excludeUserId,
    ]);
    return $stmt->fetchColumn() !== false;
}

function account_settings_supports_for_update(PDO $conn): bool
{
    return strtolower((string) $conn->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'mysql';
}

function account_settings_throttle_identity(int $userId): string
{
    return hash_hmac('sha256', 'account-settings:' . $userId, (string) INI_HASH_KEY);
}
