<?php

declare(strict_types=1);

/**
 * V1.19-B broad module extracted from the v1.18.0 facade.
 * Function bodies are intentionally kept unchanged.
 */

/** @return array{status:int,body:array<string,mixed>} */
function api_account_email_update(int $userId, array $input): array
{
    $newEmail = api_string($input, 'new_email');
    $currentPassword = api_string($input, 'current_password');

    if (!auth_email_is_valid($newEmail)) {
        return api_validation_error('新しいメールアドレスを確認してください。');
    }
    if (!account_settings_current_password_is_valid($currentPassword)) {
        return api_error('current_password_invalid', '現在のパスワードを確認してください。', 403);
    }

    $rate = api_account_settings_rate_status($userId);
    if ($rate['blocked']) {
        return api_error('account_settings_throttled', '試行回数が多いため、しばらく待ってから再度お試しください。', 429);
    }

    try {
        $result = account_settings_change_email($userId, $newEmail, $currentPassword);
    } catch (Throwable $exception) {
        error_log('Account email update failed.');
        return api_error('account_update_failed', 'メールアドレスを変更出来ませんでした。', 503);
    }

    $reason = (string) ($result['reason'] ?? '');
    if (($result['ok'] ?? false) !== true) {
        if ($reason === 'invalid_current_password') {
            api_account_settings_record_failure($userId);
            return api_error('current_password_invalid', '現在のパスワードを確認してください。', 403);
        }
        if ($reason === 'identity_exists') {
            return api_error('email_in_use', 'このメールアドレスは使用出来ません。', 409);
        }
        if ($reason === 'email_unchanged') {
            return api_validation_error('新しいメールアドレスを入力してください。');
        }
        if ($reason === 'invalid_email') {
            return api_validation_error('新しいメールアドレスを確認してください。');
        }
        if ($reason === 'not_found') {
            return api_error('not_found', 'Account was not found.', 404);
        }
        return api_error('account_update_failed', 'メールアドレスを変更出来ませんでした。', 409);
    }

    api_account_settings_record_success($userId);
    $csrfToken = api_account_settings_rotate_session($userId);
    return api_success(['csrf_token' => $csrfToken]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_account_password_update(int $userId, array $input): array
{
    $currentPassword = api_string($input, 'current_password');
    $newPassword = api_string($input, 'new_password');
    $newPasswordConfirmation = api_string($input, 'new_password_confirmation');

    if (!account_settings_current_password_is_valid($currentPassword)) {
        return api_error('current_password_invalid', '現在のパスワードを確認してください。', 403);
    }
    if (!auth_password_is_valid_for_registration($newPassword)) {
        return api_validation_error('新しいパスワードは' . AUTH_PASSWORD_MIN_LENGTH . '文字以上' . AUTH_PASSWORD_MAX_LENGTH . '文字以下で入力してください。');
    }
    if (!hash_equals($newPassword, $newPasswordConfirmation)) {
        return api_validation_error('新しいパスワードが一致していません。');
    }

    $rate = api_account_settings_rate_status($userId);
    if ($rate['blocked']) {
        return api_error('account_settings_throttled', '試行回数が多いため、しばらく待ってから再度お試しください。', 429);
    }

    try {
        $result = account_settings_change_password($userId, $currentPassword, $newPassword, $newPasswordConfirmation);
    } catch (Throwable $exception) {
        error_log('Account password update failed.');
        return api_error('account_update_failed', 'パスワードを変更出来ませんでした。', 503);
    }

    $reason = (string) ($result['reason'] ?? '');
    if (($result['ok'] ?? false) !== true) {
        if ($reason === 'invalid_current_password') {
            api_account_settings_record_failure($userId);
            return api_error('current_password_invalid', '現在のパスワードを確認してください。', 403);
        }
        if ($reason === 'password_mismatch') {
            return api_validation_error('新しいパスワードが一致していません。');
        }
        if ($reason === 'password_unchanged') {
            return api_validation_error('現在とは異なるパスワードを入力してください。');
        }
        if ($reason === 'invalid_password') {
            return api_validation_error('新しいパスワードは' . AUTH_PASSWORD_MIN_LENGTH . '文字以上' . AUTH_PASSWORD_MAX_LENGTH . '文字以下で入力してください。');
        }
        if ($reason === 'not_found') {
            return api_error('not_found', 'Account was not found.', 404);
        }
        return api_error('account_update_failed', 'パスワードを変更出来ませんでした。', 409);
    }

    api_account_settings_record_success($userId);
    persistent_login_clear_cookie();
    $csrfToken = api_account_settings_rotate_session($userId);
    return api_success(['csrf_token' => $csrfToken]);
}

/** @return array{blocked:bool,retry_after:int} */
function api_account_settings_rate_status(int $userId): array
{
    return login_throttle_status(account_settings_throttle_identity($userId), api_account_settings_remote_ip());
}

function api_account_settings_record_failure(int $userId): void
{
    login_throttle_record_failure(account_settings_throttle_identity($userId), api_account_settings_remote_ip());
}

function api_account_settings_record_success(int $userId): void
{
    login_throttle_record_success(account_settings_throttle_identity($userId), api_account_settings_remote_ip());
}

function api_account_settings_remote_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 128);
}

function api_account_settings_rotate_session(int $userId): string
{
    if (session_status() === PHP_SESSION_ACTIVE && app_session_user_id() === $userId) {
        $previousCsrfToken = app_csrf_current_token();
        app_session_login($userId);
        if ($previousCsrfToken !== null) {
            app_csrf_allow_previous_token($previousCsrfToken);
        }
        return app_csrf_token();
    }
    return '';
}

/** @return array{status:int,body:array<string,mixed>} */
function api_settings_update(int $userId, array $input): array
{
    if (search_conf($userId) === []) {
        return api_error('not_found', 'User configuration was not found.', 404);
    }

    $style = app_normalize_theme($input['conf_style'] ?? null);
    $navStyle = app_normalize_nav_style($input['conf_style_nav'] ?? null);
    if ($style === null || $navStyle === null) {
        return api_validation_error('Theme or navbar style is invalid.');
    }

    $links = [];
    $views = [];
    $icons = [];
    for ($i = 1; $i <= 4; $i++) {
        $links[$i] = app_validate_navbar_url($input['conf_style_navlink' . $i] ?? null);
        $views[$i] = app_validate_text($input['conf_style_navlink_view' . $i] ?? null, 8, true);
        $icons[$i] = app_normalize_nav_icon($input['conf_style_navlink_icon' . $i] ?? null);
        if ($links[$i] === null || $views[$i] === null || $icons[$i] === null) {
            return api_validation_error('Navbar link, label, or icon is invalid.');
        }
    }

    update_setting(
        $userId,
        $style,
        $navStyle,
        $links[1],
        $views[1],
        $icons[1],
        $links[2],
        $views[2],
        $icons[2],
        $links[3],
        $views[3],
        $icons[3],
        $links[4],
        $views[4],
        $icons[4]
    );

    return api_success();
}

/** @return array{status:int,body:array<string,mixed>} */
function api_tabs_update(int $userId, array $input): array
{
    if (search_conf($userId) === []) {
        return api_error('not_found', 'User configuration was not found.', 404);
    }

    $tabs = [];
    for ($i = 1; $i <= 4; $i++) {
        $tabs[$i] = app_validate_text($input['conf_style_tabname' . $i] ?? null, 16, true);
        if ($tabs[$i] === null) {
            return api_validation_error('Tab names must be valid UTF-8 text at most 16 characters.');
        }
    }

    update_tab($userId, $tabs[1], $tabs[2], $tabs[3], $tabs[4]);
    return api_success();
}
