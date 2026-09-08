<?php

declare(strict_types=1);

/** Verify only the current account Password. Factor verification is performed separately. */
function auth_step_up_verify_password(int $userId, string $password): bool
{
    if ($userId <= 0 || !account_settings_current_password_is_valid($password)) {
        auth_dummy_password_verify($password);
        return false;
    }

    $user = account_settings_find_active_user(conn_db(), $userId, false);
    if ($user === null) {
        auth_dummy_password_verify($password);
        return false;
    }

    $storedPassword = (string) ($user['user_password'] ?? '');
    return auth_is_password_hash($storedPassword) && password_verify($password, $storedPassword);
}
