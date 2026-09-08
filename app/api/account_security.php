<?php

declare(strict_types=1);

function api_account_security_remote_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 128);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_account_security_step_up_verify(int $userId, array $input): array
{
    $password = isset($input['current_password']) && is_string($input['current_password'])
        ? $input['current_password']
        : '';
    $method = isset($input['factor_type']) && is_string($input['factor_type'])
        ? trim($input['factor_type'])
        : '';
    $factorCode = isset($input['factor_code']) && is_string($input['factor_code'])
        ? trim($input['factor_code'])
        : '';

    if (!account_settings_current_password_is_valid($password)) {
        return api_validation_error('現在のパスワードを入力してください。');
    }
    if (!in_array($method, ['totp', 'recovery'], true)) {
        return api_validation_error('本人確認方法を選択してください。');
    }
    if (($method === 'totp' && preg_match('/\A[0-9]{6}\z/D', $factorCode) !== 1)
        || ($method === 'recovery' && strlen($factorCode) > 32)) {
        return api_validation_error('本人確認コードを確認してください。');
    }

    $ipAddress = api_account_security_remote_ip();
    $accountRate = api_account_settings_rate_status($userId);
    $factorRate = auth_2fa_throttle_status($userId, $ipAddress);
    if (($accountRate['blocked'] ?? false) === true || ($factorRate['blocked'] ?? false) === true) {
        return api_error('step_up_throttled', '試行回数が多いため、しばらく待ってから再度お試しください。', 429);
    }

    try {
        $status = auth_totp_status($userId);
        if (($status['enabled'] ?? false) !== true) {
            app_session_step_up_clear();
            return api_error('totp_not_enabled', '2段階認証が有効ではありません。', 409);
        }

        if (!auth_step_up_verify_password($userId, $password)) {
            api_account_settings_record_failure($userId);
            auth_audit_log_record('step_up', 'failure', $userId, null, $method);
            return api_error('step_up_invalid', '現在のパスワードまたは本人確認コードを確認してください。', 403);
        }

        $verified = $method === 'totp'
            ? auth_totp_verify_enabled_code($userId, $factorCode)
            : auth_recovery_code_consume($userId, $factorCode);

        if (!$verified) {
            auth_2fa_throttle_record_failure($userId, $ipAddress);
            auth_audit_log_record('step_up', 'failure', $userId, null, $method);
            return api_error('step_up_invalid', '現在のパスワードまたは本人確認コードを確認してください。', 403);
        }

        api_account_settings_record_success($userId);
        auth_2fa_throttle_record_success($userId, $ipAddress);
        app_session_step_up_grant($userId, $method);
        auth_audit_log_record('step_up', 'success', $userId, null, $method);
        if ($method === 'recovery') {
            auth_audit_log_record('recovery_code', 'success', $userId, null, 'recovery');
        }

        $data = [
            'verified' => true,
            'expires_in' => AUTH_STEP_UP_TIMEOUT,
        ];
        if ($method === 'recovery') {
            $recoveryStatus = auth_recovery_code_status($userId);
            $data['recovery_remaining'] = max(0, (int) ($recoveryStatus['unused'] ?? 0));
        }
        return api_success($data);
    } catch (AppTotpException $exception) {
        error_log('Account Security step-up unavailable: ' . $exception::class);
        return api_error('step_up_unavailable', '本人確認を完了出来ませんでした。サーバー設定を確認してください。', 503);
    } catch (Throwable $exception) {
        error_log('Account Security step-up failed: ' . $exception::class);
        return api_error('step_up_failed', '本人確認を完了出来ませんでした。', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_account_security_totp_disable(int $userId): array
{
    if (!app_session_step_up_is_valid()) {
        return api_error('step_up_required', '2FA設定を変更する前に、もう一度本人確認してください。', 403);
    }

    try {
        $result = account_security_disable_totp($userId);
        if (($result['ok'] ?? false) !== true) {
            app_session_step_up_clear();
            if (($result['reason'] ?? '') === 'not_enabled') {
                return api_error('totp_not_enabled', '2段階認証は既に無効です。', 409);
            }
            return api_error('totp_disable_failed', '2段階認証を解除出来ませんでした。', 409);
        }

        persistent_login_clear_cookie();

        // Security policy changed: rotate the current authenticated session and
        // CSRF token. This also clears the short-lived step-up grant.
        $previousCsrfToken = app_csrf_current_token();
        app_session_login($userId);
        if ($previousCsrfToken !== null) {
            app_csrf_allow_previous_token($previousCsrfToken);
        }
        auth_audit_log_record('totp_disable', 'success', $userId, null, null);

        return api_success([
            'state' => 'unconfigured',
            'csrf_token' => app_csrf_token(),
        ]);
    } catch (Throwable $exception) {
        error_log('Account 2FA disable failed: ' . $exception::class);
        return api_error('totp_disable_failed', '2段階認証を解除出来ませんでした。', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_account_security_dispatch(string $action, int $userId, array $input): array
{
    return match ($action) {
        'account.security.stepup.verify' => api_account_security_step_up_verify($userId, $input),
        'account.security.totp.disable' => api_account_security_totp_disable($userId),
        default => api_error('unknown_action', 'Unknown API action.', 400),
    };
}
