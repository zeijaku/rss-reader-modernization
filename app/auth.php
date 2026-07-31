<?php

declare(strict_types=1);

/** Normalize the login identifier used by the Secure Baseline. */
function auth_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function auth_email_is_valid(string $email): bool
{
    $normalized = auth_normalize_email($email);
    return $normalized !== ''
        && strlen($normalized) <= 254
        && filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false;
}

/** Store only a deterministic keyed identity, not the raw email address. */
function auth_identity_key(string $email): string
{
    $normalized = auth_normalize_email($email);
    return hash_hmac('sha256', $normalized, (string) INI_HASH_KEY);
}

function auth_password_is_valid_for_registration(string $password): bool
{
    $length = strlen($password);
    return $length >= AUTH_PASSWORD_MIN_LENGTH
        && $length <= AUTH_PASSWORD_MAX_LENGTH
        && strpos($password, "\0") === false;
}

function auth_password_hash(string $password): string
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($hash) || $hash === '') {
        throw new RuntimeException('Unable to hash the password.');
    }
    return $hash;
}

function auth_is_password_hash(string $stored): bool
{
    $info = password_get_info($stored);
    return isset($info['algo']) && $info['algo'] !== null && $info['algo'] !== 0;
}

/**
 * @return array{ok:bool,user_id?:int,reason?:string}
 */
function auth_authenticate(string $email, string $password): array
{
    if (!auth_email_is_valid($email) || $password === '' || strlen($password) > AUTH_PASSWORD_MAX_LENGTH) {
        auth_dummy_password_verify($password);
        return ['ok' => false, 'reason' => 'invalid_credentials'];
    }

    $identity = auth_identity_key($email);
    $users = find_active_users_by_identity($identity);

    if (count($users) !== 1) {
        auth_dummy_password_verify($password);
        return ['ok' => false, 'reason' => count($users) > 1 ? 'ambiguous_identity' : 'invalid_credentials'];
    }

    $user = $users[0];
    $stored = (string) ($user['user_password'] ?? '');
    if (!auth_is_password_hash($stored) || !password_verify($password, $stored)) {
        return ['ok' => false, 'reason' => 'invalid_credentials'];
    }

    $userId = (int) ($user['user_id'] ?? 0);
    if ($userId <= 0) {
        return ['ok' => false, 'reason' => 'invalid_credentials'];
    }

    if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
        update_user_password_hash($userId, auth_password_hash($password));
    }

    return ['ok' => true, 'user_id' => $userId];
}

function auth_dummy_password_verify(string $password): void
{
    static $dummyHash = null;
    if (!is_string($dummyHash)) {
        $dummyHash = auth_password_hash('dummy-password-value-never-used-for-login');
    }
    password_verify($password, $dummyHash);
}

/**
 * @return array{ok:bool,user_id?:int,reason?:string}
 */
function auth_register(string $email, string $password): array
{
    if (!REGISTRATION_ENABLED) {
        return ['ok' => false, 'reason' => 'registration_disabled'];
    }
    if (!auth_email_is_valid($email)) {
        return ['ok' => false, 'reason' => 'invalid_email'];
    }
    if (!auth_password_is_valid_for_registration($password)) {
        return ['ok' => false, 'reason' => 'invalid_password'];
    }

    $identity = auth_identity_key($email);
    if (user_identity_exists($identity)) {
        return ['ok' => false, 'reason' => 'identity_exists'];
    }

    $userId = entry_user($identity, auth_password_hash($password));
    return ['ok' => true, 'user_id' => $userId];
}

/** Stable non-secret key used before validation for login rate limiting. */
function auth_throttle_identity(string $email): string
{
    return hash_hmac('sha256', auth_normalize_email($email), (string) INI_HASH_KEY);
}
