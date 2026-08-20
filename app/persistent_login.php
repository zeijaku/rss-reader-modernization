<?php

declare(strict_types=1);

const PERSISTENT_LOGIN_COOKIE_NAME = 'iguguru_remember';

/** Accept only the explicit checkbox value emitted by the login form. */
function persistent_login_is_requested(mixed $value): bool
{
    return is_string($value) && $value === '1';
}

function persistent_login_cookie_value(): ?string
{
    $value = $_COOKIE[PERSISTENT_LOGIN_COOKIE_NAME] ?? null;
    return is_string($value) && $value !== '' ? $value : null;
}

/** @return array{expires:int,path:string,domain:string,secure:bool,httponly:bool,samesite:string} */
function persistent_login_cookie_options(int $expiresAt): array
{
    return [
        'expires' => $expiresAt,
        'path' => '/',
        'domain' => '',
        'secure' => app_request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function persistent_login_set_cookie(string $cookieValue, int $expiresAt): bool
{
    if (remember_token_parse($cookieValue) === null || $expiresAt <= time()) {
        return false;
    }
    if (headers_sent()) {
        return false;
    }

    $written = setcookie(PERSISTENT_LOGIN_COOKIE_NAME, $cookieValue, persistent_login_cookie_options($expiresAt));
    if ($written) {
        $_COOKIE[PERSISTENT_LOGIN_COOKIE_NAME] = $cookieValue;
    }
    return $written;
}

function persistent_login_clear_cookie(): bool
{
    unset($_COOKIE[PERSISTENT_LOGIN_COOKIE_NAME]);
    if (headers_sent()) {
        return false;
    }

    return setcookie(PERSISTENT_LOGIN_COOKIE_NAME, '', persistent_login_cookie_options(time() - 42000));
}

/**
 * Revoke the current browser token and always clear its cookie.
 * Token values are deliberately excluded from logs.
 */
function persistent_login_revoke_current(): void
{
    $cookieValue = persistent_login_cookie_value();
    if ($cookieValue !== null) {
        try {
            remember_token_revoke_cookie($cookieValue);
        } catch (Throwable $exception) {
            error_log('Persistent login revocation failed: ' . $exception::class);
        }
    }
    persistent_login_clear_cookie();
}

/** Replace any current-browser token with a newly issued fixed-30-day token. */
function persistent_login_issue_for_user(int $userId): bool
{
    persistent_login_revoke_current();

    try {
        $issued = remember_token_issue($userId);
        if (($issued['ok'] ?? false) !== true) {
            persistent_login_clear_cookie();
            return false;
        }
        $cookieValue = $issued['cookie_value'] ?? null;
        $expiresAt = $issued['expires_at'] ?? null;
        if (!is_string($cookieValue) || !is_int($expiresAt)) {
            persistent_login_clear_cookie();
            return false;
        }
        if (!persistent_login_set_cookie($cookieValue, $expiresAt)) {
            remember_token_revoke_cookie($cookieValue);
            persistent_login_clear_cookie();
            return false;
        }
        return true;
    } catch (Throwable $exception) {
        persistent_login_clear_cookie();
        error_log('Persistent login issue failed: ' . $exception::class);
        return false;
    }
}

/**
 * Restore an anonymous/expired session from a valid Remember Token.
 * Validation rotates the validator; failed cookies are silently cleared.
 */
function persistent_login_restore_session(): bool
{
    if (app_session_is_authenticated()) {
        return true;
    }

    $cookieValue = persistent_login_cookie_value();
    if ($cookieValue === null) {
        return false;
    }

    try {
        $validated = remember_token_validate_and_rotate($cookieValue);
        if (($validated['ok'] ?? false) !== true) {
            persistent_login_clear_cookie();
            return false;
        }

        $userId = $validated['user_id'] ?? null;
        $rotatedCookie = $validated['cookie_value'] ?? null;
        $expiresAt = $validated['expires_at'] ?? null;
        if (!is_int($userId) || $userId <= 0 || !is_string($rotatedCookie) || !is_int($expiresAt)) {
            persistent_login_clear_cookie();
            return false;
        }

        $previousCsrfToken = app_csrf_current_token();
        app_session_login($userId);
        if ($previousCsrfToken !== null) {
            app_csrf_allow_previous_token($previousCsrfToken);
        }
        if (!persistent_login_set_cookie($rotatedCookie, $expiresAt)) {
            // The session remains valid for this browser session, but the
            // rotated persistent token must not remain active without a cookie.
            remember_token_revoke_cookie($rotatedCookie);
            persistent_login_clear_cookie();
        }
        return true;
    } catch (Throwable $exception) {
        persistent_login_clear_cookie();
        error_log('Persistent login restore failed: ' . $exception::class);
        return false;
    }
}
